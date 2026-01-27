<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'spa_id',
        'name',
        'location',
        'phone',
        'email',
        'is_main', // ADD THIS
    ];

    protected $casts = [
        'is_main' => 'boolean', // ADD THIS
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

    // ADD THIS METHOD for staff count
    public function staff()
    {
        return $this->hasMany(User::class)->whereHas('roles', function($q) {
            $q->whereIn('name', ['staff', 'therapist', 'admin']);
        });
    }
}
