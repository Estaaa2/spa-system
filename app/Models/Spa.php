<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Spa extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'business_tier',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(\App\Models\Subscription::class);
    }

    public function activeSubscription()
    {
        return $this->subscriptions()
                    ->where('payment_status', 'paid')
                    ->where('expires_at', '>', now())
                    ->latest()
                    ->first();
    }

    public function isProfessional(): bool
    {
        return $this->business_tier === 'professional'
            && $this->activeSubscription() !== null;
    }
}
