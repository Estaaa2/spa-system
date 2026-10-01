<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingConsumptionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_consumption_id',
        'product_id',
        'product_batch_id',
        'product_name',
        'batch_number',
        'quantity',
        'unit',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    public function consumption()
    {
        return $this->belongsTo(BookingConsumption::class, 'booking_consumption_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }
}
