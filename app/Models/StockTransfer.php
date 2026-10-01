<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'spa_id',
        'source_branch_id',
        'destination_branch_id',
        'product_id',
        'quantity',
        'unit',
        'status',
        'requested_by',
        'completed_by',
        'notes',
        'transferred_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'transferred_at' => 'datetime',
    ];

    public function spa()
    {
        return $this->belongsTo(Spa::class);
    }

    public function sourceBranch()
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function destinationBranch()
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function items()
    {
        return $this->hasMany(StockTransferItem::class);
    }
}
