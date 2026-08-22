<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promo extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'spa_id',
        'branch_id',
        'name',
        'discount_type',
        'discount_value',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_active'  => 'boolean',
    ];

    public function treatments()
    {
        return $this->belongsToMany(Treatment::class, 'promo_treatment')
                    ->withTimestamps();
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'promo_package')
                    ->withTimestamps();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeActiveToday(Builder $query)
    {
        return $query->where('is_active', true)
                      ->whereDate('start_date', '<=', now())
                      ->whereDate('end_date', '>=', now());
    }

    public function discountedPrice(float $price): float
    {
        return $this->discount_type === 'percent'
            ? round($price * (1 - $this->discount_value / 100), 2)
            : max(0, round($price - $this->discount_value, 2));
    }

    protected static function booted()
    {
        static::addGlobalScope('spa_branch', function (Builder $query) {
            if (auth()->check()) {
                $user = auth()->user();
                $branchId = session('current_branch_id') ?? $user->branch_id;

                $query->where('spa_id', $user->spa_id);

                if ($branchId) {
                    $query->where('branch_id', $branchId);
                }
            }
        });
    }
}
