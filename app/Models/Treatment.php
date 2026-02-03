<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Treatment extends Model
{
    use HasFactory;

    protected $fillable = [
        'spa_id',
        'branch_id',
        'name',
        'duration',
        'price',
        'service_type',
        'description',
    ];

    public function spa()
    {
        return $this->belongsTo(Spa::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'package_treatment')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }
    
    public function getServiceTypeLabelAttribute()
    {
        return match($this->service_type) {
            'in_branch_only' => 'In Branch Only',
            'in_branch_and_home' => 'In Branch & Home',
            default => ucfirst(str_replace('_', ' ', $this->service_type)),
        };
    }

    protected static function booted()
    {
        static::addGlobalScope('spa_branch', function ($query) {
            if (auth()->check()) {
                $query->where('spa_id', auth()->user()->spa_id)
                    ->where('branch_id', auth()->user()->branch_id);
            }
        });
    }
}
