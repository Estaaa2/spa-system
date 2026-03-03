<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductLog extends Model
{
    protected $fillable = [
        'spa_id','product_id','user_id','description','logged_at'
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];

    public function product() {
        return $this->belongsTo(Product::class);
    }
}
