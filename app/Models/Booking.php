<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'spa_id',
        'branch_id',
        'created_by_user_id',
        'customer_user_id',
        'status',
        'payment_status',
        'amount_paid',
        'total_amount',
        'balance_amount',
        'service_type',
        'treatment',
        'booking_source',
        'therapist_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_address',
        'appointment_date',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'start_time' => 'string',
        'end_time' => 'string',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function therapist()
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function spa()
    {
        return $this->belongsTo(Spa::class);
    }

    public function getTherapistNameAttribute()
    {
        return $this->therapist ? $this->therapist->name : 'Not Assigned';
    }

    public function getServiceTypeLabelAttribute()
    {
        return $this->service_type === 'in_home' ? 'In Home' : 'In Branch';
    }

    public function getTreatmentLabelAttribute()
    {
        if (str_starts_with($this->treatment, 'treatment_')) {
            $id = (int) str_replace('treatment_', '', $this->treatment);
            $treatment = \App\Models\Treatment::find($id);
            return $treatment ? $treatment->name : 'Unknown Treatment';
        }

        if (str_starts_with($this->treatment, 'package_')) {
            $id = (int) str_replace('package_', '', $this->treatment);
            $package = \App\Models\Package::find($id);
            return $package ? $package->name : 'Unknown Package';
        }

        return $this->treatment;
    }

    public static function calculateEndTime($treatmentCode, $startTime)
    {
        $duration = 60;

        if (str_starts_with($treatmentCode, 'treatment_')) {
            $id = (int) str_replace('treatment_', '', $treatmentCode);
            $treatment = \App\Models\Treatment::find($id);
            $duration = $treatment->duration ?? 60;
        } elseif (str_starts_with($treatmentCode, 'package_')) {
            $id = (int) str_replace('package_', '', $treatmentCode);
            $package = \App\Models\Package::find($id);
            if ($package) {
                $duration = $package->total_duration ?? 60;
            }
        }

        return Carbon::parse($startTime)->addMinutes($duration)->format('H:i:s');
    }

    public function rescheduleRequests()
    {
        return $this->hasMany(RescheduleRequest::class);
    }

    public function latestRescheduleRequest()
    {
        return $this->hasOne(RescheduleRequest::class)->latestOfMany();
    }
}
