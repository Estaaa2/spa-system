<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Interview extends Model
{
    use HasFactory;

    protected $fillable = [
        'applicant_id', 'spa_id', 'branch_id',
        'interviewed_by', 'interview_date',
        'interview_time', 'status', 'remarks',
        'staff_account_created',
    ];

    protected $casts = [
        'interview_date'        => 'date',
        'staff_account_created' => 'boolean',
    ];

    public function applicant()    { return $this->belongsTo(Applicant::class); }
    public function interviewer()  { return $this->belongsTo(User::class, 'interviewed_by'); }
    public function spa()          { return $this->belongsTo(Spa::class); }
    public function branch()       { return $this->belongsTo(Branch::class); }
}
