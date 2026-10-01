<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Package;

class BookingConsumption extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'spa_id',
        'branch_id',
        'treatment_id',
        'package_id',
        'processed_by',
        'service_reference',
        'service_name',
        'consumed_at',
    ];

    protected $casts = [
        'consumed_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function items()
    {
        return $this->hasMany(BookingConsumptionItem::class);
    }
}
