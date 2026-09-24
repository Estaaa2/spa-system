<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'tin',
        'sss_no',
        'philhealth_no',
        'pagibig_no',
    ];

    protected $hidden = [
        'tin',
        'sss_no',
        'philhealth_no',
        'pagibig_no',
    ];

    protected $casts = [
        'tin'           => 'encrypted',
        'sss_no'        => 'encrypted',
        'philhealth_no' => 'encrypted',
        'pagibig_no'    => 'encrypted',
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

    // ── Payroll v3 ───────────────────────────────────────────────────────────

    public function payProfiles(): HasMany
    {
        return $this->hasMany(StaffPayProfile::class);
    }

    public function recurringItems(): HasMany
    {
        return $this->hasMany(StaffRecurringItem::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }
}