<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Carbon\Carbon;

class MarkPastBookingsCompleted extends Command
{
    protected $signature = 'bookings:mark-completed';
    protected $description = 'Mark reserved/confirmed bookings as completed once their end time has passed';

    public function handle(): int
    {
        $now = Carbon::now();

        $updated = Booking::whereIn('status', ['reserved', 'confirmed'])
            ->where(function ($q) use ($now) {
                $q->where('appointment_date', '<', $now->toDateString())
                  ->orWhere(function ($q2) use ($now) {
                      $q2->where('appointment_date', $now->toDateString())
                         ->where('end_time', '<=', $now->toTimeString());
                  });
            })
            ->update(['status' => 'completed']);

        $this->info("Marked {$updated} booking(s) as completed.");

        return self::SUCCESS;
    }
}
