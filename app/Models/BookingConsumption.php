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
        'has_shortfall',
        'shortfall_notes',
    ];

    protected $casts = [
        'consumed_at' => 'datetime',
        'has_shortfall' => 'boolean',
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

    /** Products that couldn't be fully consumed at checkout (D7). */
    public function shortItems()
    {
        return $this->hasMany(BookingConsumptionItem::class)->where('short_quantity', '>', 0);
    }

    /** Setup problems recorded at checkout, one per line. */
    public function shortfallNoteLines(): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", (string) $this->shortfall_notes))));
    }
}
