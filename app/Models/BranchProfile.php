<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BranchProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'is_listed',
        'cover_image',
        'gallery_images',
        'gallery_captions',
        'description',
        'phone',
        'address',
        'city',
        'latitude',
        'longitude',
        'amenities',
        'is_hiring',
        'hiring_note',
    ];

    protected $casts = [
        'gallery_images' => 'array',
        'gallery_captions' => 'array',
        'amenities' => 'array',
        'is_listed' => 'boolean',
        'is_hiring' => 'boolean',
        'hiring_note' => 'string',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function getTitleAttribute()
    {
        $spaName  = $this->branch->spa->name ?? 'Spa';
        $cityName = self::resolveCitySummary($this->city, $this->address, $this->branch->location ?? null);

        return $spaName . ($cityName ? ' — ' . $cityName : '');
    }

    public static function resolveCitySummary(?string $city, ?string $address, ?string $branchLocation = null): ?string
    {
        $resolvedCity = $city ?: null;

        if (!$resolvedCity && $address) {
            $cleaned = preg_replace('/,?\s*(Philippines|Calabarzon|\d{4})\s*/i', '', $address);
            $parts   = array_values(array_filter(array_map('trim', explode(',', $cleaned))));

            $resolvedCity = match (true) {
                count($parts) >= 2  => $parts[count($parts) - 2],
                count($parts) === 1 => $parts[0],
                default             => null,
            };
        }

        $resolvedCity = $resolvedCity ?: $branchLocation;

        if (!$resolvedCity) {
            return null;
        }

        return str_contains(strtolower($resolvedCity), 'cavite')
            ? $resolvedCity
            : "{$resolvedCity}, Cavite";
    }

    // This method returns the caption for a given gallery image path, if it exists.
    public function captionFor(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return $this->gallery_captions[$path]['caption'] ?? null;
    }

    // This method returns an array of photo payloads for a given branch profile, including the URL and caption for each photo. 
    // It limits the number of photos to 5 and ensures that only unique, non-null paths are included.
    public static function photoPayload(?self $profile): array
    {
        if (!$profile) {
            return [];
        }

        $paths = collect([$profile->cover_image])
            ->merge($profile->gallery_images ?? [])
            ->filter()
            ->unique()
            ->take(5)
            ->values();

        return $paths
            ->map(fn ($path) => [
                'url'     => asset('storage/' . $path),
                'caption' => $profile->captionFor($path),
            ])
            ->all();
    }

    /**
     * Shown when a spa or treatment has no photo (UI rework Phase 4). A neutral
     * placeholder that can't pass for a real photo. It lives in public/images,
     * which is in the repo; the old storage/branch_profiles/emptyspa.jpg path
     * was never committed, so every fallback was a broken image.
     *
     * Web pages get the SVG (it follows dark mode). The mobile app gets the
     * PNG, because Flutter's Image.network can't draw SVG on its own.
     */
    public static function fallbackPhotoUrl(bool $forApp = false): string
    {
        return asset($forApp ? 'images/placeholder-spa.png' : 'images/placeholder-spa.svg');
    }

    // URL of the card thumbnail: the first real photo, or the placeholder.
    public static function thumbnailFor(?self $profile, bool $forApp = false): string
    {
        $photos = self::photoPayload($profile);

        return $photos[0]['url'] ?? self::fallbackPhotoUrl($forApp);
    }
}