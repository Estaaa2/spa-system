<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Navigation\NavBadges;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * One-time data fix (booking lifecycle fix, Phase 2, D8).
 *
 * Before Phase 1 no staff action could complete a booking (F1): every
 * `completed` booking was closed by the clock. Since Phase 1, checkout always
 * writes a booking_consumptions row with processed_by set. So:
 *
 *   completed by the clock  =  status completed
 *                              AND no booking_consumptions row with processed_by
 *
 * Those are reopened as `pending`, so they appear under Needs Checkout and staff
 * close them properly. Payments, ratings and any stock already deducted by the
 * old page-load path stay as they are (checkout won't deduct twice: the existing
 * consumption row is reused). Re-running finds nothing new.
 */
class ReopenClockCompletedBookings extends Command
{
    protected $signature = 'bookings:reopen-clock-completed
                            {--dry-run : Show what would be reopened without changing anything}
                            {--spa= : Only this spa id}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Reopen bookings the old clock marked completed with no staff checkout (lifecycle fix D8)';

    public function handle(): int
    {
        $query = $this->matching();
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nothing to reopen: every completed booking was checked out by staff.');

            return self::SUCCESS;
        }

        $withBalance = (clone $query)->where('balance_amount', '>', 0)->count();
        $rated       = (clone $query)->whereHas('rating')->count();
        $consumed    = (clone $query)->whereHas('consumption')->count();

        $this->line("Completed by the clock, no staff checkout: <comment>{$total}</comment>");
        $this->line("  with a balance still owed:        {$withBalance}");
        $this->line("  already rated by the customer:    {$rated}");
        $this->line("  stock already deducted (kept):    {$consumed}");

        $this->table(
            ['Spa', 'Branch', 'Bookings', 'With balance'],
            (clone $query)
                ->select('spa_id', 'branch_id',
                    DB::raw('COUNT(*) as bookings'),
                    DB::raw('SUM(CASE WHEN balance_amount > 0 THEN 1 ELSE 0 END) as with_balance'))
                ->groupBy('spa_id', 'branch_id')
                ->orderBy('spa_id')->orderBy('branch_id')
                ->get()
                ->map(fn ($r) => [$r->spa_id, $r->branch_id, $r->bookings, $r->with_balance])
                ->all()
        );

        if ($this->option('dry-run')) {
            $ids = (clone $query)->orderBy('id')->limit(50)->pluck('id')->implode(', ');
            $this->line('Booking ids'.($total > 50 ? ' (first 50)' : '').": {$ids}");
            $this->info('Dry run: nothing was changed.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Reopen these {$total} booking(s) as pending?", false)) {
            $this->warn('Cancelled. Nothing was changed.');

            return self::SUCCESS;
        }

        $spaIds = (clone $query)->distinct()->pluck('spa_id');

        // Plain query update: no model events, so no customer emails and no
        // arrival-code side effects. Only the status changes.
        $reopened = DB::transaction(fn () => $this->matching()->update([
            'status'     => 'pending',
            'updated_at' => now(),
        ]));

        $spaIds->each(fn ($id) => NavBadges::bump((int) $id));

        $this->info("Reopened {$reopened} booking(s). They are listed under Appointments → Needs Checkout.");

        return self::SUCCESS;
    }

    private function matching(): Builder
    {
        return Booking::query()
            ->where('status', 'completed')
            ->whereDoesntHave('consumption', fn ($q) => $q->whereNotNull('processed_by'))
            ->when($this->option('spa'), fn ($q, $spa) => $q->where('spa_id', (int) $spa));
    }
}
