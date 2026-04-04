<?php
// File path: app/Models/StaffBranchDeployment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffBranchDeployment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'staff_id',
        'spa_id',
        'requested_by',
        'reviewed_by',
        'from_branch_id',
        'to_branch_id',
        'start_date',
        'end_date',
        'is_permanent',
        'status',
        'rejection_reason',
        'notes',
        'deployed_at',
        'reverted_at',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'is_permanent' => 'boolean',
        'deployed_at'  => 'datetime',
        'reverted_at'  => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function spa(): BelongsTo
    {
        return $this->belongsTo(Spa::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Returns true if this deployment is currently blocking a new request.
     * A staff can't have a new request if one of these is open.
     */
    public function isBlocking(): bool
    {
        return in_array($this->status, ['pending', 'approved', 'active']);
    }
}
