<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'arrival_code' => 'encrypted',
        'arrival_attempts' => 'integer',
        'arrival_locked_at' => 'datetime',
        'arrival_confirmed_at' => 'datetime',
    ];

    /**
     * The arrival code is for the customer only (staff verification D7). Hidden
     * so it can never reach a staff-facing JSON response by accident.
     */
    protected $hidden = [
        'arrival_code',
    ];

    /** Statuses where the booking is still coming up or running. */
    public const ACTIVE_STATUSES = ['reserved', 'pending', 'ongoing'];

    protected static function booted(): void
    {
        // Every online home-service booking gets a 4-digit arrival code (Q41).
        static::saving(function (Booking $booking) {
            if ($booking->requiresArrivalCode() && blank($booking->arrival_code)) {
                $booking->arrival_code = self::newArrivalCode();
            }
        });

        // Q43: tell the customer when their therapist is assigned or changed,
        // so the photo they check at the door is the right one.
        static::updated(function (Booking $booking) {
            if (! $booking->wasChanged('therapist_id') || ! $booking->therapist_id) {
                return;
            }

            $wasUnassigned = $booking->getOriginal('therapist_id') === null;

            \Illuminate\Support\Facades\DB::afterCommit(fn () => app(\App\Services\Bookings\BookingNotifier::class)
                ->therapistChanged($booking->fresh(), $wasUnassigned));
        });
    }

    public static function newArrivalCode(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    /** Online + home service: the customer gets an arrival code (D2, D7). */
    public function requiresArrivalCode(): bool
    {
        return $this->booking_source === 'online' && $this->service_type === 'in_home';
    }

    /** Creates the code for older bookings made before this feature. */
    public function ensureArrivalCode(): void
    {
        if ($this->requiresArrivalCode() && blank($this->arrival_code)) {
            $this->forceFill(['arrival_code' => self::newArrivalCode()])->saveQuietly();
        }
    }

    public function isArrivalConfirmed(): bool
    {
        return $this->arrival_confirmed_at !== null;
    }

    public function arrivalConfirmedBy()
    {
        return $this->belongsTo(User::class, 'arrival_confirmed_by');
    }

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

    public function treatments()
    {
        return $this->belongsTo(Treatment::class, 'treatment', 'id');
    }

    public function consumption()
    {
        return $this->hasOne(BookingConsumption::class);
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
        // This method is used to get the treatment name for a booking.
        // It checks if the treatment field starts with 'treatment_' or 'package_'
        // and retrieves the corresponding name from the Treatment or Package model,
        // respectively. If the treatment is not found, it returns 'Unknown Treatment'
        // or 'Unknown Package'. If the treatment field does not start with either prefix,
        //  it simply returns the treatment value as is.
        if (str_starts_with($this->treatment, 'treatment_')) {
            $id = (int) str_replace('treatment_', '', $this->treatment);
            $treatment = \App\Models\Treatment::withoutGlobalScopes()->find($id);
            return $treatment ? $treatment->name : 'Unknown Treatment';
        }

        if (str_starts_with($this->treatment, 'package_')) {
            $id = (int) str_replace('package_', '', $this->treatment);
            $package = \App\Models\Package::withoutGlobalScopes()->find($id);
            return $package ? $package->name : 'Unknown Package';
        }

        return $this->treatment;
    }

    public static function calculateEndTime($treatmentCode, $startTime)
    {
        $duration = 60;

        // Determine the duration based on the treatment code prefix.
        if (str_starts_with($treatmentCode, 'treatment_')) {
            $id = (int) str_replace('treatment_', '', $treatmentCode);
            $treatment = \App\Models\Treatment::withoutGlobalScopes()->find($id);
            $duration = $treatment->duration ?? 60;
        } elseif (str_starts_with($treatmentCode, 'package_')) {
            $id = (int) str_replace('package_', '', $treatmentCode);
            $package = \App\Models\Package::withoutGlobalScopes()->find($id);
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

    public function reassignmentRequests()
    {
        return $this->hasMany(ReassignmentRequest::class);
    }

    public function latestReassignmentRequest()
    {
        return $this->hasOne(ReassignmentRequest::class)->latestOfMany();
    }

    public function rating(): HasOne
    {
        return $this->hasOne(Rating::class, 'booking_id');
    }

    public function hasRating(): bool
    {
        return $this->rating()->exists();
    }

}
