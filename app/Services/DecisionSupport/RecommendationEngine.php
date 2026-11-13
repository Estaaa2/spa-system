<?php

declare(strict_types=1);

namespace App\Services\DecisionSupport;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\DssRecommendation;
use App\Models\Package;
use App\Models\Promo;
use App\Models\Spa;
use App\Models\Treatment;
use App\Services\Finance\BookingRevenue;
use App\Services\Navigation\NavBadges;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The DSS rules (DSS revamp, section 5). Each rule returns plain candidates with
 * the numbers behind them; sync() stores them by fingerprint.
 *
 *  R1 staffing      next 7 days: expected capacity use ≥ 85%, or ≥ 60 expected
 *                   therapist-minutes with nobody scheduled for them (3 most urgent days).
 *  R2 hire          capacity use ≥ 85% in at least 3 of the last 4 weeks
 *                   (plus the fully booked hours as evidence, Q1 B).
 *  R3 over-staffed  a coming day with ≥ 2 therapists and expected use ≤ 35% (max 2 shown).
 *  R4 promo         a treatment/package down ≥ 40% (last 28 days vs the 28 before),
 *                   at least 3 bookings before, no promo running on it today.
 *  R6 expansion     spa level, owner only: each of the last 3 complete months ≥ 10%
 *                   above the expected revenue (average of the 3 months before, Q3 A)
 *                   AND the branch meets R2.
 * Minimum data: R1/R2/R3 need 4 weeks of bookings, R4 needs 8, R6 needs 6 months.
 */
final class RecommendationEngine
{
    public const BUSY_USE      = 85.0;
    public const LIGHT_USE     = 35.0;
    public const SHORT_MINUTES = 60.0;
    public const DROP_PCT      = 40.0;
    public const DROP_MIN_PREV = 3;
    public const GROWTH_MARGIN = 10.0;

    public function __construct(
        private readonly DemandForecaster $forecaster,
        private readonly CapacityCalculator $capacity,
    ) {
    }

    // ── Store ───────────────────────────────────────────────────────────────

    /** Recompute every rule for the spa and store the result. Returns the open count. */
    public function sync(Spa $spa, ?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $candidates = $this->candidatesForSpa($spa, $today);
        $now = now();

        DB::transaction(function () use ($spa, $candidates, $now) {
            $existing = DssRecommendation::query()
                ->where('spa_id', $spa->id)
                ->whereIn('fingerprint', array_column($candidates, 'fingerprint'))
                ->get()
                ->keyBy('fingerprint');

            foreach ($candidates as $c) {
                $row = $existing[$c['fingerprint']] ?? null;
                $fields = [
                    'branch_id' => $c['branch_id'], 'scope' => $c['scope'], 'rule_key' => $c['rule_key'],
                    'priority' => $c['priority'], 'title' => $c['title'], 'message' => $c['message'],
                    'details' => $c['details'], 'action' => $c['action'], 'last_seen_at' => $now,
                ];

                if ($row === null) {
                    DssRecommendation::create($fields + [
                        'spa_id' => $spa->id, 'fingerprint' => $c['fingerprint'],
                        'status' => DssRecommendation::OPEN, 'first_seen_at' => $now,
                    ]);
                } elseif (in_array($row->status, [DssRecommendation::OPEN, DssRecommendation::EXPIRED], true)) {
                    // Numbers refresh; a decided row keeps the snapshot it was decided on.
                    $row->update($fields + ['status' => DssRecommendation::OPEN]);
                } else {
                    $row->update(['last_seen_at' => $now]);
                }
            }

            // Open items whose situation went away.
            DssRecommendation::query()
                ->where('spa_id', $spa->id)
                ->where('status', DssRecommendation::OPEN)
                ->whereNotIn('fingerprint', array_column($candidates, 'fingerprint') ?: ['-'])
                ->update(['status' => DssRecommendation::EXPIRED, 'updated_at' => $now]);
        });

        NavBadges::bump((int) $spa->id);

        return DssRecommendation::where('spa_id', $spa->id)->open()->count();
    }

    /** @return list<array> */
    public function candidatesForSpa(Spa $spa, CarbonImmutable $today): array
    {
        $out = [];
        $branches = Branch::withoutGlobalScopes()->where('spa_id', $spa->id)->whereNull('deleted_at')->orderBy('id')->get();

        foreach ($branches as $branch) {
            $spaId = (int) $spa->id;
            $branchId = (int) $branch->id;
            $weeks = $this->forecaster->weeksOfHistory($spaId, $branchId, $today);

            if ($weeks >= DemandForecaster::MIN_WEEKS) {
                $forecast = $this->forecaster->forecast($spaId, $branchId, $today);
                array_push($out, ...$this->r1Staffing($branch, $forecast));
                array_push($out, ...$this->r3Overstaffed($branch, $forecast, $today));

                $busy = $this->busyWeeks($spaId, $branchId, $today);
                if ($r2 = $this->r2Hire($branch, $busy, $today)) {
                    $out[] = $r2;
                    if ($r6 = $this->r6Expansion($spa, $branch, $busy, $today)) {
                        $out[] = $r6;
                    }
                }
            }
            if ($weeks >= 8) {
                array_push($out, ...$this->r4Promo($branch, $today));
            }
        }

        return $out;
    }

    // ── R1 / R3: the next 7 days ────────────────────────────────────────────

    /** @return list<array> */
    public function r1Staffing(Branch $branch, array $forecast): array
    {
        if (! $forecast['enough_data']) {
            return [];
        }
        $out = [];
        $rank = [];
        foreach ($forecast['days'] as $i => $d) {
            if (! $d['open'] || $d['expected'] <= 0) {
                continue;
            }
            $busy  = $d['use'] !== null && $d['use'] >= self::BUSY_USE;
            $short = $d['shortfall'] >= self::SHORT_MINUTES;
            if (! $busy && ! $short) {
                continue;
            }

            $when = CarbonImmutable::parse($d['date'])->format('l, M j');
            $nobody = $d['therapists'] === 0;
            $title = $nobody
                ? "No therapist scheduled on {$when}"
                : ($short ? "Add therapist time on {$when}" : "{$when} is nearly fully booked");

            $msg = "About {$this->num($d['expected_count'])} bookings expected ({$d['booked_count']} already booked), "
                .($nobody ? 'but nobody is scheduled to work.' : "with {$d['therapists']} ".($d['therapists'] === 1 ? 'therapist' : 'therapists').' scheduled. ')
                .($d['use'] !== null ? "Expected capacity use: {$this->num($d['use'])}%." : '');
            if ($short) {
                $msg .= ' Short at '.DemandForecaster::hourRanges($d['short_hours'])
                    .": about {$this->num($d['unserved'])} ".($d['unserved'] == 1 ? 'booking' : 'bookings').' may not get a therapist.';
            }

            $out[] = $this->make('r1_staffing', $branch, 'r1|'.$branch->id.'|'.$d['date'],
                ($d['unserved'] >= 2 || ($short && $i <= 1)) ? 'high' : 'medium',
                $title, trim($msg),
                [
                    'Scheduled therapist time: '.$this->hrs($d['scheduled'])." ({$d['therapists']} working, after rest days, approved leave and one-off changes).",
                    'Expected booked time: '.$this->hrs($d['expected']).", hour by hour the higher of what is already booked (".$this->hrs($d['booked']).") and the average for {$d['weekday']}s over the last {$forecast['weeks']} weeks.",
                    'Capacity use = expected booked time ÷ scheduled therapist time.',
                    'Shows when capacity use is at least 85%, or when at least 1 hour of expected therapist time has nobody scheduled for it.',
                    'Unserved bookings = short therapist-minutes ÷ average booking length ('.$this->num($forecast['avg_duration']).' min).',
                ],
                ['label' => 'Open staff schedules', 'route' => 'staff.index', 'params' => [], 'permission' => 'view staff'],
                ['date' => $d['date'], 'use' => $d['use'], 'shortfall' => $d['shortfall'], 'unserved' => $d['unserved']]
            );
            $rank[] = [$d['shortfall'], $d['use'] ?? 0, -$i];
        }

        // The 3 most urgent days (biggest shortfall, then highest use, then soonest), so a
        // branch that is busy every day gets a short list plus the hiring advice (R2).
        $keys = array_keys($out);
        usort($keys, fn ($a, $b) => $rank[$b] <=> $rank[$a]);

        return array_values(array_map(fn ($k) => $out[$k], array_slice($keys, 0, 3)));
    }

    /** @return list<array> at most 2, lightest first; only from tomorrow on */
    public function r3Overstaffed(Branch $branch, array $forecast, CarbonImmutable $today): array
    {
        if (! $forecast['enough_data']) {
            return [];
        }
        $light = collect($forecast['days'])
            ->filter(fn ($d) => $d['open'] && $d['date'] > $today->toDateString()
                && $d['therapists'] >= 2 && $d['use'] !== null && $d['use'] <= self::LIGHT_USE)
            ->sortBy('use')
            ->take(2);

        return $light->map(function ($d) use ($branch, $forecast) {
            $when = CarbonImmutable::parse($d['date'])->format('l, M j');

            return $this->make('r3_overstaffed', $branch, 'r3|'.$branch->id.'|'.$d['date'], 'low',
                "Light day ahead: {$when}",
                "About {$this->num($d['expected_count'])} bookings expected for {$d['therapists']} scheduled therapists (expected capacity use {$this->num($d['use'])}%). You could move a shift to a busier day or run a promo for that day.",
                [
                    'Scheduled therapist time: '.$this->hrs($d['scheduled']).'; expected booked time: '.$this->hrs($d['expected']).'.',
                    "Expected time uses the {$d['weekday']} average over the last {$forecast['weeks']} weeks, or what is already booked if that is higher.",
                    'Shows when at least 2 therapists are scheduled and capacity use is 35% or lower (the 2 lightest days at most).',
                ],
                ['label' => 'Open staff schedules', 'route' => 'staff.index', 'params' => [], 'permission' => 'view staff'],
                ['date' => $d['date'], 'use' => $d['use']]
            );
        })->values()->all();
    }

    // ── R2: hire ────────────────────────────────────────────────────────────

    /** Capacity use for each of the last 4 full weeks (oldest first) and the fully booked hours. */
    public function busyWeeks(int $spaId, int $branchId, CarbonImmutable $today): array
    {
        $from = $today->subDays(28);
        $rows = $this->capacity->range($spaId, $branchId, $from->toDateString(), $today->subDay()->toDateString());

        $weeks = [[0, 0], [0, 0], [0, 0], [0, 0]];
        $full = [];
        $fullCount = 0;
        $i = 0;
        foreach ($rows as $date => $row) {
            $w = intdiv($i++, 7);
            $weeks[$w][0] += $row['booked'];
            $weeks[$w][1] += $row['scheduled'];
            foreach ($row['hours'] as $h => $v) {
                if ($v['scheduled'] > 0 && $v['booked'] >= $v['scheduled']) {
                    $fullCount++;
                    $key = CarbonImmutable::parse($date)->format('D').' '.CarbonImmutable::today()->setTime($h, 0)->format('g A');
                    $full[$key] = ($full[$key] ?? 0) + 1;
                }
            }
        }
        arsort($full);

        $use = array_map(fn ($w) => $w[1] > 0 ? round($w[0] / $w[1] * 100, 1) : null, $weeks);

        return [
            'use'        => $use,
            'busy_weeks' => count(array_filter($use, fn ($u) => $u !== null && $u >= self::BUSY_USE)),
            'full_hours' => $fullCount,
            'top_full'   => array_slice($full, 0, 3, true),
        ];
    }

    public function r2Hire(Branch $branch, array $busy, CarbonImmutable $today): ?array
    {
        if ($busy['busy_weeks'] < 3) {
            return null;
        }
        $weekly = implode(', ', array_map(fn ($u) => $u === null ? 'n/a' : $this->num($u).'%', $busy['use']));
        $top = $busy['top_full']
            ? ' Most often: '.implode(', ', array_map(fn ($k, $n) => "{$k} ({$n}×)", array_keys($busy['top_full']), $busy['top_full'])).'.'
            : '';

        return $this->make('r2_hire', $branch, 'r2|'.$branch->id.'|'.$today->format('o-\WW'), 'high',
            'Consider hiring another therapist',
            "Therapists were at least 85% booked in {$busy['busy_weeks']} of the last 4 weeks (weekly use, oldest first: {$weekly}). "
                ."{$busy['full_hours']} hours were fully booked, with every scheduled therapist busy.{$top}",
            [
                'Weekly capacity use = booked therapist-minutes ÷ scheduled therapist-minutes for that week (cancelled bookings excluded; no-shows count, since the slot was held).',
                'Shows when weekly use is at least 85% in at least 3 of the last 4 weeks.',
                'A fully booked hour is a clock hour where booked minutes reached the scheduled minutes.',
                'For reference, Zenoti\'s 2026 salon benchmark puts the 90th percentile at about 79% staff utilization and the median at about 47% (US/Canada data).',
            ],
            ['label' => 'Create job opening', 'route' => 'job-openings.index',
                'params' => ['dss_prefill' => 1, 'role' => 'therapist', 'title' => 'Massage Therapist'],
                'permission' => 'create hiring', 'feature' => 'manpower'],
            ['weekly_use' => $busy['use'], 'busy_weeks' => $busy['busy_weeks'], 'full_hours' => $busy['full_hours']]
        );
    }

    // ── R4: treatment / package promo ───────────────────────────────────────

    /** @return list<array> at most 3, biggest drop first */
    public function r4Promo(Branch $branch, CarbonImmutable $today): array
    {
        $spaId = (int) $branch->spa_id;
        $recentFrom = $today->subDays(28)->toDateString();
        $prevFrom = $today->subDays(56)->toDateString();
        $prevTo = $today->subDays(29)->toDateString();

        $counts = fn (string $from, string $to) => Booking::query()
            ->where('spa_id', $spaId)->where('branch_id', $branch->id)
            ->whereBetween('appointment_date', [$from, $to])
            ->whereNotIn('status', CapacityCalculator::NOT_DEMAND)
            ->whereNotNull('treatment')
            ->groupBy('treatment')
            ->selectRaw('treatment, COUNT(*) as n')
            ->pluck('n', 'treatment')
            ->map(fn ($n) => (int) $n)
            ->all();

        $recent = $counts($recentFrom, $today->subDay()->toDateString());
        $prev = $counts($prevFrom, $prevTo);

        $promoted = Promo::withoutGlobalScope('spa_branch')
            ->where('spa_id', $spaId)->where('branch_id', $branch->id)
            ->activeToday()
            ->with(['treatments:id', 'packages:id'])
            ->get();
        $onPromo = [];
        foreach ($promoted as $p) {
            foreach ($p->treatments as $t) {
                $onPromo['treatment_'.$t->id] = true;
            }
            foreach ($p->packages as $pk) {
                $onPromo['package_'.$pk->id] = true;
            }
        }

        $treatments = Treatment::withoutGlobalScope('spa_branch')->where('spa_id', $spaId)->where('branch_id', $branch->id)
            ->get(['id', 'name', 'price'])->keyBy(fn ($t) => 'treatment_'.$t->id);
        $packages = Package::withoutGlobalScope('spa_branch')->where('spa_id', $spaId)->where('branch_id', $branch->id)
            ->get(['id', 'name', 'price'])->keyBy(fn ($p) => 'package_'.$p->id);
        $items = $treatments->union($packages);

        $drops = [];
        foreach ($prev as $code => $before) {
            $item = $items[$code] ?? null;
            if (! $item || $before < self::DROP_MIN_PREV || isset($onPromo[$code])) {
                continue;
            }
            $now = $recent[$code] ?? 0;
            $pct = round(($before - $now) / $before * 100, 1);
            if ($pct >= self::DROP_PCT) {
                $drops[] = compact('code', 'item', 'before', 'now', 'pct');
            }
        }
        usort($drops, fn ($a, $b) => $b['pct'] <=> $a['pct']);

        return array_map(function ($d) use ($branch, $today) {
            $isPackage = str_starts_with($d['code'], 'package_');
            $name = $d['item']->name;

            return $this->make('r4_promo', $branch, 'r4|'.$branch->id.'|'.$d['code'].'|'.$today->format('o-\WW'), 'medium',
                "Bookings for {$name} dropped {$this->num($d['pct'])}%",
                "{$d['now']} ".($d['now'] === 1 ? 'booking' : 'bookings')." in the last 28 days, down from {$d['before']} in the 28 days before. "
                    .'A limited-time promo could bring it back. Use the break-even calculator to see how many extra bookings a discount needs.',
                [
                    'Bookings counted by appointment date, cancelled ones excluded.',
                    'Shows when bookings fell by at least 40% and there were at least 3 bookings in the earlier 28 days.',
                    'Skipped when a promo is already running on it today.',
                ],
                ['label' => 'Create promo', 'route' => 'promos.index',
                    'params' => ['dss_prefill' => 1, ($isPackage ? 'package' : 'treatment') => $d['item']->id, 'name' => $name.' Promo'],
                    'permission' => 'create promos'],
                ['code' => $d['code'], 'name' => $name, 'price' => (float) $d['item']->price, 'before' => $d['before'], 'now' => $d['now'], 'drop_pct' => $d['pct']]
            );
        }, array_slice($drops, 0, 3));
    }

    // ── R6: expansion (spa level, owner only) ───────────────────────────────

    public function r6Expansion(Spa $spa, Branch $branch, array $busy, CarbonImmutable $today): ?array
    {
        $m1 = $today->startOfMonth()->subMonth();        // last complete month
        $months = [];
        for ($k = 5; $k >= 0; $k--) {
            $m = $m1->subMonths($k);
            $months[] = [
                'label'  => $m->format('M Y'),
                'amount' => BookingRevenue::serviceRevenue((int) $spa->id, (int) $branch->id, $m->toDateString(), $m->endOfMonth()->toDateString()),
            ];
        }

        $firstPaid = Booking::query()->where('spa_id', $spa->id)->where('branch_id', $branch->id)
            ->where('status', 'completed')->min('appointment_date');
        if (! $firstPaid || substr((string) $firstPaid, 0, 10) > $m1->subMonths(5)->endOfMonth()->toDateString()) {
            return null;   // fewer than 6 months of revenue
        }

        $baseline = round(array_sum(array_column(array_slice($months, 0, 3), 'amount')) / 3, 2);
        $recent = array_slice($months, 3, 3);
        $floor = $baseline * (1 + self::GROWTH_MARGIN / 100);
        if ($baseline <= 0 || collect($recent)->contains(fn ($m) => $m['amount'] < $floor)) {
            return null;
        }

        $limit = $spa->planLimit('branches');
        $count = Branch::withoutGlobalScopes()->where('spa_id', $spa->id)->whereNull('deleted_at')->count();
        $canAdd = $spa->canAddBranch();
        $peso = fn ($v) => '₱'.number_format((float) $v, 0);

        return $this->make('r6_expansion', $branch, 'r6|'.$branch->id.'|'.$m1->format('Y-m'), 'medium',
            "{$branch->name} is earning above expected: consider opening another branch",
            'Collected revenue was '.implode(', ', array_map(fn ($m) => $peso($m['amount']).' ('.$m['label'].')', $recent))
                .', each at least 10% above the expected '.$peso($baseline).' a month. '
                ."Therapists were also at least 85% booked in {$busy['busy_weeks']} of the last 4 weeks, so demand is outgrowing this branch. "
                .($canAdd ? "Your plan allows {$limit} branches and you have {$count}." : "Your plan allows {$limit} ".($limit === 1 ? 'branch' : 'branches').' and you have reached it, so another branch needs a plan upgrade.'),
            [
                'Expected revenue = the average collected revenue of the 3 months before ('.implode(', ', array_column(array_slice($months, 0, 3), 'label')).').',
                'Collected revenue = amount paid on completed bookings (the same rule as Reports and Revenue).',
                'Shows when each of the last 3 complete months is at least 10% above expected AND the branch meets the hiring rule (85% use in 3 of the last 4 weeks).',
                'Needs at least 6 months of completed bookings. Only the spa owner sees this.',
            ],
            $canAdd
                ? ['label' => 'Go to Branches', 'route' => 'branches.index', 'params' => [], 'permission' => null]
                : ['label' => 'View plans', 'route' => 'subscription.index', 'params' => [], 'permission' => null],
            ['months' => $months, 'baseline' => $baseline, 'branch_limit' => $limit, 'branches' => $count],
            DssRecommendation::SCOPE_SPA
        );
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function make(string $rule, Branch $branch, string $fingerprint, string $priority, string $title,
        string $message, array $why, ?array $action, array $numbers, string $scope = DssRecommendation::SCOPE_BRANCH): array
    {
        return [
            'rule_key' => $rule, 'branch_id' => (int) $branch->id, 'scope' => $scope,
            'fingerprint' => $fingerprint, 'priority' => $priority,
            'title' => $title, 'message' => $message, 'action' => $action,
            'details' => ['why' => $why, 'numbers' => $numbers, 'branch' => $branch->name],
        ];
    }

    private function num(float|int|null $v): string
    {
        if ($v === null) {
            return '—';
        }

        return rtrim(rtrim(number_format((float) $v, 1, '.', ','), '0'), '.');
    }

    private function hrs(float|int $minutes): string
    {
        return $this->num(round($minutes / 60, 1)).' h';
    }
}
