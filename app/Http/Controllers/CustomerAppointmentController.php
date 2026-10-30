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

        $bookings = Booking::with(['spa', 'branch.profile', 'therapist', 'latestRescheduleRequest', 'rating'])
            ->where('customer_user_id', $user->id)
            ->orderBy('appointment_date', 'desc')
            ->get()
            ->map(fn($b) => $this->formatBooking($b));

        return response()->json($bookings);
    }

    public function schedule()
    {
        $user = Auth::user();

        $bookings = Booking::with(['spa', 'branch.profile', 'therapist', 'rating'])
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
        ] + $this->therapistCard($b);
    }

    /**
     * Who is coming, and the arrival code (staff verification D6, D7).
     *
     * therapist_card  photo, name, verified badge — upcoming or running bookings
     *                 only (the photo route allows exactly those)
     * arrival_code    online home service, not yet arrived; this is the only place
     *                 (besides the customer's email) where the code is ever sent
     */
    private function therapistCard(Booking $b): array
    {
        $active = in_array($b->status, Booking::ACTIVE_STATUSES, true)
            && ! $b->appointment_date->isBefore(today());
        $isHome = $b->requiresArrivalCode();

        if ($active && $isHome) {
            $b->ensureArrivalCode();
        }

        $therapist = $b->therapist;

        return [
            'is_home_service'      => $isHome,
            'therapist_assigned'   => $therapist !== null,
            'therapist_card'       => $active && $therapist ? [
                'name'      => trim($therapist->first_name.' '.$therapist->last_name),
                'initials'  => $therapist->initials(),
                // The app can't send the web session cookie: give it the token route.
                'photo_url' => request()->is('api/*') ? $therapist->profilePhotoApiUrl() : $therapist->profilePhotoUrl(),
                'verified'  => $therapist->isIdentityVerified(),
            ] : null,
            'arrival_code'         => $active && $isHome && ! $b->isArrivalConfirmed() ? $b->arrival_code : null,
            'arrival_confirmed_at' => $b->arrival_confirmed_at?->format('g:i A'),
            'branch_phone'         => $b->branch?->profile?->phone,
        ];
    }
}
