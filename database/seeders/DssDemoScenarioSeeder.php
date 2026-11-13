<?php

namespace Database\Seeders;

use App\Http\Controllers\Insights\DecisionSupportController;
use App\Models\Branch;
use App\Models\DssRecommendation;
use App\Models\LeaveRequest;
use App\Models\Spa;
use App\Models\Staff;
use App\Services\Bookings\TherapistAssigner;
use App\Services\DecisionSupport\RecommendationEngine;
use App\Services\Finance\BookingRevenue;
use App\Services\Staff\WorkScheduleResolver;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoCatalog;
use Database\Seeders\Demo\DemoGuard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * DSS DEMO SCENARIO — turns one demo branch into a "busy branch" so the defense can
 * show every Decision Support rule on real-looking data (DSS revamp, Phase 3 item 4).
 * Runs AFTER DemoDataSeeder; touches only the demo spa "Serenity Wellness Spa (Demo)".
 *
 *   php artisan db:seed --class=DssDemoScenarioSeeder --force         (once)
 *   php artisan db:seed --class=DssDemoScenarioResetSeeder --force    (remove, then seed again)
 *   php artisan db:seed --class=DssDemoScenarioRemoveSeeder --force   (remove only)
 *
 * What it adds to the Bacoor branch:
 *   - the last 8 weeks filled to ~90% of every therapist's shift with completed walk-in
 *     bookings, in free slots only            → R2 "hire", and R1 for the coming days;
 *   - an approved leave for one therapist 2–4 days ahead → R1 "short" (high priority);
 *   - completed bookings 2–6 months back so the last 3 months are ≥ 25% above the
 *     3 months before                         → R6 "open another branch" (owner);
 * R3 (light days) and R4 (treatment promo) already show on the other demo branches.
 *
 * Every added booking has customer_email DssDemoScenarioSeeder::MARKER_EMAIL; the leave
 * reason starts with "(DSS demo)". The Remove seeder deletes exactly those, plus the
 * spa's stored recommendations. Same guard as the demo seeders (DEMO_SEED_ALLOW_PRODUCTION).
 */
class DssDemoScenarioSeeder extends Seeder
{
    public const MARKER_EMAIL = 'dss.scenario@levictas-demo.test';
    public const LEAVE_MARKER = '(DSS demo)';
    public const SPA_KEY      = 'serenity';
    public const BRANCH_LIKE  = 'Bacoor';
    public const FILL         = 0.9;

    private array $rows = [];

    public function run(): void
    {
        if (! DemoGuard::environmentAllowed($this->command, 'DssDemoScenarioSeeder')) {
            return;
        }

        [$spa, $branch] = self::target();
        if (! $spa || ! $branch) {
            $this->command?->error('Demo spa not found. Run DemoDataSeeder first.');

            return;
        }
        if (DB::table('bookings')->where('customer_email', self::MARKER_EMAIL)->exists()) {
            $this->command?->warn('The DSS scenario is already there. For a fresh copy run: php artisan db:seed --class=DssDemoScenarioResetSeeder --force');

            return;
        }

        $today = CarbonImmutable::today();

        DB::transaction(function () use ($spa, $branch, $today) {
            $treatments = DB::table('treatments')->where('spa_id', $spa->id)->where('branch_id', $branch->id)
                ->where('price', '>', 0)->orderBy('id')->get(['id', 'price', 'duration'])->values();
            if ($treatments->isEmpty()) {
                throw new \RuntimeException('The demo branch has no priced treatments.');
            }

            $assigner = app(TherapistAssigner::class);
            $resolver = app(WorkScheduleResolver::class);
            $pool = $assigner->pool((int) $spa->id, (int) $branch->id);
            $staff = Staff::where('spa_id', $spa->id)->where('branch_id', $branch->id)->where('employment_status', 'active')
                ->whereIn('user_id', $pool->pluck('id'))->get()->keyBy('user_id');
            if ($staff->isEmpty()) {
                throw new \RuntimeException('The demo branch has no therapists.');
            }

            // 1. Last 8 weeks at ~90% (the forecast window, so forecast and R2 agree).
            $from = $today->subDays(56);
            $resolver->prime($staff->values(), $from->toDateString(), $today->addDays(7)->toDateString());
            $i = 0;
            for ($d = $from; $d->lt($today); $d = $d->addDay()) {
                $date = $d->toDateString();
                $busy = DB::table('bookings')->where('branch_id', $branch->id)->whereDate('appointment_date', $date)
                    ->where('status', '!=', 'cancelled')->get(['therapist_id', 'start_time', 'end_time']);

                foreach ($staff as $userId => $s) {
                    $shift = $resolver->forDate($s, $date, (int) $branch->id);
                    if (! $shift->isWorking() || ! $shift->start || ! $shift->end) {
                        continue;
                    }
                    $a = self::min($shift->start);
                    $b = self::min($shift->end);
                    $mine = $busy->where('therapist_id', $userId)->map(fn ($x) => [self::min($x->start_time), self::min($x->end_time)])->values()->all();
                    $booked = array_sum(array_map(fn ($w) => max(0, min($w[1], $b) - max($w[0], $a)), $mine));
                    $target = (int) floor(($b - $a) * self::FILL);

                    for ($t = $a; $t + 60 <= $b && $booked < $target; $t += 60) {
                        $overlaps = collect($mine)->contains(fn ($w) => $t < $w[1] && $t + 60 > $w[0]);
                        if ($overlaps) {
                            continue;
                        }
                        $tr = $treatments[$i++ % $treatments->count()];
                        $this->addBooking($spa, $branch, $userId, $date, $t, (float) $tr->price, (int) $tr->id);
                        $booked += 60;
                    }
                }
            }
            $this->flush();

            // 2. An approved leave 2–4 days ahead for a therapist who would work that day.
            for ($k = 2; $k <= 4; $k++) {
                $date = $today->addDays($k)->toDateString();
                $onShift = $staff->filter(fn ($s) => $resolver->forDate($s, $date, (int) $branch->id)->isWorking());
                if ($onShift->count() >= 2) {
                    $s = $onShift->first();
                    LeaveRequest::create([
                        'user_id' => $s->user_id, 'spa_id' => $spa->id, 'branch_id' => $branch->id,
                        'leave_type' => 'vacation', 'start_date' => $date, 'end_date' => $date,
                        'reason' => self::LEAVE_MARKER.' Family event', 'status' => 'approved',
                    ]);
                    break;
                }
            }

            // 3. Revenue history for R6: last 3 complete months ≥ the 3 before × 1.25.
            $m1 = $today->startOfMonth()->subMonth();
            $rev = fn (CarbonImmutable $m) => BookingRevenue::serviceRevenue((int) $spa->id, (int) $branch->id, $m->toDateString(), $m->endOfMonth()->toDateString());
            $recent = [$m1, $m1->subMonth(), $m1->subMonths(2)];
            $top = max(array_map($rev, $recent));
            $avgPrice = (float) $treatments->avg('price');
            $userIds = $staff->keys()->values();
            foreach ($recent as $m) {
                $this->topUp($spa, $branch, $m, $top - $rev($m), $avgPrice, $treatments, $userIds, $from);
            }
            $this->flush();
            $floor = min(array_map($rev, $recent));
            foreach ([$m1->subMonths(3), $m1->subMonths(4), $m1->subMonths(5)] as $m) {
                $this->topUp($spa, $branch, $m, $floor / 1.25 - $rev($m), $avgPrice, $treatments, $userIds, $from);
            }
            $this->flush();
        });

        Cache::forget(DecisionSupportController::syncKey((int) $spa->id));
        $open = app(RecommendationEngine::class)->sync($spa);
        Cache::forever(DecisionSupportController::syncKey((int) $spa->id), now()->toIso8601String());

        $this->command?->info("DSS scenario added to {$spa->name} / {$branch->name}. {$open} open recommendations for the spa:");
        $this->command?->table(['Branch', 'Rule', 'Priority', 'Title'],
            DssRecommendation::where('spa_id', $spa->id)->open()->byPriority()->with('branch:id,name')->get()
                ->map(fn ($r) => [$r->branch?->name, $r->rule_key, $r->priority, $r->title])->all());
    }

    /** @return array{0: ?Spa, 1: ?Branch} */
    public static function target(): array
    {
        $name = DemoCatalog::SPAS[self::SPA_KEY][0].DemoCatalog::NAME_SUFFIX;
        $spa = Spa::withoutGlobalScopes()->where('name', $name)->first();
        $branch = $spa ? Branch::withoutGlobalScopes()->where('spa_id', $spa->id)->where('name', 'like', '%'.self::BRANCH_LIKE.'%')->first() : null;

        return [$spa, $branch];
    }

    /** Completed bookings worth about $amount spread over $month (only before $notFrom), round-robin therapists. */
    private function topUp(Spa $spa, Branch $branch, CarbonImmutable $month, float $amount, float $avgPrice, $treatments, $userIds, CarbonImmutable $notFrom): void
    {
        if ($amount <= 0 || $avgPrice <= 0) {
            return;
        }
        $count = (int) ceil($amount / $avgPrice);
        $days = [];
        for ($d = $month->startOfMonth(); $d->lte($month->endOfMonth()); $d = $d->addDay()) {
            if ($d->lt($notFrom) && $d->lt(CarbonImmutable::today())) {
                $days[] = $d->toDateString();
            }
        }
        if ($days === []) {
            return;
        }
        for ($n = 0; $n < $count; $n++) {
            $date = $days[$n % count($days)];
            $slot = intdiv($n, count($days));             // 0, 1, 2 … per day
            $tr = $treatments[$n % $treatments->count()];
            $this->addBooking($spa, $branch, (int) $userIds[$n % $userIds->count()], $date, (10 + ($slot % 8)) * 60, (float) $tr->price, (int) $tr->id);
        }
    }

    private function addBooking(Spa $spa, Branch $branch, int $therapistId, string $date, int $startMin, float $price, int $treatmentId): void
    {
        $start = sprintf('%02d:%02d:00', intdiv($startMin, 60), $startMin % 60);
        $end = sprintf('%02d:%02d:00', intdiv($startMin + 60, 60), ($startMin + 60) % 60);
        $at = CarbonImmutable::parse($date.' '.$end)->addMinutes(10);
        $this->rows[] = [
            'spa_id' => $spa->id, 'branch_id' => $branch->id, 'customer_user_id' => null, 'created_by_user_id' => null,
            'booking_source' => 'walk_in', 'status' => 'completed', 'payment_status' => 'paid',
            'amount_paid' => $price, 'total_amount' => $price, 'balance_amount' => 0,
            'service_type' => 'in_branch', 'treatment' => 'treatment_'.$treatmentId,
            'customer_name' => 'Walk-in guest (DSS demo)', 'customer_email' => self::MARKER_EMAIL, 'customer_phone' => '09170000000',
            'appointment_date' => $date, 'start_time' => $start, 'end_time' => $end,
            'therapist_id' => $therapistId, 'created_at' => $at, 'updated_at' => $at,
        ];
        if (count($this->rows) >= 500) {
            $this->flush();
        }
    }

    private function flush(): void
    {
        foreach (array_chunk($this->rows, 200) as $chunk) {
            DB::table('bookings')->insert($chunk);
        }
        $this->rows = [];
    }

    private static function min(?string $t): int
    {
        [$h, $m] = array_map('intval', array_pad(explode(':', (string) $t), 2, 0));

        return $h * 60 + $m;
    }
}
