<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// App\Models\Booking.php
class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'spa_id',
        'branch_id',
        'created_by_user_id',
        'status',
        'service_type',
        'treatment',
        'therapist_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_address',
        'appointment_date',
        'appointment_time',
    ];

    // Remove or update this casts array:
    protected $casts = [
        'appointment_date' => 'date',
        'appointment_time' => 'datetime:H:i',
        // REMOVE: 'therapist' => 'array', // This is wrong!
    ];

    // Who created the booking (staff/receptionist)
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // Assigned therapist
    public function therapist()
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    // If you ever link a customer as a registered user
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    // Optional: branch
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // Optional: spa
    public function spa()
    {
        return $this->belongsTo(Spa::class);
    }

    public function getEmailAttribute()
    {
        return $this->user->email ?? null;
    }

    // SIMPLIFY the accessor - you don't need complex logic
    public function getTherapistNameAttribute()
    {
        // Since you're using relationship, just access it directly
        return $this->therapist ? $this->therapist->name : 'Not Assigned';
    }
}
