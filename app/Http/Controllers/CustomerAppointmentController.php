<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Support\Facades\Auth;

class CustomerAppointmentController extends Controller
{
    public function appointments()
    {
        $user = Auth::user();

        $bookings = Booking::with(['spa', 'branch', 'therapist', 'latestRescheduleRequest', 'rating'])
            ->where('customer_user_id', $user->id)
            ->orderBy('appointment_date', 'desc')
            ->get()
            ->map(fn($b) => $this->formatBooking($b));

        return response()->json($bookings);
    }

    public function schedule()
    {
        $user = Auth::user();

        $bookings = Booking::with(['spa', 'branch', 'therapist', 'rating'])
            ->where('customer_user_id', $user->id)
            ->whereIn('status', ['reserved', 'pending', 'ongoing'])
            ->where('appointment_date', '>=', now()->toDateString())
            ->orderBy('appointment_date', 'asc')
            ->get()
            ->map(fn($b) => $this->formatBooking($b));

        return response()->json($bookings);
    }

    private function formatBooking(Booking $b): array
    {
        // Resolve treatment name manually without global scopes
        $treatmentName = 'Unknown Treatment';

        if (str_starts_with($b->treatment, 'treatment_')) {
            $id = (int) str_replace('treatment_', '', $b->treatment);
            $treatment = \App\Models\Treatment::withoutGlobalScopes()->find($id);
            $treatmentName = $treatment?->name ?? 'Unknown Treatment';

        } elseif (str_starts_with($b->treatment, 'package_')) {
            $id = (int) str_replace('package_', '', $b->treatment);
            $package = \App\Models\Package::withoutGlobalScopes()->find($id);
            $treatmentName = $package ? $package->name . ' (Package)' : 'Unknown Package';
        }

        // Get rating information
        $hasRating = $b->rating()->exists();
        $ratingValue = $hasRating ? $b->rating->rating : null;

        return [
            'id'           => $b->id,
            'branch_id'    => $b->branch_id,
            'spa_name'     => $b->spa?->name ?? 'N/A',
            'branch_name'  => $b->branch?->name ?? 'N/A',
            'treatment'    => $treatmentName,
            'date'         => $b->appointment_date->format('F j, Y'),
            'date_raw'     => $b->appointment_date->format('Y-m-d'),
            'start_time'   => $b->start_time,
            'end_time'     => $b->end_time,
            'status'       => $b->status,
            'therapist'    => $b->therapist ? trim($b->therapist->first_name . ' ' . $b->therapist->last_name) : 'Not Assigned',
            'price'        => null,
            'service_type' => $b->service_type_label,
            'reschedule_status' => $b->latestRescheduleRequest?->status ?? null,
            'reschedule_pending' => $b->latestRescheduleRequest?->isPending() ?? false,
            // ← ADD THESE LINES
            'has_rating'   => $hasRating,
            'rating_value' => $ratingValue,
        ];
    }
}
