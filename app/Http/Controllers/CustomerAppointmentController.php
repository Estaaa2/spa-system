<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Package;
use App\Models\Treatment;
use App\Services\Bookings\CustomerCancellation;
use Illuminate\Support\Facades\Auth;

class CustomerAppointmentController extends Controller
{
    public function __construct(private CustomerCancellation $cancellation)
    {
    }

    public function appointments()
    {
        $user = Auth::user();

        $bookings = Booking::with(['spa', 'branch.profile', 'therapist', 'latestRescheduleRequest', 'rating', 'reservation', 'refund'])
            ->where('customer_user_id', $user->id)
            ->orderBy('appointment_date', 'desc')
            ->get()
            ->map(fn($b) => $this->formatBooking($b));

        return response()->json($bookings);
    }

    public function schedule()
    {
        $user = Auth::user();

        $bookings = Booking::with(['spa', 'branch.profile', 'therapist', 'rating', 'reservation', 'refund'])
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
            // Q1-C (lifecycle Phase 3): the app is sent `rejected` for an expired
            // request until it knows `expired`; the web reads the real value.
            'reschedule_status'  => $b->latestRescheduleRequest
                ? (request()->is('api/*') ? $b->latestRescheduleRequest->legacyStatus() : $b->latestRescheduleRequest->status)
                : null,
            'reschedule_expired' => $b->latestRescheduleRequest?->isExpired() ?? false,
            // A pending one past the original start is expired (job may not have run yet).
            'reschedule_pending' => ($b->latestRescheduleRequest?->isPending() ?? false)
                && ! $b->latestRescheduleRequest->isPastDeadline(),
            // Same rule the server enforces on submit; the app can read it too.
            'can_reschedule'     => $b->canRequestReschedule(),
            'has_rating'         => $hasRating,
            'rating_value'       => $ratingValue,
            // Customer cancellation (Nov 2026): what cancelling now would mean,
            // and what happened after a cancel. Same rule the endpoint enforces.
            'cancel'             => $this->cancellation->quote($b),
            'cancelled'          => $this->cancelledInfo($b),
        ] + $this->therapistCard($b);
    }

    /** Who ended it, and where the money stands; null unless cancelled or a no-show. */
    private function cancelledInfo(Booking $b): ?array
    {
        if (! in_array($b->status, ['cancelled', Booking::NO_SHOW], true)) {
            return null;
        }

        $noShow = $b->status === Booking::NO_SHOW;

        // The 20% stays with the spa after a late customer cancel or a no-show.
        $kept = $b->booking_source === 'online'
            && ($noShow || ($b->cancelled_by === 'customer' && ! $b->free_cancellation))
            ? $this->cancellation->money($b)['downpayment']
            : null;

        return [
            // customer | staff | no_show | null (older cancelled rows)
            'by'            => $noShow ? 'no_show' : $b->cancelled_by,
            'on'            => $b->cancelled_at?->format('F j, Y'),
            'free'          => (bool) $b->free_cancellation,
            'kept'          => $kept,
            'refund_amount' => $b->refund ? (float) $b->refund->amount : null,
            'refund_status' => $b->refund?->status,       // pending | sent
            'refund_sent_on' => $b->refund?->processed_at?->format('F j, Y'),
        ];
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
