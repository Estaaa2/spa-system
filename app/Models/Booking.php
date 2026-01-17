<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_type',
        'treatment',
        'therapist',
        'phone',
        'fullname',
        'address',
        'email',
        'date',
        'time',
    ];
}
