<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payroll extends Model
{
    use HasFactory;

    protected $table = 'payroll';

    protected $fillable = [
        'staff_id', 'spa_id', 'branch_id',
        'period_label', 'period_start', 'period_end',
        'basic_salary', 'days_present', 'days_absent', 'days_late',
        'absent_deduction', 'late_deduction', 'commission',
        'total_pay', 'status',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
    ];

    public function staff()  { return $this->belongsTo(Staff::class); }
    public function spa()    { return $this->belongsTo(Spa::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
}
