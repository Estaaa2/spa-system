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

    /** The customer didn't come. Set by staff, only after the start time (UI rework Phase 3). */
    public const NO_SHOW = 'no_show';

    /** Statuses that end a booking; it can't be edited, processed or cancelled after these. */
    public const FINISHED_STATUSES = ['completed', 'cancelled', self::NO_SHOW];

    /**
     * Staff-facing words for each status, in one place. Blade reads this
     * directly and the polling scripts get it through @json, so a status
     * never shows as a raw value like "No_show".
     */
    public const STATUS_LABELS = [
        'reserved'    => 'Reserved',
        'pending'     => 'Pending',
        'ongoing'     => 'Ongoing',
        'completed'   => 'Completed',
        'cancelled'   => 'Cancelled',
        self::NO_SHOW => 'No-show',
    ];

    public static function statusLabel(?string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', (string) $status));
    }

    /** Staff may mark a no-show only once the appointment has started and it isn't finished. */
    public function canBeMarkedNoShow(): bool
    {
        $start = $this->startsAt();

        return in_array($this->status, ['pending', 'ongoing'], true)
            && $start !== null && now()->gte($start);
    }

    /**
     * Staff checkout is the only way a booking becomes completed (booking
     * lifecycle fix D1). Allowed once the session was started (ongoing), or
     * once the start time has passed for a booking nobody started yet (Q3-A:
     * checkout from pending records start and finish in one step). Stays
     * allowed after the end time — the clock never closes a booking.
     */
    public function canBeCheckedOut(): bool
    {
        if ($this->status === 'ongoing') {
            return true;
        }

        $start = $this->startsAt();

        return in_array($this->status, ['reserved', 'pending'], true)
            && $start !== null && now()->gte($start);
    }

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

    /** When the appointment starts, in app time. Null when the date or time is missing. */
    public function startsAt(): ?Carbon
    {
        if (! $this->appointment_date || blank($this->start_time)) {
            return null;
        }

        return Carbon::parse($this->appointment_date->toDateString().' '.$this->start_time);
    }

    /**
     * A customer can ask to reschedule only before the appointment starts.
     * 'pending' (start time reached, set by the clock) and 'ongoing' (started
     * by staff) both mean it has begun, so only 'reserved' qualifies, and the start time is checked too in case the
     * status sync hasn't run yet. Web, the app API and the details screen all
     * read this one rule.
     */
    public function canRequestReschedule(): bool
    {
        $start = $this->startsAt();

        return $this->status === 'reserved' && $start !== null && now()->lt($start);
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
