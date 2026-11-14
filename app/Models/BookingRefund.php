<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money Levictas owes a customer after a cancellation (customer cancellation,
 * Nov 2026). Created by CustomerCancellation; a Levictas admin sends it by hand
 * and marks it sent with a reference number.
 */
class BookingRefund extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT    = 'sent';

    /** Inside the free window: everything paid comes back. */
    public const KIND_FREE = 'free_cancellation';

    /** After the window: the 20% downpayment is kept, the rest comes back. */
    public const KIND_LATE = 'late_cancellation';

    /** The spa cancelled: everything paid online comes back (Terms, Q1-A). */
    public const KIND_SPA = 'spa_cancellation';

    /** A no-show on a full payment: the 20% is kept, the 80% comes back. */
    public const KIND_NO_SHOW = 'no_show';

    protected $fillable = [
        'booking_id',
        'online_reservation_payment_id',
        'user_id',
        'amount',
        'kind',
        'status',
        'payment_method',
        'reference',
        'notes',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withTrashed();
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(OnlineReservationPayment::class, 'online_reservation_payment_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
