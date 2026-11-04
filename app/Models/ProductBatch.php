<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'spa_id',
        'branch_id',
        'product_id',
        'batch_number',
        'received_quantity',
        'remaining_quantity',
        'unit_cost',
        'manufactured_at',
        'expiration_date',
        'received_at',
    ];

    protected $casts = [
        'received_quantity' => 'decimal:3',
        'remaining_quantity' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'manufactured_at' => 'date',
        'expiration_date' => 'date',
        'received_at' => 'datetime',
    ];

    public function spa()
    {
        return $this->belongsTo(Spa::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }
}
