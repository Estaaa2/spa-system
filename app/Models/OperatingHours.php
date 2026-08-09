<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatingHours extends Model
{
    use HasFactory;

    protected $table = 'operating_hours';

    protected $fillable = [
        'branch_id',
        'day_of_week',
        'opening_time',
        'closing_time',
        'is_closed',
    ];

    protected $casts = [
        'is_closed' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Convert day name string → Flutter integer (0=Sun … 6=Sat).
     * Matches Flutter's: date.weekday % 7
     */
    public static function dayNameToInt(string $day): int
    {
        return match (strtolower(trim($day))) {
            'sunday'    => 0,
            'monday'    => 1,
            'tuesday'   => 2,
            'wednesday' => 3,
            'thursday'  => 4,
            'friday'    => 5,
            'saturday'  => 6,
            default     => -1,  // filtered out in getClosedDaysForApi()
        };
    }
}
