<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

/**
 * Gates for staff identity verification and profile photos.
 * Registered in AppServiceProvider:
 *   submit-identity-verification    the signed-in user may submit their own
 *   review-identity-verifications   platform admin with `verify staff identity`
 *   view-profile-photo              who may see someone's photo (private disk)
 */
class IdentityPolicy
{
    /** Upcoming or running bookings let the customer see the assigned therapist's face. */
    private const ACTIVE_BOOKING_STATUSES = ['reserved', 'pending', 'ongoing'];

    /** Only treatment providers with an active staff record verify (D1, Q1). */
    public function submit(User $user): bool
    {
        $staff = $user->staff;

        return $staff !== null
            && $staff->employment_status === 'active'
            && $user->isTreatmentProvider();
    }

    /** Platform admin only (D4, Q10). */
    public function review(User $user): bool
    {
        return $user->hasRole('admin')
            && $user->checkPermissionTo('verify staff identity');
    }

    /**
     * Who may see $subject's profile photo:
     * - the person themselves;
     * - a platform admin who reviews identities or manages users;
     * - staff of the same spa, for a staff member's photo (nav, staff list);
     * - a customer whose upcoming/ongoing booking is assigned to $subject (D6).
     * Customer photos are only visible to the customer and admins (D13; whether
     * the assigned therapist sees them is a Phase 7 question).
     */
    public function viewPhoto(User $viewer, User $subject): bool
    {
        if ($viewer->id === $subject->id) {
            return true;
        }

        if ($viewer->hasRole('admin')
            && ($viewer->checkPermissionTo('verify staff identity') || $viewer->checkPermissionTo('view registered users'))) {
            return true;
        }

        if ($subject->hasRole('customer') || ! $subject->spa_id) {
            return false;
        }

        if (! $viewer->hasRole('customer') && $viewer->spa_id && (int) $viewer->spa_id === (int) $subject->spa_id) {
            return true;
        }

        if ($viewer->hasRole('customer')) {
            return Booking::query()
                ->where('customer_user_id', $viewer->id)
                ->where('therapist_id', $subject->id)
                ->whereIn('status', self::ACTIVE_BOOKING_STATUSES)
                ->whereDate('appointment_date', '>=', today()->toDateString())
                ->exists();
        }

        return false;
    }
}
