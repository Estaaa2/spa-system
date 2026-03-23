<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Spa extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'owner_id',
        'name',
        'business_tier',
        'verification_status',
        'verification_remarks',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
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

    public function verificationDocuments(): HasMany
    {
        return $this->hasMany(SpaVerificationDocument::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function hasCompleteVerificationDocuments(): bool
    {
        $required = ['government_id', 'dti_sec', 'bir_certificate'];

        $uploaded = $this->verificationDocuments()
            ->pluck('document_type')
            ->unique()
            ->toArray();

        return count(array_intersect($required, $uploaded)) === count($required);
    }
}