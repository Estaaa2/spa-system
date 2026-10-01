<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TreatmentRecipeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'treatment_id',
        'product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
