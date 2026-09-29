<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Exceptions\PayrollSetupException;
use App\Models\Branch;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Models\Spa;
use App\Models\Staff;
use App\Models\StaffPayProfile;
use App\Models\User;
use App\Services\Payroll\Display\PayrollDisplay;
use App\Services\Payroll\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Unit 6 read side: the queries and sums behind the payroll pages. Read-only — every
 * write goes through PayrollRunService (Unit 4). Sums use Decimal (bcmath) and return
 * display strings, so controllers stay thin and Blade does no arithmetic.
 */
final class PayrollRunReadModel
{
    private const PAYABLE = [PayrollRun::STATUS_FINALIZED, PayrollRun::STATUS_RELEASED];

    public function __construct(private readonly PayrollRunService $runs)
    {
    }

    // =====================================================================
    // Runs index
    // =====================================================================

    /** @return list<int> years that have runs, plus the current year, newest first */
    public function yearOptions(Spa $spa): array
    {
        $years = PayrollRun::query()->where('spa_id', $spa->id)->pluck('period_start')
            ->map(fn ($d) => (int) Carbon::parse($d)->format('Y'))
            ->push((int) now()->format('Y'))
            ->unique()->sortDesc()->values()->all();

        return $years;
    }

    /** @return list<array<string, mixed>> */
    public function runsIndex(Spa $spa, int $year, ?string $type, ?string $status): array
    {
        return PayrollRun::query()
            ->where('spa_id', $spa->id)
            ->whereBetween('period_start', ["{$year}-01-01", "{$year}-12-31"])
            ->when($type, fn ($q) => $q->where('run_type', $type))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->withCount('payslips')
            ->withSum('payslips', 'gross_pay')
            ->withSum('payslips', 'net_pay')
            ->orderByDesc('period_start')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PayrollRun $r) => [
                'id'       => (int) $r->id,
                'type'     => PayrollDisplay::TYPE[$r->run_type] ?? $r->run_type,
                'period'   => PayrollDisplay::periodLabel($r),
                'cutoff'   => PayrollDisplay::cutoffLabel($r),
                'pay_date' => $r->pay_date->format('M j, Y'),
                'status'   => $r->status,
                'staff'    => (int) $r->payslips_count,
                'gross'    => PayrollDisplay::peso($r->payslips_sum_gross_pay ?? '0'),
                'net'      => PayrollDisplay::peso($r->payslips_sum_net_pay ?? '0'),
            ])
            ->all();
    }

    /** @return array{needs_action: int, gross: string, net: string, runs: int} */
    public function indexSummary(Spa $spa, int $year): array
    {
        $base = PayrollRun::query()->where('spa_id', $spa->id)->whereBetween('period_start', ["{$year}-01-01", "{$year}-12-31"]);

        $sums = DB::table('payslips')
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payslips.payroll_run_id')
            ->where('payroll_runs.spa_id', $spa->id)
            ->whereIn('payroll_runs.status', self::PAYABLE)
            ->whereBetween('payroll_runs.period_start', ["{$year}-01-01", "{$year}-12-31"])
            ->selectRaw('COALESCE(SUM(payslips.gross_pay),0) as g, COALESCE(SUM(payslips.net_pay),0) as n')
            ->first();

        return [
            'runs'         => (clone $base)->count(),
            'needs_action' => (clone $base)->whereIn('status', [PayrollRun::STATUS_DRAFT, PayrollRun::STATUS_APPROVED, PayrollRun::STATUS_FINALIZED])->count(),
            'gross'        => PayrollDisplay::peso($sums->g),
            'net'          => PayrollDisplay::peso($sums->n),
        ];
    }

    /**
     * Setup gaps that stop staff from being paid (locked eligibility rules; Unit 4
     * regularEligibility). "Missing profile" is judged on today's date — a run checks
     * its own period, so this is a heads-up, not the run's verdict.
     *
     * @return array{suite_branches: int, branches_missing_wage: list<string>, staff_missing_profile: int}
     */
    public function setupGaps(Spa $spa): array
    {
        $suite = Branch::query()->where('spa_id', $spa->id)->where('has_workforce_finance_suite', true)->get(['id', 'name', 'min_daily_wage']);

        $staffIds = Staff::query()
            ->where('spa_id', $spa->id)
            ->where('employment_status', 'active')
            ->whereIn('branch_id', $suite->pluck('id'))
            ->pluck('id');

        $withProfile = StaffPayProfile::query()->whereIn('staff_id', $staffIds)->effectiveOn(now()->toDateString())
            ->distinct()->pluck('staff_id');

        return [
            'suite_branches'        => $suite->count(),
            'branches_missing_wage' => $suite->whereNull('min_daily_wage')->pluck('name')->values()->all(),
            'staff_missing_profile' => $staffIds->diff($withProfile)->count(),
        ];
    }

    // =====================================================================
    // "New run" preview (uses the engine's own period builder)
    // =====================================================================

    /**
     * @return array{ok: bool, blocking: ?string, notes: list<string>, period: ?array, existing: ?array}
     */
    public function previewRegular(Spa $spa, int $year, int $month, int $cutoff): array
    {
        $out = ['ok' => false, 'blocking' => null, 'notes' => [], 'period' => null, 'existing' => null];
        $monthStart = Carbon::create($year, $month, 1)->toDateString();
        $monthEnd = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
        $label = Carbon::create($year, $month, 1)->format('F Y');

        $inMonth = fn (int $c) => PayrollRun::query()->where('spa_id', $spa->id)
            ->where('run_type', PayrollRun::TYPE_REGULAR)->where('cutoff_no', $c)
            ->whereBetween('period_start', [$monthStart, $monthEnd])->first();

        $override = null;
        if ($cutoff === 2) {
            $c1 = $inMonth(1);
            if ($c1 === null || ! in_array($c1->status, self::PAYABLE, true)) {
                $out['blocking'] = "Cutoff 1 of {$label} must be finalized first"
                    .($c1 ? ' (it is '.strtolower(PayrollDisplay::STATUS[$c1->status]['label']).').' : ' (it has not been generated).');

                return $out;
            }
            // Same rule as PayrollRunService::generateRegular: cutoff 2 starts the day after cutoff 1 ended.
            $override = $c1->period_end->copy()->addDay()->toDateString();
        }

        try {
            $p = $this->runs->buildPeriod($spa, $year, $month, $cutoff, $override);
        } catch (PayrollSetupException|InvalidArgumentException $e) {
            $out['blocking'] = $e->getMessage();

            return $out;
        }

        $out['period'] = [
            'start' => Carbon::parse($p->start)->format('M j, Y'),
            'end'   => Carbon::parse($p->end)->format('M j, Y'),
            'pay'   => Carbon::parse($p->payDate)->format('M j, Y'),
            'days'  => $p->lengthInDays(),
        ];

        $existing = $inMonth($cutoff);
        if ($existing !== null) {
            $out['existing'] = ['id' => (int) $existing->id, 'status' => $existing->status, 'label' => PayrollDisplay::STATUS[$existing->status]['label']];
            if (! $existing->isDraft()) {
                $out['blocking'] = 'This cutoff already has a '.strtolower(PayrollDisplay::STATUS[$existing->status]['label']).' run. Open it instead — corrections go into a later run as adjustments.';

                return $out;
            }
            $out['notes'][] = 'A draft already exists for this cutoff. Generating again rebuilds it; manual adjustment lines are kept.';
        }
        if ($p->end > now()->toDateString()) {
            $out['notes'][] = 'This period has not ended yet — attendance and bookings may be incomplete. You can generate now and regenerate later.';
        }
        if ($cutoff === 2) {
            $out['notes'][] = 'Cutoff 2 also computes the full month\'s SSS, PhilHealth and Pag-IBIG.';
        }

        $out['ok'] = true;

        return $out;
    }

    /** @return array{ok: bool, blocking: ?string, notes: list<string>, period: ?array, existing: ?array} */
    public function previewThirteenth(Spa $spa, int $year, string $payDate): array
    {
        $out = ['ok' => false, 'blocking' => null, 'notes' => [], 'period' => null, 'existing' => null];

        if ($payDate < "{$year}-01-01") {
            $out['blocking'] = "The pay date cannot be before {$year}.";

            return $out;
        }

        $out['period'] = [
            'start' => "Jan 1, {$year}",
            'end'   => "Dec 31, {$year}",
            'pay'   => Carbon::parse($payDate)->format('M j, Y'),
            'days'  => null,
        ];

        $existing = PayrollRun::query()->where('spa_id', $spa->id)->where('run_type', PayrollRun::TYPE_THIRTEENTH_MONTH)
            ->whereDate('period_start', "{$year}-01-01")->first();
        if ($existing !== null) {
            $out['existing'] = ['id' => (int) $existing->id, 'status' => $existing->status, 'label' => PayrollDisplay::STATUS[$existing->status]['label']];
            if (! $existing->isDraft()) {
                $out['blocking'] = "The {$year} 13th-month run is already ".strtolower(PayrollDisplay::STATUS[$existing->status]['label']).'. Open it instead.';

                return $out;
            }
            $out['notes'][] = 'A draft already exists for this year. Generating again rebuilds it; manual adjustment lines are kept.';
        }

        $deadline = $year.'-'.config('payroll.payment_timing.thirteenth_month_deadline');
        if ($payDate > $deadline) {
            $out['notes'][] = 'This pay date is after '.Carbon::parse($deadline)->format('F j').'. PD 851 and DOLE Labor Advisory 16-25 require 13th-month pay on or before December 24.';
        }
        $out['notes'][] = 'The amount is 1/12 of basic pay and commission in FINALIZED regular runs of the year. Unfinalized cutoffs are left out; pay any difference later as a non-taxable adjustment.';

        $out['ok'] = true;

        return $out;
    }

    // =====================================================================
    // Run page
    // =====================================================================

    /** @return array<string, mixed> header data: steps with actor names and times */
    public function runHeader(PayrollRun $run, ?array $cached): array
    {
        $ids = array_filter([$run->generated_by, $run->approved_by, $run->finalized_by, $run->released_by, $cached['generated_by'] ?? null, $cached['reviewed_by'] ?? null]);
        $names = $this->userNames($ids);
        $who = fn ($id) => $id ? ($names[(int) $id] ?? "User #{$id}") : null;
        $when = fn ($t) => $t ? Carbon::parse($t)->timezone(config('app.timezone'))->format('M j, Y g:i A') : null;

        return [
            'title'          => (PayrollDisplay::TYPE[$run->run_type] ?? '').' run · '.PayrollDisplay::periodLabel($run),
            'period'         => $run->period_start->format('M j, Y').' – '.$run->period_end->format('M j, Y'),
            'cutoff'         => PayrollDisplay::cutoffLabel($run),
            'pay_date'       => $run->pay_date->format('M j, Y'),
            'config_version' => $run->config_version,
            'config_current' => $run->config_version === (string) config('payroll.version'),
            'current_config' => (string) config('payroll.version'),
            'self_approved'  => $run->approved_by !== null && (int) $run->approved_by === (int) $run->generated_by,
            'reviewed'       => ! empty($cached['reviewed_by']) ? [
                'by'    => $who($cached['reviewed_by']),
                'at'    => $when($cached['reviewed_at'] ?? null),
                'count' => (int) ($cached['reviewed_count'] ?? 0),
            ] : null,
            'steps'          => [
                // generated_by is overwritten on every regeneration; created_at is the first generation.
                ['key' => 'generated', 'label' => 'Generated', 'by' => $who($run->generated_by),
                 'at' => $when($cached['generated_at'] ?? $run->created_at), 'done' => true],
                ['key' => 'approved',  'label' => 'Approved',  'by' => $who($run->approved_by),  'at' => $when($run->approved_at),  'done' => $run->approved_at !== null],
                ['key' => 'finalized', 'label' => 'Finalized', 'by' => $who($run->finalized_by), 'at' => $when($run->finalized_at), 'done' => $run->finalized_at !== null],
                ['key' => 'released',  'label' => 'Released',  'by' => $who($run->released_by),  'at' => $when($run->released_at),  'done' => $run->released_at !== null],
            ],
        ];
    }

    /**
     * Per-staff register with expandable lines, filtered by home branch.
     *
     * @return array{rows: list<array<string, mixed>>, totals: array<string, string>, count: int, branches: array<int, string>}
     */
    public function register(PayrollRun $run, ?int $branchId): array
    {
        $payslips = Payslip::query()->where('payroll_run_id', $run->id)->with('lines')->get();

        $staff = Staff::withTrashed()->with('user')->whereIn('id', $payslips->pluck('staff_id'))->get()->keyBy('id');
        $branchIds = $payslips->pluck('home_branch_id')
            ->merge($payslips->flatMap(fn (Payslip $p) => $p->lines->pluck('branch_id')))
            ->filter()->unique();
        $branchNames = Branch::withTrashed()->whereIn('id', $branchIds)->pluck('name', 'id')->all();

        $lines = $payslips->flatMap(fn (Payslip $p) => $p->lines);
        $bookings = $this->bookings($lines);
        $creators = $this->userNames($lines->pluck('created_by')->filter()->all());

        $homeOptions = $payslips->pluck('home_branch_id')->unique()
            ->mapWithKeys(fn ($id) => [(int) $id => $branchNames[$id] ?? "Branch #{$id}"])
            ->sort()->all();

        $filtered = $branchId ? $payslips->where('home_branch_id', $branchId) : $payslips;
        $zero = ['gross' => '0', 'ee' => '0', 'wtax' => '0', 'other' => '0', 'net' => '0', 'er' => '0', 'days' => '0'];
        $sum = $zero;
        $rows = [];

        foreach ($filtered as $p) {
            $s = $staff->get($p->staff_id);
            $ee = $this->sumLines($p->lines, fn (PayslipLine $l) => in_array($l->component_code, PayrollDisplay::EMPLOYEE_CONTRIBUTION_CODES, true));
            $wtax = $this->sumLines($p->lines, fn (PayslipLine $l) => $l->component_code === 'WTAX');
            $er = $this->sumLines($p->lines, fn (PayslipLine $l) => $l->kind === PayslipLine::KIND_EMPLOYER_SHARE);
            $other = Decimal::sub(Decimal::sub(Decimal::fromDb($p->total_deductions), $ee), $wtax);

            $vals = ['gross' => Decimal::fromDb($p->gross_pay), 'ee' => $ee, 'wtax' => $wtax, 'other' => $other,
                     'net' => Decimal::fromDb($p->net_pay), 'er' => $er, 'days' => Decimal::fromDb($p->days_worked)];
            foreach ($vals as $k => $v) {
                $sum[$k] = Decimal::add($sum[$k], $v);
            }

            $rank = PayrollDisplay::componentRank();
            $sorted = $p->lines->sortBy(fn (PayslipLine $l) => sprintf('%03d-%012d', $rank[$l->component_code] ?? 999, $l->id));
            $groups = [];
            foreach (array_keys(PayrollDisplay::KIND) as $kind) {
                $groups[$kind] = $sorted->where('kind', $kind)
                    ->map(fn (PayslipLine $l) => $this->lineRow($l, $p, $run, $branchNames, $bookings, $creators))
                    ->values()->all();
            }

            $rows[] = [
                'id'        => (int) $p->id,
                'staff_id'  => (int) $p->staff_id,
                'name'      => $this->staffName($s, (int) $p->staff_id),
                'branch'    => $branchNames[$p->home_branch_id] ?? "Branch #{$p->home_branch_id}",
                'days'      => $run->run_type === PayrollRun::TYPE_THIRTEENTH_MONTH ? '—' : PayrollDisplay::qty($p->days_worked),
                'gross'     => PayrollDisplay::peso($vals['gross']),
                'ee'        => PayrollDisplay::peso($ee),
                'wtax'      => PayrollDisplay::peso($wtax),
                'other'     => PayrollDisplay::peso($other),
                'net'       => PayrollDisplay::peso($vals['net']),
                'er'        => PayrollDisplay::peso($er),
                'is_mwe'    => (bool) $p->is_mwe,
                'orphan'    => ! (bool) ($p->snapshot['eligibility']['eligible'] ?? true),
                'short'     => $p->lines->contains(fn (PayslipLine $l) => PayrollDisplay::isShort($l)),
                'manual'    => $p->lines->where('is_manual', true)->count(),
                'groups'    => $groups,
                'sort'      => mb_strtolower($this->staffName($s, (int) $p->staff_id)),
            ];
        }

        usort($rows, fn ($a, $b) => [$a['sort'], $a['staff_id']] <=> [$b['sort'], $b['staff_id']]);

        return [
            'rows'     => $rows,
            'count'    => count($rows),
            'branches' => $homeOptions,
            'totals'   => [
                'gross' => PayrollDisplay::peso($sum['gross']),
                'ee'    => PayrollDisplay::peso($sum['ee']),
                'wtax'  => PayrollDisplay::peso($sum['wtax']),
                'other' => PayrollDisplay::peso($sum['other']),
                'net'   => PayrollDisplay::peso($sum['net']),
                'er'    => PayrollDisplay::peso($sum['er']),
                'days'  => PayrollDisplay::qty($sum['days']),
            ],
        ];
    }

    /**
     * Unit 4 monthlyContributionSummary, formatted. Only for regular cutoff-2 runs.
     *
     * @return array<string, mixed>|null
     */
    public function contributionSummary(Spa $spa, PayrollRun $run, ?int $branchId): ?array
    {
        if ($run->run_type !== PayrollRun::TYPE_REGULAR || (int) $run->cutoff_no !== 2) {
            return null;
        }

        $year = (int) $run->period_start->format('Y');
        $month = (int) $run->period_start->format('n');
        $s = $this->runs->monthlyContributionSummary($spa, $year, $month, $branchId);

        $fmt = function (array $r): array {
            $out = [];
            foreach (PayrollRunService::SUMMARY_CODES as $code) {
                $out[$code] = PayrollDisplay::peso($r[$code]);
                if (array_key_exists($code.'_withheld', $r)) {
                    $out[$code.'_withheld'] = PayrollDisplay::peso($r[$code.'_withheld']);
                    $out[$code.'_short'] = Decimal::cmp(Decimal::of((string) $r[$code.'_withheld']), Decimal::of((string) $r[$code])) < 0;
                }
            }

            return $out;
        };

        $runLabel = fn (array $r) => ($r['run_type'] ?? PayrollRun::TYPE_REGULAR) === PayrollRun::TYPE_THIRTEENTH_MONTH
            ? '13th month'
            : 'Cutoff '.$r['cutoff_no'].(isset($r['pay_date']) ? ' (paid '.Carbon::parse($r['pay_date'])->format('M j').')' : '');

        return [
            'month_label'       => Carbon::create($year, $month, 1)->format('F Y'),
            'complete'          => $s['complete'],
            'contribution_runs' => array_map(fn ($r) => ['label' => $runLabel($r), 'status' => PayrollDisplay::STATUS[$r['status']]['label'] ?? $r['status'], 'included' => $r['included']], $s['contribution_runs']),
            'wtax_runs'         => array_map(fn ($r) => ['label' => $runLabel($r), 'status' => PayrollDisplay::STATUS[$r['status']]['label'] ?? $r['status'], 'included' => $r['included']], $s['wtax_runs']),
            'rows'              => array_map(fn ($r) => ['name' => $r['name']] + $fmt($r), $s['rows']),
            'totals'            => $fmt($s['totals']),
        ];
    }

    // =====================================================================
    // Payslip pages
    // =====================================================================

    /** @return array{employee: string, position: ?string, branch: string, location: ?string, spa: string} */
    public function payslipParties(Payslip $payslip, PayrollRun $run): array
    {
        $staff = Staff::withTrashed()->with('user')->find($payslip->staff_id);
        $branch = Branch::withTrashed()->find($payslip->home_branch_id);
        $spa = Spa::query()->find($run->spa_id);

        return [
            'employee' => $this->staffName($staff, (int) $payslip->staff_id),
            'position' => $this->position($staff),
            'branch'   => $branch?->name ?? "Branch #{$payslip->home_branch_id}",
            'location' => $branch?->location,
            'spa'      => $spa?->name ?? '',
        ];
    }

    /**
     * Every payslip of a run as employee copies, ordered by name, for the batch print
     * (optionally one home branch).
     *
     * @return list<array<string, mixed>>
     */
    public function payslipDocuments(PayrollRun $run, ?int $branchId): array
    {
        $payslips = Payslip::query()->where('payroll_run_id', $run->id)
            ->when($branchId, fn ($q) => $q->where('home_branch_id', $branchId))
            ->with('lines')->get();

        $staff = Staff::withTrashed()->with('user')->whereIn('id', $payslips->pluck('staff_id'))->get()->keyBy('id');
        $branches = Branch::withTrashed()->whereIn('id', $payslips->pluck('home_branch_id'))->get()->keyBy('id');
        $spaName = (string) (Spa::query()->whereKey($run->spa_id)->value('name') ?? '');
        $bookings = $this->bookings($payslips->flatMap(fn (Payslip $p) => $p->lines));

        return $payslips
            ->map(function (Payslip $p) use ($run, $staff, $branches, $spaName, $bookings) {
                $s = $staff->get($p->staff_id);
                $b = $branches->get($p->home_branch_id);

                return \App\Services\Payroll\Display\PayslipDocument::build($p, $run, [
                    'spa'      => $spaName,
                    'employee' => $this->staffName($s, (int) $p->staff_id),
                    'position' => $this->position($s),
                    'branch'   => $b?->name ?? "Branch #{$p->home_branch_id}",
                    'location' => $b?->location,
                ], $bookings);
            })
            ->sortBy(fn (array $d) => mb_strtolower($d['employee']))
            ->values()->all();
    }

    // =====================================================================
    // My Payslips (identity-based)
    // =====================================================================

    /**
     * Staff records of this user in their spa. Includes inactive and soft-deleted
     * records so a former or re-hired employee still sees payslips already released
     * to them (see delivery notes).
     *
     * @return list<int>
     */
    public function staffIdsFor(User $user): array
    {
        if (! $user->spa_id) {
            return [];
        }

        return Staff::withTrashed()->where('user_id', $user->id)->where('spa_id', $user->spa_id)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** Released payslips of these staff ids in the spa; year = pay-date year. */
    private function releasedQuery(User $user, array $staffIds, ?int $year)
    {
        return Payslip::query()
            ->select('payslips.*')
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payslips.payroll_run_id')
            ->whereIn('payslips.staff_id', $staffIds === [] ? [0] : $staffIds)
            ->where('payroll_runs.spa_id', $user->spa_id)
            ->where('payroll_runs.status', PayrollRun::STATUS_RELEASED)
            ->when($year, fn ($q) => $q->whereBetween('payroll_runs.pay_date', ["{$year}-01-01", "{$year}-12-31"]));
    }

    /** @return list<int> */
    public function myYears(User $user, array $staffIds): array
    {
        return $this->releasedQuery($user, $staffIds, null)
            ->toBase()->select('payroll_runs.pay_date as run_pay_date')
            ->pluck('run_pay_date')
            ->map(fn ($d) => (int) Carbon::parse($d)->format('Y'))
            ->push((int) now()->format('Y'))
            ->unique()->sortDesc()->values()->all();
    }

    /** @return list<array<string, mixed>> */
    public function myPayslips(User $user, array $staffIds, int $year): array
    {
        return $this->releasedQuery($user, $staffIds, $year)
            ->with('payrollRun')
            ->orderByDesc('payroll_runs.pay_date')
            ->orderByDesc('payslips.id')
            ->get()
            ->map(fn (Payslip $p) => [
                'id'       => (int) $p->id,
                'type'     => PayrollDisplay::TYPE[$p->payrollRun->run_type] ?? '',
                'period'   => PayrollDisplay::periodLabel($p->payrollRun),
                'cutoff'   => PayrollDisplay::cutoffLabel($p->payrollRun),
                'pay_date' => $p->payrollRun->pay_date->format('M j, Y'),
                'gross'    => PayrollDisplay::peso($p->gross_pay),
                'deductions' => PayrollDisplay::peso($p->total_deductions),
                'net'      => PayrollDisplay::peso($p->net_pay),
            ])
            ->all();
    }

    /** Year-to-date (pay-date year) from released payslips: gross, WTAX withheld, employee contributions withheld. */
    public function myYearToDate(User $user, array $staffIds, int $year): array
    {
        $ids = $this->releasedQuery($user, $staffIds, $year)->toBase()->select('payslips.id as payslip_id')->pluck('payslip_id');

        $gross = Payslip::query()->whereIn('id', $ids)->sum('gross_pay');
        $by = DB::table('payslip_lines')
            ->whereIn('payslip_id', $ids->isEmpty() ? [0] : $ids)
            ->whereIn('component_code', [...PayrollDisplay::EMPLOYEE_CONTRIBUTION_CODES, 'WTAX'])
            ->groupBy('component_code')
            ->get(['component_code', DB::raw('SUM(amount) as total')])
            ->pluck('total', 'component_code');

        $ee = '0';
        foreach (PayrollDisplay::EMPLOYEE_CONTRIBUTION_CODES as $c) {
            $ee = Decimal::add($ee, Decimal::fromDb($by[$c] ?? '0'));
        }

        return [
            'count' => $ids->count(),
            'gross' => PayrollDisplay::peso($gross ?? '0'),
            'wtax'  => PayrollDisplay::peso($by['WTAX'] ?? '0'),
            'ee'    => PayrollDisplay::peso($ee),
        ];
    }

    // =====================================================================
    // Internals
    // =====================================================================

    /**
     * @param array<int, string> $branchNames
     * @param array<int, array<string, mixed>> $bookings
     * @param array<int, string> $creators
     */
    private function lineRow(PayslipLine $l, Payslip $p, PayrollRun $run, array $branchNames, array $bookings, array $creators): array
    {
        $booking = null;
        if ($l->source_type === PayslipLine::SOURCE_BOOKING && $l->source_id !== null) {
            $b = $bookings[(int) $l->source_id] ?? null;
            $booking = [
                'id'       => (int) $l->source_id,
                'date'     => $b ? PayrollDisplay::date($b['appointment_date']) : null,
                'late'     => $b !== null && $b['appointment_date'] < $run->period_start->toDateString(),
                'customer' => $b['customer_name'] ?? null,
                'service'  => $b['service'] ?? null,
                'branch'   => $b && $b['branch_id'] ? ($branchNames[$b['branch_id']] ?? "Branch #{$b['branch_id']}") : null,
                'missing'  => $b === null,
            ];
        }

        return [
            'id'      => (int) $l->id,
            'code'    => $l->component_code,
            'label'   => $l->label,
            'detail'  => PayrollDisplay::lineDetail($l),
            'amount'  => PayrollDisplay::peso($l->amount),
            'note'    => $l->note,
            'short'   => PayrollDisplay::isShort($l),
            'manual'  => (bool) $l->is_manual,
            'by'      => $l->created_by ? ($creators[(int) $l->created_by] ?? "User #{$l->created_by}") : null,
            'branch'  => $l->branch_id && (int) $l->branch_id !== (int) $p->home_branch_id ? ($branchNames[$l->branch_id] ?? "Branch #{$l->branch_id}") : null,
            'booking' => $booking,
        ];
    }

    /**
     * Bookings referenced by commission lines, with the service name. Read straight
     * from the tables so the branch/session global scopes on Booking, Treatment and
     * Package do not hide other branches' records; soft-deleted rows are still shown
     * (they were paid).
     *
     * @param Collection<int, PayslipLine> $lines
     * @return array<int, array{appointment_date: string, customer_name: ?string, branch_id: ?int, service: ?string}>
     */
    public function bookings(Collection $lines): array
    {
        $ids = $lines->where('source_type', PayslipLine::SOURCE_BOOKING)->pluck('source_id')->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $rows = DB::table('bookings')->whereIn('id', $ids)->get(['id', 'appointment_date', 'customer_name', 'branch_id', 'treatment']);

        // bookings.treatment holds "treatment_<id>" or "package_<id>" (Booking::getTreatmentLabelAttribute).
        $want = ['treatment' => [], 'package' => []];
        foreach ($rows as $r) {
            if (preg_match('/^(treatment|package)_(\d+)$/', (string) $r->treatment, $m) === 1) {
                $want[$m[1]][] = (int) $m[2];
            }
        }
        $names = [
            'treatment' => $want['treatment'] ? DB::table('treatments')->whereIn('id', $want['treatment'])->pluck('name', 'id')->all() : [],
            'package'   => $want['package'] ? DB::table('packages')->whereIn('id', $want['package'])->pluck('name', 'id')->all() : [],
        ];

        return $rows->mapWithKeys(function ($b) use ($names) {
            $service = null;
            if (preg_match('/^(treatment|package)_(\d+)$/', (string) $b->treatment, $m) === 1) {
                $service = $names[$m[1]][(int) $m[2]] ?? null;
            } elseif (is_string($b->treatment) && $b->treatment !== '') {
                $service = $b->treatment;
            }

            return [(int) $b->id => [
                'appointment_date' => substr((string) $b->appointment_date, 0, 10),
                'customer_name'    => $b->customer_name,
                'branch_id'        => $b->branch_id !== null ? (int) $b->branch_id : null,
                'service'          => $service,
            ]];
        })->all();
    }

    /** @param Collection<int, PayslipLine> $lines */
    private function sumLines(Collection $lines, callable $filter): string
    {
        $sum = '0';
        foreach ($lines->filter($filter) as $l) {
            $sum = Decimal::add($sum, Decimal::fromDb($l->amount));
        }

        return $sum;
    }

    /** @return array<int, string> */
    private function userNames(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));
        if ($ids === []) {
            return [];
        }

        return User::query()->whereIn('id', $ids)->get()
            ->mapWithKeys(fn (User $u) => [(int) $u->id => trim((string) $u->name) ?: "User #{$u->id}"])
            ->all();
    }

    private function position(?Staff $s): ?string
    {
        $role = $s?->user?->getRoleNames()->first();

        return $role ? ucwords(str_replace(['_', '-'], ' ', $role)) : null;
    }

    private function staffName(?Staff $s, int $staffId): string
    {
        $name = $s?->user ? trim((string) $s->user->name) : '';

        return $name !== '' ? preg_replace('/\s+/', ' ', $name) : "Staff #{$staffId}";
    }
}
