<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'spa_id', 'branch_id',
        'leave_type', 'start_date', 'end_date', 'reason',
        'status', 'rejection_reason', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function user()       { return $this->belongsTo(User::class, 'user_id'); }
    public function reviewedBy() { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function scopePending($q)  { return $q->where('status', 'pending'); }
    public function scopeApproved($q) { return $q->where('status', 'approved'); }

    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }

    /** Every calendar date this leave covers, inclusive, as Y-m-d strings. */
    public function dateRange(): array
    {
        $dates  = [];
        $cursor = Carbon::parse($this->start_date);
        $end    = Carbon::parse($this->end_date);

        while ($cursor->lte($end)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * User IDs on APPROVED leave covering the given date, for a branch.
     * Used to exclude staff from being auto-assigned new bookings while
     * they're on leave — called from BookingController, the online
     * checkout controller, and the PayMongo webhook.
     */
    public static function approvedUserIdsOnDate(int $spaId, int $branchId, string $date): array
    {
        return static::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->pluck('user_id')
            ->all();
    }
}