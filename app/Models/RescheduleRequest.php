<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RescheduleRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    /** Nobody reviewed it before the original start time (booking lifecycle fix, Phase 3). */
    public const STATUS_EXPIRED  = 'expired';

    public const EXPIRED_REASON = 'Not reviewed before your appointment started, so your original schedule stands.';

    protected $fillable = [
        'booking_id',
        'requested_by',
        'requested_date',
        'requested_time',
        'reason',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'requested_date' => 'date',
        'requested_time' => 'string',
        'reviewed_at'    => 'datetime',
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

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
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
     * Pending and still answerable: the booking is open and its original start
     * hasn't come (booking lifecycle fix, Phase 3, D5). This is what staff lists
     * and badges count; a pending row past that point is waiting for the
     * expiry job and is never shown or approvable.
     */
    public function scopeOpen($query)
    {
        return $query->where($query->qualifyColumn('status'), self::STATUS_PENDING)
            ->whereHas('booking', fn ($b) => $b->whereIn('status', Booking::ACTIVE_STATUSES)->notStarted());
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

    /**
     * Too late to answer: the booking is no longer open, or its original start
     * time has come (D5). The booking keeps its original schedule.
     */
    public function isPastDeadline(): bool
    {
        $booking = $this->booking;
        $start = $booking?->startsAt();

        return ! $booking
            || ! in_array($booking->status, Booking::ACTIVE_STATUSES, true)
            || $start === null
            || now()->gte($start);
    }

    /**
     * The status the mobile app is sent (Phase 3, Q1-C): `expired` goes out as
     * `rejected` until the app knows the new value; `reschedule_expired` /
     * `expired` fields carry the truth.
     */
    public function legacyStatus(): string
    {
        return $this->isExpired() ? self::STATUS_REJECTED : (string) $this->status;
    }
}
