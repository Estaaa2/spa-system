<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'spa_id','name','brand','stock_quantity','unit','expiration_date'
    ];

    protected $casts = [
        'expiration_date' => 'date',
    ];

    public function logs() {
        return $this->hasMany(ProductLog::class);
    }
}
