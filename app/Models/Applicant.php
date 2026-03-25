<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Applicant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'job_posting_id',
        'spa_id',
        'branch_id',
        'position_applied',
        'availability',
        'source',
        'gender',
        'date_of_birth',
        'civil_status',
        'address',
        'education',
        'work_experience',
        'skills',
        'emergency_contact_name',
        'emergency_contact_relation',
        'emergency_contact_phone',
        'expected_start_date',
        'full_name',
        'email',
        'phone',
        'notes',
        'status',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'expected_start_date' => 'date',
        'deleted_at' => 'datetime',
    ];

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function spa(): BelongsTo
    {
        return $this->belongsTo(Spa::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function interview(): HasOne
    {
        return $this->hasOne(Interview::class);
    }
}