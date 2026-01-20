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
}
