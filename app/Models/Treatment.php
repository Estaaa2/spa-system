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
        return $this->belongsToMany(Package::class)
                    ->withPivot('quantity');
    }
    
    public function getServiceTypeLabelAttribute()
    {
        return match($this->service_type) {
            'in_branch_only' => 'In Branch Only',
            'in_branch_and_home' => 'In Branch & Home',
            default => ucfirst(str_replace('_', ' ', $this->service_type)),
        };
    }
}
