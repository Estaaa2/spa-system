<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Package;
use App\Models\Treatment;
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

    /**
     * The booking's `treatment` column holds "treatment_12" or "package_3".
     */
    private function resolveTreatmentName(?string $value): string
    {
        $value = (string) $value;

        if (str_starts_with($value, 'treatment_')) {
            $id = (int) substr($value, strlen('treatment_'));
            $treatment = Treatment::withoutGlobalScopes()->find($id);

            return $treatment?->name ?? 'Unknown Treatment';
        }

        if (str_starts_with($value, 'package_')) {
            $id = (int) substr($value, strlen('package_'));
            $package = Package::withoutGlobalScopes()->find($id);

            return $package ? $package->name . ' (Package)' : 'Unknown Package';
        }

        return 'Unknown Treatment';
    }

    private function formatBooking(Booking $b): array
    {
        $treatmentName = $this->resolveTreatmentName($b->treatment);

        $hasRating   = $b->rating !== null;
        $ratingValue = $hasRating ? $b->rating->rating : null;

        return [
            'id'                 => $b->id,
            'branch_id'          => $b->branch_id,
            'spa_name'           => $b->spa?->name ?? 'N/A',
            'branch_name'        => $b->branch?->name ?? 'N/A',
            'treatment'          => $treatmentName,
            'date'               => $b->appointment_date->format('F j, Y'),
            'date_raw'           => $b->appointment_date->format('Y-m-d'),
            'start_time'         => $b->start_time,
            'end_time'           => $b->end_time,
            'status'             => $b->status,
            'therapist'          => $b->therapist
                ? trim($b->therapist->first_name . ' ' . $b->therapist->last_name)
                : 'Not Assigned',
            'price'              => null,
            'service_type'       => $b->service_type_label,
            'reschedule_status'  => $b->latestRescheduleRequest?->status ?? null,
            'reschedule_pending' => $b->latestRescheduleRequest?->isPending() ?? false,
            'has_rating'         => $hasRating,
            'rating_value'       => $ratingValue,
        ];
    }
}
