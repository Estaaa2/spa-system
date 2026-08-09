<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'therapist_id',
        'customer_id',
        'rating',
        'comment',
        'feedback',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function therapist()
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
