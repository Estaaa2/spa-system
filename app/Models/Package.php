<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'duration',
        'price',
        'included_treatments',
        'description',
    ];

    // Cast JSON field to array
    protected $casts = [
        'included_treatments' => 'array',
    ];
}
