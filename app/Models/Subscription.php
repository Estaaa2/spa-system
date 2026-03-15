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
        'paymongo_checkout_id',
        'payment_status',
        'starts_at',
        'expires_at'
    ];
    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function spa()
    {
        return $this->belongsTo(Spa::class);
    }
}