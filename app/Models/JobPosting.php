<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Applicant;

class JobPosting extends Model
{
    use HasFactory;

    protected $fillable = [
        'spa_id', 'branch_id', 'created_by',
        'title', 'role', 'description',
        'requirements', 'status', 'deadline',
    ];

    protected $casts = ['deadline' => 'date'];

    public function spa()      { return $this->belongsTo(Spa::class); }
    public function branch()   { return $this->belongsTo(Branch::class); }
    public function creator()  { return $this->belongsTo(User::class, 'created_by'); }
    public function applicants() { return $this->hasMany(Applicant::class); }
}
