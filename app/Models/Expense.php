<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'spa_id',
        'branch_id',
        'requested_by',
        'reviewed_by',
        'title',
        'description',
        'amount',
        'status',       // pending | on_review | accepted | rejected
        'review_notes',
        'reviewed_at',
    ];

    protected $casts = [
        'amount'      => 'float',
        'reviewed_at' => 'datetime',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function spa()
    {
        return $this->belongsTo(Spa::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending'   => 'Pending',
            'on_review' => 'On Review',
            'accepted'  => 'Accepted',
            'rejected'  => 'Rejected',
            default     => ucfirst($this->status),
        };
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeNeedsAction($query)
    {
        return $query->whereIn('status', ['pending', 'on_review']);
    }
}