<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReassignmentRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    /** Nobody acted on it before the booking ended (booking lifecycle fix, Phase 3, D6). */
    public const STATUS_EXPIRED  = 'expired';

    public const EXPIRED_REASON = 'Not reviewed before the appointment ended.';

    protected $fillable = [
        'booking_id',
        'requested_by',
        'old_therapist_id',
        'new_therapist_id',
        'reason',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'leave_request_id',
        'source',   // null (therapist / leave), 'unverified' (VerificationBookingFlags), 'schedule' (ScheduleBookingFlags) or 'clocked_out' (AttendanceBookingFlags)
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    // =====================
    // Relationships
    // =====================

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function oldTherapist()
    {
        return $this->belongsTo(User::class, 'old_therapist_id');
    }

    public function newTherapist()
    {
        return $this->belongsTo(User::class, 'new_therapist_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function leaveRequest()
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    // =====================
    // Scopes
    // =====================

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Pending and still actionable: the booking is open and hasn't ended
     * (D6). Lists, badges and the Dashboard alert count these.
     */
    public function scopeOpen($query)
    {
        return $query->where($query->qualifyColumn('status'), self::STATUS_PENDING)
            ->whereHas('booking', fn ($b) => $b->whereIn('status', Booking::ACTIVE_STATUSES)->notEnded());
    }

    // =====================
    // Helpers
    // =====================

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    /** Too late to act: the booking is no longer open, or it has ended (D6). */
    public function isPastDeadline(): bool
    {
        $booking = $this->booking;
        $end = $booking?->endsAt();

        return ! $booking
            || ! in_array($booking->status, Booking::ACTIVE_STATUSES, true)
            || $end === null
            || now()->gte($end);
    }

    /** What the current mobile app is sent (Q1-C): `expired` goes out as `rejected`. */
    public function legacyStatus(): string
    {
        return $this->isExpired() ? self::STATUS_REJECTED : (string) $this->status;
    }
}