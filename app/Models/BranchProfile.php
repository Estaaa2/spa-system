<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'is_listed',
        'cover_image',
        'gallery_images',
        'description',
        'phone',
        'address',
        'latitude',
        'longitude',
        'amenities',
    ];

    protected $casts = [
        'gallery_images' => 'array',
        'amenities' => 'array',
        'is_listed' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function getTitleAttribute()
    {
        $spaName = $this->branch->spa->name ?? 'Spa';
        $address = $this->address ?? $this->branch->location ?? '';

        // Extract city from address
        $cityName = '';
        if ($address) {
            $parts = explode(',', $address);
            // Use second to last part for city if available
            $cityName = trim(count($parts) >= 2 ? $parts[count($parts)-2] : end($parts));
        }

        return $spaName . ($cityName ? ' — ' . $cityName : '');
    }
}