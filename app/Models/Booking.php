<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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
        'start_time',
        'end_time',
    ];

    // Remove or update this casts array:
    protected $casts = [
        'appointment_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
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

    public function getTherapistNameAttribute()
    {
        return $this->therapist ? $this->therapist->name : 'Not Assigned';
    }

    // Accessor for service type label
    public function getServiceTypeLabelAttribute()
    {
        return $this->service_type === 'in_home' ? 'In Home' : 'In Branch';
    }

    public function getTreatmentLabelAttribute()
    {
        if (str_starts_with($this->treatment, 'treatment_')) {
            $id = intval(str_replace('treatment_', '', $this->treatment));
            $treatment = \App\Models\Treatment::find($id);
            return $treatment ? $treatment->name : 'Unknown Treatment';
        } elseif (str_starts_with($this->treatment, 'package_')) {
            $id = intval(str_replace('package_', '', $this->treatment));
            $package = \App\Models\Package::find($id);
            return $package ? $package->name : 'Unknown Package';
        }
        return $this->treatment; // fallback
    }

        public static function calculateEndTime($treatmentCode, $startTime)
    {
        $duration = 60;

        if (str_starts_with($treatmentCode, 'treatment_')) {
            $id = intval(str_replace('treatment_', '', $treatmentCode));
            $treatment = \App\Models\Treatment::find($id);
            $duration = $treatment->duration ?? 60;
        } elseif (str_starts_with($treatmentCode, 'package_')) {
            $id = intval(str_replace('package_', '', $treatmentCode));
            $package = \App\Models\Package::find($id);
            if ($package) {
                $duration = $package->total_duration ?? $package->treatments->sum('duration') ?? 60;
            }
        }

        return Carbon::parse($startTime)->addMinutes($duration)->format('H:i:s');
    }
}
