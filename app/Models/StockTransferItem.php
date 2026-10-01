<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_transfer_id',
        'source_product_batch_id',
        'destination_product_batch_id',
        'source_batch_number',
        'quantity',
        'expiration_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'expiration_date' => 'date',
    ];

    public function transfer()
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function sourceBatch()
    {
        return $this->belongsTo(
            ProductBatch::class,
            'source_product_batch_id'
        );
    }

    public function destinationBatch()
    {
        return $this->belongsTo(
            ProductBatch::class,
            'destination_product_batch_id'
        );
    }
}
