<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchRolePermission extends Model
{
    protected $fillable = [
        'branch_id',
        'spa_id',
        'role_name',
        'permission_name',
        'granted',
    ];

    protected $casts = [
        'granted' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
