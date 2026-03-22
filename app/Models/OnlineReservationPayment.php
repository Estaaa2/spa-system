<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnlineReservationPayment extends Model
{
    protected $fillable = [
        'user_id',
        'spa_id',
        'branch_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_address',
        'bookable_id',
        'bookable_type',
        'bookable_name',
        'full_amount',
        'downpayment_amount',
        'service_type',
        'appointment_date',
        'start_time',
        'paymongo_checkout_session_id',
        'paymongo_payment_intent_id',
        'paymongo_payment_id',
        'payment_reference',
        'payment_status',
        'reservation_status',
        'paymongo_payload',
        'paid_at',
        'booking_id',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'paid_at' => 'datetime',
        'paymongo_payload' => 'array',
        'full_amount' => 'decimal:2',
        'downpayment_amount' => 'decimal:2',
    ];
}