<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Interview;

class Applicant extends Model
{
    use HasFactory;

    protected $fillable = [
    'job_posting_id', 'spa_id', 'branch_id',
    'full_name', 'email', 'phone',
    'role', 'gender', 'date_of_birth', 'civil_status', 'address',
    'education', 'work_experience', 'skills',
    'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
    'expected_start_date', 'notes', 'status',
];

    public function jobPosting() { return $this->belongsTo(JobPosting::class); }
    public function spa()        { return $this->belongsTo(Spa::class); }
    public function branch()     { return $this->belongsTo(Branch::class); }
    public function interview()  { return $this->hasOne(Interview::class); }
}
