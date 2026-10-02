<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CONVERTED = 'converted';

    protected $fillable = [
        'spa_id',
        'branch_id',
        'requested_by',
        'reviewed_by',
        'reason',
        'status',
        'review_reason',
        'reviewed_at',
        'converted_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function spa()
    {
        return $this->belongsTo(Spa::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items()
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(PurchaseRequestStatusHistory::class);
    }

    public function purchaseOrder()
    {
        return $this->hasOne(PurchaseOrder::class);
    }

}
