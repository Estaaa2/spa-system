<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR\Concerns\HandlesPayrollSetupForms;
use App\Models\Branch;
use App\Models\Spa;
use App\Models\Staff;
use App\Models\StaffPayProfile;
use App\Models\StaffRecurringItem;
use App\Services\Payroll\PayrollSetupService;
use App\Services\Payroll\StatutoryCalculator;
use App\Services\Payroll\Support\Decimal;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Staff pay setup (Unit 5 B): index of active staff and the per-staff page (pay
 * profiles, statutory IDs, recurring items). Spa-wide. Routes (Unit 7): view with
 * `view payroll`, writes with `edit payroll`, via branch.permission.
 */
class StaffPayProfileController extends Controller
{
    use HandlesPayrollSetupForms;

    public const ID_FIELDS = [
        'tin'           => 'TIN',
        'sss_no'        => 'SSS',
        'philhealth_no' => 'PhilHealth',
        'pagibig_no'    => 'Pag-IBIG',
    ];

    public const FILTERS = ['all', 'missing', 'no_profile', 'missing_ids'];

    public function __construct(
        private readonly PayrollSetupService $setup,
        private readonly StatutoryCalculator $calc,
    ) {
    }

    // =====================================================================
    // Index
    // =====================================================================

    public function index(Request $request): View
    {
        $spa = $this->currentSpa();
        $today = now()->toDateString();
        $filter = in_array($request->query('filter'), self::FILTERS, true) ? $request->query('filter') : 'all';

        // "Active" = the run engine's candidate test (PayrollRunService::candidates).
        $staff = Staff::query()
            ->with(['user.roles', 'branch' => fn ($q) => $q->withTrashed()])
            ->where('spa_id', $spa->id)
            ->where('employment_status', 'active')
            ->orderBy('id')
            ->get();

        $current = StaffPayProfile::query()->whereIn('staff_id', $staff->pluck('id'))->effectiveOn($today)
            ->orderByDesc('effective_from')->get()->unique('staff_id')->keyBy('staff_id');
        $upcoming = StaffPayProfile::query()->whereIn('staff_id', $staff->pluck('id'))->where('effective_from', '>', $today)
            ->orderBy('effective_from')->get()->unique('staff_id')->keyBy('staff_id');

        $rows = $staff->map(function (Staff $s) use ($current, $upcoming) {
            $missingIds = array_keys(array_filter($this->idState($s), fn ($st) => $st !== 'set'));

            return [
                'staff'        => $s,
                'name'         => $this->displayName($s),
                'role'         => $s->user?->getRoleNames()->first(),
                'profile'      => $current->get($s->id),
                'upcoming'     => $upcoming->get($s->id),
                'missing_ids'  => array_map(fn ($k) => self::ID_FIELDS[$k], $missingIds),
                'in_payroll'   => $s->branch !== null && ! $s->branch->trashed() && (bool) $s->branch->has_workforce_finance_suite,
            ];
        });

        $counts = [
            'total'       => $rows->count(),
            'no_profile'  => $rows->whereNull('profile')->count(),
            'missing_ids' => $rows->filter(fn ($r) => $r['missing_ids'] !== [])->count(),
            'excluded'    => $rows->where('in_payroll', false)->count(),
        ];

        $filtered = $rows->filter(fn ($r) => match ($filter) {
            'no_profile'  => $r['profile'] === null,
            'missing_ids' => $r['missing_ids'] !== [],
            'missing'     => $r['profile'] === null || $r['missing_ids'] !== [],
            default       => true,
        });

        // Grouped by home branch; main branch first, then by name; no-branch last.
        $groups = $filtered->groupBy(fn ($r) => $r['staff']->branch_id ?? 0)
            ->sortBy(fn ($g, $key) => $key === 0 ? 'zzz' : ($g->first()['staff']->branch->is_main ? '0' : '1').$g->first()['staff']->branch->name);

        return view('hr.payroll.staff.index', compact('groups', 'counts', 'filter'));
    }

    // =====================================================================
    // Per-staff page
    // =====================================================================

    public function show(Staff $staff): View
    {
        $spa = $this->currentSpa();
        $this->assertOwned($staff, $spa);

        $canEdit = Auth::user()?->hasBranchPermission('edit payroll') ?? false;
        $home = $staff->branch_id ? Branch::withTrashed()->find($staff->branch_id) : null;
        $usage = $this->setup->staffUsage($staff);

        $profiles = $staff->payProfiles()->orderByDesc('effective_from')->get();
        $wageChecks = $profiles->mapWithKeys(fn (StaffPayProfile $p) => [$p->id => $this->setup->minimumWageCheck($p, $home)]);

        $ids = [];
        $states = $this->idState($staff);
        foreach (self::ID_FIELDS as $attr => $label) {
            $state = $states[$attr];
            $plain = $state === 'set' ? (string) $staff->{$attr} : null;
            // Plaintext never goes into the page, even for editors: it is fetched on
            // demand through revealStatutoryIds(), which logs the access (Data Privacy
            // Act — government-issued numbers are sensitive personal information).
            $ids[$attr] = [
                'label'  => $label,
                'state'  => $state,
                'masked' => $plain === null ? null : $this->mask($plain),
            ];
        }

        $min = $home?->min_daily_wage === null ? null : Decimal::round2(Decimal::fromDb($home->min_daily_wage));
        try {
            $minMonthly = $min === null ? null : $this->calc->equivalentMonthlyRate($min, $this->calc->eemrFactor('monthly', 1, now()->toDateString()));
        } catch (InvalidArgumentException) {
            $minMonthly = null;
        }

        return view('hr.payroll.staff.show', [
            'staff'          => $staff->load('user.roles'),
            'name'           => $this->displayName($staff),
            'home'           => $home,
            'inPayroll'      => $home !== null && ! $home->trashed() && (bool) $home->has_workforce_finance_suite,
            'canEdit'        => $canEdit,
            'profiles'       => $profiles,
            'profileUsage'   => $usage['profiles'],
            'wageChecks'     => $wageChecks,
            'recurring'      => $staff->recurringItems()->orderByRaw('end_date IS NULL DESC')->orderByDesc('start_date')->get(),
            'recurringUsage' => $usage['recurring'],
            'ids'            => $ids,
            'idWarnings'     => $this->statutoryIdWarnings($staff, $spa),
            'minDaily'       => $min,
            'minMonthly'     => $minMonthly,
            'lastFinalized'  => $this->setup->lastFinalizedPeriodEnd($staff),
            'today'          => now()->toDateString(),
        ]);
    }

    // ── Pay profiles ────────────────────────────────────────────────────────

    public function storeProfile(Request $request, Staff $staff)
    {
        $this->assertOwned($staff, $this->currentSpa());

        return $this->handleForm($request, 'profileCreate', $this->profileRules($request), function (array $data) use ($staff) {
            $res = $this->setup->createProfile($staff, $data, Auth::user());
            $msg = 'Pay profile from '.$res['profile']->effective_from->toDateString().' saved.';
            if ($res['closed']) {
                $msg .= ' The previous profile now ends '.$res['closed']->effective_to->toDateString().'.';
            }

            return $this->toStaff($staff, 'profiles')->with('success', $msg)->with('warning', $this->profileWarnings($staff, $res['profile']));
        }, [], $this->restDayCheck($request));
    }

    public function updateProfile(Request $request, Staff $staff, StaffPayProfile $profile)
    {
        $this->assertOwned($staff, $this->currentSpa());
        abort_unless((int) $profile->staff_id === (int) $staff->id, 404);

        return $this->handleForm($request, 'profileEdit', $this->profileRules($request, true), function (array $data) use ($staff, $profile) {
            $this->setup->updateProfile($profile, $data);

            return $this->toStaff($staff, 'profiles')->with('success', 'Pay profile updated.')
                ->with('warning', $this->profileWarnings($staff, $profile->refresh()));
        }, [], $this->restDayCheck($request));
    }

    public function destroyProfile(Request $request, Staff $staff, StaffPayProfile $profile)
    {
        $this->assertOwned($staff, $this->currentSpa());
        abort_unless((int) $profile->staff_id === (int) $staff->id, 404);

        return $this->handleForm($request, 'profileDelete', [], function () use ($staff, $profile) {
            $this->setup->deleteProfile($profile);

            return $this->toStaff($staff, 'profiles')->with('success', 'Pay profile deleted.');
        });
    }

    // ── Statutory IDs ───────────────────────────────────────────────────────

    /**
     * Blank field = keep the stored number (the form never receives the old value);
     * `clear[]` removes a number. Characters only are validated — see statutoryIdWarnings()
     * for the non-blocking format hints.
     */
    public function updateStatutoryIds(Request $request, Staff $staff)
    {
        $spa = $this->currentSpa();
        $this->assertOwned($staff, $spa);

        $rule = ['nullable', 'string', 'max:20', 'regex:/^[0-9][0-9 \-]*$/'];
        $rules = array_fill_keys(array_keys(self::ID_FIELDS), $rule) + [
            'clear'   => ['sometimes', 'array'],
            'clear.*' => [Rule::in(array_keys(self::ID_FIELDS))],
        ];

        return $this->handleForm($request, 'ids', $rules, function (array $data) use ($staff, $spa) {
            $clear = $data['clear'] ?? [];
            $changes = [];
            foreach (array_keys(self::ID_FIELDS) as $attr) {
                if (in_array($attr, $clear, true)) {
                    $changes[$attr] = null;
                } elseif (filled($data[$attr] ?? null)) {
                    $changes[$attr] = $data[$attr];
                }
            }
            $this->setup->updateStatutoryIds($staff, $changes);

            Log::info('payroll.statutory_ids.updated', [
                'spa_id' => $spa->id, 'staff_id' => $staff->id, 'user_id' => Auth::id(),
                'fields' => array_keys($changes),   // which fields — never the values
            ]);

            $warnings = $this->statutoryIdWarnings($staff->refresh(), $spa);

            return $this->toStaff($staff, 'ids')
                ->with('success', $changes === [] ? 'No statutory IDs changed.' : 'Statutory IDs saved.')
                ->with('warning', $warnings === [] ? null : implode(' ', $warnings));
        }, ['*.regex' => 'Use digits, spaces and hyphens only.']);
    }

    /**
     * Returns the plaintext numbers for the Reveal button. POST (CSRF-protected, never
     * prefetched or cached) and logged: the Data Privacy Act treats government-issued
     * numbers as sensitive personal information (RA 10173 Sec. 3(l)), and access logs
     * are an expected technical safeguard (Sec. 20). Route: `edit payroll`.
     */
    public function revealStatutoryIds(Request $request, Staff $staff): JsonResponse
    {
        $spa = $this->currentSpa();
        $this->assertOwned($staff, $spa);

        $out = [];
        foreach (array_keys(self::ID_FIELDS) as $attr) {
            try {
                $out[$attr] = blank($staff->{$attr}) ? null : (string) $staff->{$attr};
            } catch (DecryptException) {
                $out[$attr] = null;
            }
        }

        Log::info('payroll.statutory_ids.revealed', [
            'spa_id' => $spa->id, 'staff_id' => $staff->id, 'user_id' => Auth::id(), 'ip' => $request->ip(),
        ]);

        return response()->json(['ids' => $out])
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    // ── Recurring items ─────────────────────────────────────────────────────

    public function storeRecurring(Request $request, Staff $staff)
    {
        $this->assertOwned($staff, $this->currentSpa());
        $kind = $request->input('kind');

        return $this->handleForm($request, 'recurringCreate', [
            'kind'              => ['required', Rule::in([StaffRecurringItem::KIND_EARNING, StaffRecurringItem::KIND_DEDUCTION])],
            'component_code'    => ['required', Rule::in(PayrollSetupService::RECURRING_CODES[$kind] ?? [])],
            'label'             => ['required', 'string', 'max:100'],
            'amount'            => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'frequency'         => ['required', Rule::in([StaffRecurringItem::FREQ_PER_CUTOFF, StaffRecurringItem::FREQ_PER_DAY_WORKED, StaffRecurringItem::FREQ_SECOND_CUTOFF_ONLY])],
            'start_date'        => ['required', 'date_format:Y-m-d'],
            'end_date'          => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            // Labor Code Art. 113 — written authorization for deductions (locked model table 2).
            'authorization_ref' => [Rule::requiredIf($kind === StaffRecurringItem::KIND_DEDUCTION), 'nullable', 'string', 'max:255'],
        ], function (array $data) use ($staff) {
            $item = $this->setup->createRecurring($staff, $data, Auth::user());

            return $this->toStaff($staff, 'recurring')->with('success', "Recurring {$item->kind} “{$item->label}” added.");
        }, [
            'amount.regex'                  => 'Enter an amount in pesos, e.g. 500 or 500.00.',
            'amount.not_regex'              => 'The amount must be more than zero.',
            'authorization_ref.required'    => 'Deductions need the employee’s written authorization reference (Labor Code Art. 113).',
            'component_code.in'             => 'Choose a component that matches the item type.',
        ]);
    }

    public function stopRecurring(Request $request, Staff $staff, StaffRecurringItem $item)
    {
        $this->assertOwned($staff, $this->currentSpa());
        abort_unless((int) $item->staff_id === (int) $staff->id, 404);

        return $this->handleForm($request, 'recurringStop', [
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$item->start_date->toDateString()],
        ], function (array $data) use ($staff, $item) {
            $this->setup->stopRecurring($item, $data['end_date']);

            return $this->toStaff($staff, 'recurring')->with('success', "“{$item->label}” stops after {$data['end_date']}.");
        }, ['end_date.after_or_equal' => 'The stop date cannot be before the item\'s start date.']);
    }

    public function destroyRecurring(Request $request, Staff $staff, StaffRecurringItem $item)
    {
        $this->assertOwned($staff, $this->currentSpa());
        abort_unless((int) $item->staff_id === (int) $staff->id, 404);

        return $this->handleForm($request, 'recurringDelete', [], function () use ($staff, $item) {
            $label = $item->label;
            $this->setup->deleteRecurring($item);

            return $this->toStaff($staff, 'recurring')->with('success', "“{$label}” deleted.");
        });
    }

    // =====================================================================
    // Internals
    // =====================================================================

    private function assertOwned(Staff $staff, Spa $spa): void
    {
        abort_unless((int) $staff->spa_id === (int) $spa->id, 404);
    }

    private function toStaff(Staff $staff, string $section)
    {
        return redirect()->to(route('payroll.staff.show', $staff).'#'.$section);
    }

    /** @return array<string, mixed> */
    private function profileRules(Request $request, bool $isEdit = false): array
    {
        return [
            'effective_from'     => ['required', 'date_format:Y-m-d'],
            'effective_to'       => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'pay_basis'          => ['required', Rule::in([StaffPayProfile::BASIS_DAILY, StaffPayProfile::BASIS_MONTHLY])],
            // DECIMAL(12,2); 0 allowed (commission + minimum-wage top-up — locked model table 1).
            'base_rate'          => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'commission_enabled' => ['sometimes', 'boolean'],
            'rest_days'          => ['required', 'array'],
            'rest_days.*'        => ['string', 'distinct', Rule::in(StaffPayProfile::DAY_NAMES)],
        ];
    }

    /**
     * Daily-paid: exactly 1 or 2 rest days (v3.1 — only those have a DOLE factor, rates
     * sheet §10). Monthly-paid: at least 1 (Labor Code Art. 91 weekly rest day, cited in
     * rates sheet §10) and not all 7.
     */
    private function restDayCheck(Request $request): callable
    {
        return function ($v) use ($request) {
            $n = count(array_unique((array) $request->input('rest_days', [])));
            if ($request->input('pay_basis') === StaffPayProfile::BASIS_DAILY && ! in_array($n, [1, 2], true)) {
                $v->errors()->add('rest_days', 'Daily-paid staff need 1 or 2 rest days a week — only those have a DOLE days-per-year factor (313 or 261). Other counts are skipped by payroll.');
            }
            if ($request->input('pay_basis') === StaffPayProfile::BASIS_MONTHLY && ($n < 1 || $n > 6)) {
                $v->errors()->add('rest_days', 'Choose at least one weekly rest day (Labor Code Art. 91).');
            }
        };
    }

    /** Non-blocking notices after saving a profile. */
    private function profileWarnings(Staff $staff, StaffPayProfile $profile): ?string
    {
        $home = $staff->branch_id ? Branch::withTrashed()->find($staff->branch_id) : null;
        $w = [];

        $check = $this->setup->minimumWageCheck($profile, $home);
        if ($check['below']) {
            $w[] = "Daily rate ₱{$check['daily']} is below {$home->name}'s minimum wage of ₱{$check['min']} — payroll will add a minimum-wage top-up for each worked day.";
        }
        if (! Decimal::isPositive(Decimal::fromDb($profile->base_rate)) && ! $profile->commission_enabled) {
            $w[] = 'Base rate is 0 and commission is off — this staff member will be paid the minimum-wage top-up only.';
        }
        $last = $this->setup->lastFinalizedPeriodEnd($staff);
        if ($last !== null && $profile->effective_from->toDateString() <= $last) {
            $w[] = "Payroll is already finalized through {$last}; finalized runs are not recomputed. Correct past pay with an adjustment line in a later run.";
        }

        return $w === [] ? null : implode(' ', $w);
    }

    /** @return array<string, 'set'|'missing'|'unreadable'> */
    private function idState(Staff $staff): array
    {
        $out = [];
        foreach (array_keys(self::ID_FIELDS) as $attr) {
            try {
                $out[$attr] = blank($staff->{$attr}) ? 'missing' : 'set';
            } catch (DecryptException) {
                $out[$attr] = 'unreadable';
            }
        }

        return $out;
    }

    /**
     * Non-blocking hints (never reject a save):
     *  - digit count differs from the commonly published format — UNVERIFIED: no
     *    agency text read directly; TIN 9 digits + 3- or 5-digit branch code
     *    (5-digit codes: RMC 36-2026 per secondary sources), SSS 10, PhilHealth 12,
     *    Pag-IBIG MID 12;
     *  - the same number is on file for another active staff member of this spa
     *    (almost always a typing error, and it would misdirect remittances).
     *
     * @return list<string>
     */
    private function statutoryIdWarnings(Staff $staff, Spa $spa): array
    {
        $expected = ['tin' => [9, 12, 14], 'sss_no' => [10], 'philhealth_no' => [12], 'pagibig_no' => [12]];
        $mine = [];
        $w = [];

        foreach ($expected as $attr => $lengths) {
            try {
                $digits = preg_replace('/\D/', '', (string) $staff->{$attr}) ?? '';
            } catch (DecryptException) {
                continue;
            }
            if ($digits === '') {
                continue;
            }
            $mine[$attr] = $digits;
            if (! in_array(strlen($digits), $lengths, true)) {
                $w[] = sprintf('%s has %d digits; the usual format has %s — please double-check.',
                    self::ID_FIELDS[$attr], strlen($digits), implode(' or ', $lengths));
            }
        }

        if ($mine !== []) {
            $others = Staff::query()->where('spa_id', $spa->id)->whereKeyNot($staff->id)
                ->where('employment_status', 'active')->get();
            foreach ($others as $o) {
                foreach ($mine as $attr => $digits) {
                    try {
                        $theirs = preg_replace('/\D/', '', (string) $o->{$attr}) ?? '';
                    } catch (DecryptException) {
                        continue;
                    }
                    if ($theirs !== '' && $theirs === $digits) {
                        $w[] = sprintf('The same %s number is on file for %s.', self::ID_FIELDS[$attr], $this->displayName($o));
                    }
                }
            }
        }

        return $w;
    }

    private function mask(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        return strlen($digits) > 4 ? '•••• '.substr($digits, -4) : '••••';
    }

    private function displayName(Staff $s): string
    {
        $u = $s->user;
        $name = trim(($u?->first_name ?? '').' '.($u?->middle_name ? $u->middle_name.' ' : '').($u?->last_name ?? ''));

        return $name !== '' ? preg_replace('/\s+/', ' ', $name) : "Staff #{$s->id}";
    }
}