<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'spa_id',
        'branch_id',
        'employment_status',
        'hire_date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function spa()
    {
        return $this->belongsTo(Spa::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'therapist_id');
    }

    public function attendance()
    {
        return $this->hasMany(StaffAttendance::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function deployments()
    {
        return $this->hasMany(\App\Models\StaffBranchDeployment::class);
    }
}
