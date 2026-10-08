<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'spa_id',
        'business_tier',
        'amount',
        'billing_cycle',
        'paymongo_checkout_id',
        'paymongo_payment_id',
        'payment_method',
        'payment_status',
        'status',
        'starts_at',
        'expires_at',
        'cancelled_at',
    ];
    protected $casts = [
        'amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function spa()
    {
        return $this->belongsTo(Spa::class);
    }
}