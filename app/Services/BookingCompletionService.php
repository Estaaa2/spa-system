<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BookingCompletionService
{
    public function complete(Booking $booking,?User $user = null): Booking
    {
        return DB::transaction(function () use ($booking, $user) {
            $lockedBooking = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBooking->status === 'completed') {
                return $lockedBooking;
            }

            if ($lockedBooking->status === 'cancelled') {
                return $lockedBooking;
            }

            app(BookingConsumptionService::class)
                ->consume($lockedBooking, $user);

            $lockedBooking->update([
                'status' => 'completed',
            ]);

            return $lockedBooking->fresh();
        });
    }
}
