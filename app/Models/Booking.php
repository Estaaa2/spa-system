<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

        protected $fillable = [
            'spa_id',
            'branch_id',
            'created_by_user_id',
            // 'booking_source',
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
    
}
