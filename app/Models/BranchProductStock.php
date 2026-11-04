<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchProductStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'spa_id',
        'branch_id',
        'product_id',
        'on_hand_quantity',
        'reorder_level',
        'minimum_stock',
        'maximum_stock',
    ];

    protected $casts = [
        'on_hand_quantity' => 'decimal:3',
        'reorder_level' => 'decimal:3',
        'minimum_stock' => 'decimal:3',
        'maximum_stock' => 'decimal:3',
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
}
