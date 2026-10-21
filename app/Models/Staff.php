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
        'applicant_id',
        'employment_status',
        'keep_active',
        'plan_paused_at',
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
        'keep_active'    => 'boolean',
        'plan_paused_at' => 'datetime',
        'tin'           => 'encrypted',
        'sss_no'        => 'encrypted',
        'philhealth_no' => 'encrypted',
        'pagibig_no'    => 'encrypted',
    ];

    /** Paused by the system because the spa is over its plan's staff limit. */
    public function isPlanPaused(): bool
    {
        return $this->plan_paused_at !== null;
    }

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

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }
}
