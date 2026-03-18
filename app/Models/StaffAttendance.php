<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StaffAttendance extends Model
{
    use HasFactory;

    protected $table = 'staff_attendance';

    protected $fillable = [
        'staff_id', 'spa_id', 'branch_id',
        'date', 'status', 'remarks',
    ];

    protected $casts = ['date' => 'date'];

    public function staff()  { return $this->belongsTo(Staff::class); }
    public function spa()    { return $this->belongsTo(Spa::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
}
