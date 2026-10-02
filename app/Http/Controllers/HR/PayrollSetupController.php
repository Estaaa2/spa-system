<?php

namespace App\Http\Controllers\HR;

use App\Exceptions\PayrollSetupException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\HR\Concerns\HandlesPayrollSetupForms;
use App\Models\Branch;
use App\Models\CommissionRule;
use App\Models\Package;
use App\Models\Spa;
use App\Models\Treatment;
use App\Services\Payroll\PayrollRunService;
use App\Services\Payroll\PayrollSetupService;
use App\Services\Payroll\StatutoryCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Payroll setup page (Unit 5 A): Schedule, Branch minimum wage, Commission rules.
 * Spa-wide data (consolidated payroll). Routes (Unit 7): view with `view payroll`,
 * every write with `edit payroll`, via branch.permission.
 */
class PayrollSetupController extends Controller
{
    use HandlesPayrollSetupForms;

    public const TABS = ['schedule', 'wages', 'commission'];

    public function __construct(
        private readonly PayrollSetupService $setup,
        private readonly StatutoryCalculator $calc,
    ) {
    }

    // =====================================================================
    // Page
    // =====================================================================

    public function index(Request $request, PayrollRunService $runs): View
    {
        $spa = $this->currentSpa();
        $today = now()->toDateString();

        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'schedule';

        $branches = Branch::query()->where('spa_id', $spa->id)->orderByDesc('is_main')->orderBy('name')->get();

        // Treatments/packages are per-branch and carry a session-based global scope; payroll
        // is spa-wide, so the scope is removed and the spa filter applied by hand.
        $treatments = Treatment::withoutGlobalScope('spa_branch')->withTrashed()
            ->where('spa_id', $spa->id)->orderBy('name')->get(['id', 'name', 'branch_id', 'deleted_at']);
        $packages = Package::withoutGlobalScope('spa_branch')->withTrashed()
            ->where('spa_id', $spa->id)->orderBy('name')->get(['id', 'name', 'branch_id', 'deleted_at']);

        $rules = CommissionRule::query()->where('spa_id', $spa->id)
            ->orderByRaw('effective_to IS NOT NULL')   // open rules first
            ->orderBy('target_type')->orderBy('branch_id')->orderByDesc('effective_from')
            ->get();

        return view('hr.payroll.setup', [
            'spa'           => $spa,
            'tab'           => $tab,
            'branches'      => $branches,
            'treatments'    => $treatments,
            'packages'      => $packages,
            'rules'         => $rules,
            'ruleUsage'     => $this->setup->commissionRuleUsage($spa),
            'periodPreview' => $this->periodPreview($spa, $runs),
            'factors'       => $this->factors($today),
            'maxInterval'   => (int) config('payroll.payment_timing.max_pay_interval_days'),
            'today'         => $today,
        ]);
    }

    // =====================================================================
    // Schedule
    // =====================================================================

    public function updateSchedule(Request $request)
    {
        $spa = $this->currentSpa();
        $max = (int) config('payroll.payment_timing.max_pay_interval_days');   // 16 — rates sheet §12, Art. 103 (VERIFY)

        return $this->handleForm($request, 'schedule', [
            'payroll_first_cutoff_day' => ['required', 'integer', 'between:1,27'],
            // Upper bound is DESIGN (not in the rates sheet): the offset does not change the
            // gap between pay dates, but more than one full interval of delay is refused.
            'payroll_pay_day_offset'   => ['required', 'integer', 'between:0,'.$max],
        ], function (array $data) use ($spa) {
            $this->setup->updateSchedule($spa, $data);

            return redirect()->route('payroll.setup.index', ['tab' => 'schedule'])
                ->with('success', 'Pay schedule saved. Draft runs keep their old periods until regenerated.');
        }, [], function ($v) use ($request, $max) {
            $first = (int) $request->input('payroll_first_cutoff_day');
            if ($first < 1 || $first > 27) {
                return;
            }
            // With a fixed offset, the gap between consecutive pay dates equals the length
            // of the later cutoff (PayrollRunService::buildPeriod). Worst case is a 31-day
            // month for cutoff 2; cutoff 1 is always `first` days long.
            $len1 = $first;
            $len2 = 31 - $first;
            if ($len1 > $max || $len2 > $max) {
                $v->errors()->add('payroll_first_cutoff_day', sprintf(
                    'With day %d, cutoff 1 is %d days and cutoff 2 is up to %d days. Labor Code Art. 103 requires pay at intervals not exceeding %d days, so the first cutoff must end on day %d or %d.',
                    $first, $len1, $len2, $max, 31 - $max, $max,
                ));
            }
        });
    }

    // =====================================================================
    // Branch minimum wage
    // =====================================================================

    public function updateBranchWage(Request $request, Branch $branch)
    {
        $spa = $this->currentSpa();
        abort_unless((int) $branch->spa_id === (int) $spa->id, 404);

        return $this->handleForm($request, 'wage', [
            'min_daily_wage'          => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'wage_order_ref'          => ['required', 'string', 'max:255'],
            'min_wage_effective_from' => ['required', 'date_format:Y-m-d'],
        ], function (array $data) use ($branch) {
            $this->setup->updateBranchWage($branch, $data);

            // branches holds ONE rate (locked model), and the engine applies it to every
            // day it computes. A future-dated rate therefore also applies to days before
            // its effective date (lawful over-payment; MinimumWageTopUp warns per day).
            $warning = $data['min_wage_effective_from'] > now()->toDateString()
                ? "This rate takes effect {$data['min_wage_effective_from']}, but payroll uses it from now on — days before that date will be topped up to the new rate. To avoid that, enter the new rate on its effective date, after the earlier cutoffs are generated."
                : null;

            return redirect()->route('payroll.setup.index', ['tab' => 'wages'])
                ->with('success', "Minimum wage saved for {$branch->name}.")
                ->with('warning', $warning);
        }, [
            'min_daily_wage.regex'     => 'Enter the daily amount in pesos, e.g. 600 or 600.00.',
            'min_daily_wage.not_regex' => 'The minimum daily wage must be more than zero.',
        ]);
    }

    // =====================================================================
    // Commission rules
    // =====================================================================

    public function storeRule(Request $request)
    {
        $spa = $this->currentSpa();

        return $this->handleForm($request, 'ruleCreate', $this->ruleRules($spa, $request), function (array $data) use ($spa) {
            $rule = $this->setup->createRule($spa, $this->normalizeTarget($data), Auth::user());

            return redirect()->route('payroll.setup.index', ['tab' => 'commission'])
                ->with('success', "Commission rule #{$rule->id} added.");
        });
    }

    public function updateRule(Request $request, CommissionRule $rule)
    {
        $spa = $this->currentSpa();
        $this->assertOwned($rule, $spa);

        return $this->handleForm($request, 'ruleEdit', $this->ruleRules($spa, $request, true), function (array $data) use ($rule) {
            $this->setup->updateRule($rule, $this->normalizeTarget($data));

            return redirect()->route('payroll.setup.index', ['tab' => 'commission'])
                ->with('success', "Commission rule #{$rule->id} updated.");
        });
    }

    public function supersedeRule(Request $request, CommissionRule $rule)
    {
        $spa = $this->currentSpa();
        $this->assertOwned($rule, $spa);

        return $this->handleForm($request, 'ruleChange', [
            'method'         => ['required', Rule::in([CommissionRule::METHOD_PERCENT, CommissionRule::METHOD_FLAT])],
            'value'          => $this->valueRules($request),
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
        ], function (array $data) use ($rule) {
            $new = $this->setup->supersedeRule($rule, $data['method'], $data['value'], $data['effective_from'], $data['effective_to'] ?? null, Auth::user());

            return redirect()->route('payroll.setup.index', ['tab' => 'commission'])
                ->with('success', "Rule #{$rule->id} now ends {$new->effective_from->copy()->subDay()->toDateString()}; new rule #{$new->id} starts {$new->effective_from->toDateString()}.");
        }, $this->valueMessages());
    }

    public function endRule(Request $request, CommissionRule $rule)
    {
        $spa = $this->currentSpa();
        $this->assertOwned($rule, $spa);

        return $this->handleForm($request, 'ruleEnd', [
            'effective_to' => ['required', 'date_format:Y-m-d'],
        ], function (array $data) use ($rule) {
            $this->setup->endRule($rule, $data['effective_to']);

            return redirect()->route('payroll.setup.index', ['tab' => 'commission'])
                ->with('success', "Rule #{$rule->id} now ends {$data['effective_to']}.");
        });
    }

    public function destroyRule(Request $request, CommissionRule $rule)
    {
        $spa = $this->currentSpa();
        $this->assertOwned($rule, $spa);

        return $this->handleForm($request, 'ruleDelete', [], function () use ($rule) {
            $id = $rule->id;
            $this->setup->deleteRule($rule);

            return redirect()->route('payroll.setup.index', ['tab' => 'commission'])
                ->with('success', "Commission rule #{$id} deleted.");
        });
    }

    /** "Which rule applies" — JSON for the preview panel. Read-only (`view payroll`). */
    public function previewRule(Request $request): JsonResponse
    {
        $spa = $this->currentSpa();

        $data = $request->validate([
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('spa_id', $spa->id)->whereNull('deleted_at')],
            'target'    => ['required', 'string', 'regex:/^(default|treatment_\d+|package_\d+)$/'],
            'date'      => ['required', 'date_format:Y-m-d'],
        ]);

        [$type, $id] = $data['target'] === 'default'
            ? [CommissionRule::TARGET_DEFAULT, null]
            : [str_starts_with($data['target'], 'treatment_') ? CommissionRule::TARGET_TREATMENT : CommissionRule::TARGET_PACKAGE,
               (int) substr($data['target'], strpos($data['target'], '_') + 1)];

        if ($id !== null && ! $this->targetExists($spa, $type, $id, true)) {
            return response()->json(['message' => 'That treatment or package is not part of this spa.'], 422);
        }

        $result = $this->setup->previewRule($spa, (int) $data['branch_id'], $type, $id, $data['date']);
        $rule = $result['rule'];

        return response()->json([
            'rule' => $rule ? [
                'id'             => $rule->id,
                'method'         => $rule->method,
                'value'          => (string) $rule->value,
                'branch_id'      => $rule->branch_id,
                'target_type'    => $rule->target_type,
                'target_id'      => $rule->target_id,
                'effective_from' => $rule->effective_from->toDateString(),
                'effective_to'   => $rule->effective_to?->toDateString(),
            ] : null,
            'tier' => $result['tier'],
        ]);
    }

    // =====================================================================
    // Internals
    // =====================================================================

    private function assertOwned(CommissionRule $rule, Spa $spa): void
    {
        abort_unless((int) $rule->spa_id === (int) $spa->id, 404);
    }

    /** @return array<string, mixed> */
    private function ruleRules(Spa $spa, Request $request, bool $isEdit = false): array
    {
        $type = $request->input('target_type');

        return [
            'branch_id'      => ['nullable', 'integer', Rule::exists('branches', 'id')->where('spa_id', $spa->id)->whereNull('deleted_at')],
            'target_type'    => ['required', Rule::in([CommissionRule::TARGET_DEFAULT, CommissionRule::TARGET_TREATMENT, CommissionRule::TARGET_PACKAGE])],
            'target_id'      => [
                Rule::requiredIf(in_array($type, [CommissionRule::TARGET_TREATMENT, CommissionRule::TARGET_PACKAGE], true)),
                'nullable', 'integer',
                function ($attr, $value, $fail) use ($spa, $type, $isEdit, $request) {
                    if ($value === null || ! in_array($type, [CommissionRule::TARGET_TREATMENT, CommissionRule::TARGET_PACKAGE], true)) {
                        return;
                    }
                    $targetBranch = $this->targetBranchId($spa, $type, (int) $value, $isEdit);
                    if ($targetBranch === false) {
                        $fail('Choose a '.$type.' from this spa.');

                        return;
                    }
                    // Treatments/packages belong to one branch and bookings only offer the
                    // booking branch's own list (BookingController), so a branch rule that
                    // targets another branch's service could never match any booking.
                    $ruleBranch = $request->input('branch_id');
                    if (filled($ruleBranch) && $targetBranch !== null && (int) $ruleBranch !== $targetBranch) {
                        $fail('This '.$type.' is sold at a different branch, so a rule for the chosen branch would never apply. Pick the branch that sells it, or “All branches”.');
                    }
                },
            ],
            'method'         => ['required', Rule::in([CommissionRule::METHOD_PERCENT, CommissionRule::METHOD_FLAT])],
            'value'          => $this->valueRules($request),
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
        ];
    }

    /** value DECIMAL(10,4): percent 0–100 (model), flat up to ₱999,999.9999. */
    private function valueRules(Request $request): array
    {
        return $request->input('method') === CommissionRule::METHOD_PERCENT
            ? ['required', 'regex:/^\d{1,3}(\.\d{1,4})?$/', 'numeric', 'max:100']
            : ['required', 'regex:/^\d{1,6}(\.\d{1,4})?$/'];
    }

    private function valueMessages(): array
    {
        return ['value.regex' => 'Enter a number with up to 4 decimal places.', 'value.max' => 'A percentage cannot exceed 100.'];
    }

    /** @return int|null|false branch id of the target, null if it has none, false if not found */
    private function targetBranchId(Spa $spa, string $type, int $id, bool $includeTrashed): int|null|false
    {
        $model = $type === CommissionRule::TARGET_TREATMENT ? Treatment::class : Package::class;
        $q = $model::withoutGlobalScope('spa_branch')->where('spa_id', $spa->id)->whereKey($id);
        $row = ($includeTrashed ? $q->withTrashed() : $q)->first(['id', 'branch_id']);

        return $row === null ? false : ($row->branch_id === null ? null : (int) $row->branch_id);
    }

    private function targetExists(Spa $spa, string $type, int $id, bool $includeTrashed): bool
    {
        $model = $type === CommissionRule::TARGET_TREATMENT ? Treatment::class : Package::class;
        $q = $model::withoutGlobalScope('spa_branch')->where('spa_id', $spa->id)->whereKey($id);

        return ($includeTrashed ? $q->withTrashed() : $q)->exists();
    }

    private function normalizeTarget(array $data): array
    {
        if ($data['target_type'] === CommissionRule::TARGET_DEFAULT) {
            $data['target_id'] = null;
        }
        $data['branch_id'] = isset($data['branch_id']) && $data['branch_id'] !== '' ? (int) $data['branch_id'] : null;

        return $data;
    }

    /**
     * The next two months of periods as the run engine will build them, or the setup
     * error it would throw. Uses PayrollRunService::buildPeriod so the preview cannot
     * drift from the engine.
     *
     * @return list<array{label: string, periods: list<array{cutoff: int, start: string, end: string, pay: string, days: int}>, error: ?string}>
     */
    private function periodPreview(Spa $spa, PayrollRunService $runs): array
    {
        $out = [];
        $month = Carbon::now()->startOfMonth();

        for ($i = 0; $i < 2; $i++, $month->addMonth()) {
            $row = ['label' => $month->format('F Y'), 'periods' => [], 'error' => null];
            try {
                foreach ([1, 2] as $c) {
                    $p = $runs->buildPeriod($spa, $month->year, $month->month, $c);
                    $row['periods'][] = ['cutoff' => $c, 'start' => $p->start, 'end' => $p->end, 'pay' => $p->payDate, 'days' => $p->lengthInDays()];
                }
            } catch (PayrollSetupException $e) {
                $row['error'] = $e->getMessage();
            }
            $out[] = $row;
        }

        return $out;
    }

    /** DOLE days-per-year factors in effect today (rates sheet §10), read through the calculator. */
    private function factors(string $date): array
    {
        $f = [];
        foreach ([['monthly', 1, 'Monthly-paid'], ['daily', 1, 'Daily-paid, 1 rest day a week'], ['daily', 2, 'Daily-paid, 2 rest days a week']] as [$basis, $rest, $label]) {
            try {
                $f[] = ['label' => $label, 'factor' => $this->calc->eemrFactor($basis, $rest, $date)];
            } catch (InvalidArgumentException) {
                $f[] = ['label' => $label, 'factor' => null];
            }
        }

        return $f;
    }
}