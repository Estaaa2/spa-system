<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'spa_id',
        'branch_id',
        'name',
        'total_duration',
        'price',
        'description',
    ];

    // Treatments included in this package
    public function treatments()
    {
        return $this->belongsToMany(Treatment::class, 'package_treatment')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }

    // Optional helper: get total duration
    public function getDurationAttribute()
    {
        if ($this->total_duration) {
            return $this->total_duration;
        }
        return $this->treatments->sum('duration');
    }

    // Optional helper: get treatment IDs easily
    public function getIncludedTreatmentsAttribute()
    {
        return $this->treatments->pluck('id')->toArray();
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
