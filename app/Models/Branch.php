<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'spa_id',
        'name',
        'location',
        'is_main',
        'has_workforce_finance_suite',
    ];

    protected $casts = [
        'is_main'          => 'boolean',
        'has_workforce_finance_suite' => 'boolean',
    ];

    public function spa(): BelongsTo
    {
        return $this->belongsTo(Spa::class);
    }

    public function operatingHours(): HasMany
    {
        return $this->hasMany(OperatingHours::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function staff()
    {
        return $this->hasMany(User::class)->whereHas('roles', function ($q) {
            $q->whereIn('name', ['staff', 'therapist', 'admin']);
        });
    }

    public function profile()
    {
        return $this->hasOne(BranchProfile::class);
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(Treatment::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function getUsesWorkforceFinanceSuiteAttribute(): bool
    {
        return (bool) $this->has_workforce_finance_suite;
    }
}
