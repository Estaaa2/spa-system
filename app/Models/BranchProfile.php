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
        $city = $this->branch->profile->address ?? $this->branch->location ?? '';

        // Extract the city from the address string if possible
        $cityParts = explode(',', $city);
        $cityName = trim(end($cityParts)); // last part of the address

        return $spaName . ($cityName ? ' - ' . $cityName : '');
    }
}