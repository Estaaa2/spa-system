<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Spa;
use Illuminate\Http\Request;

class SpaController extends Controller
{
    /**
     * GET /api/spas
     * Returns all spas with their branches and treatments.
     */
    public function index(Request $request)
    {
        $query = Spa::with([
            'branches.treatments',
            'branches.operatingHours',
            'branches.profile',
            'subscriptions',
        ])->where('verification_status', 'verified');

        // Featured = professional tier with active subscription
        if ($request->has('featured')) {
            $query->where('business_tier', 'professional')
                ->whereHas('subscriptions', function ($q) {
                    $q->where('payment_status', 'paid')
                        ->where('expires_at', '>', now());
                });
        }

        // Filter by city/location
        if ($request->has('city')) {
            $city = $request->get('city');

            $caviteCities = [
                'Cavite City', 'Carmona', 'Bacoor', 'Imus', 'Dasmariñas', 'Dasmarinas',
                'General Trias', 'Kawit', 'Noveleta', 'Rosario', 'Tanza',
                'Naic', 'Trece Martires', 'Silang', 'Tagaytay', 'Alfonso',
                'Amadeo', 'General Mariano Alvarez', 'GMA', 'Mendez',
                'Magallanes', 'Maragondon', 'Ternate', 'Indang',
            ];

            $query->whereHas('branches', function ($q) use ($caviteCities) {
                $q->where(function ($inner) use ($caviteCities) {
                    foreach ($caviteCities as $city) {
                        $inner->orWhere('location', 'like', "%{$city}%");
                    }
                });
            });
        }

        if ($request->has('exclude_featured')) {
            $query->where(function ($q) {
                $q->where('business_tier', '!=', 'professional')
                    ->orWhereDoesntHave('subscriptions', function ($sq) {
                        $sq->where('payment_status', 'paid')
                            ->where('expires_at', '>', now());
                    });
            });
        }

        $spas = $query->get();

        return response()->json([
            'success' => true,
            'spas'    => $spas->map(fn($spa) => $this->formatSpa($spa))->values(),
        ]);
    }

    /**
     * GET /api/featured-spas
     * Returns featured spas only
     */
    public function featured()
    {
        try {
            $featuredSpas = Spa::with([
                'branches.treatments',
                'branches.operatingHours',
                'branches.profile',
                'subscriptions',
            ])
            ->where('verification_status', 'verified')
            ->where('business_tier', 'professional')
            ->whereHas('subscriptions', function ($q) {
                $q->where('payment_status', 'paid')
                    ->where('expires_at', '>', now());
            })
            ->get();

            $flattenedBranches = [];

            foreach ($featuredSpas as $spa) {
                foreach ($spa->branches as $branch) {
                    $profile = $branch->profile;
                    $imageUrl = $profile?->cover_image
                        ? url('storage/' . $profile->cover_image)
                        : '';

                    $flattenedBranches[] = [
                        'id'          => $spa->id,
                        'name'        => $spa->name,
                        'location'    => $branch->location ?? '',
                        'address'     => $profile?->address ?? $branch->location ?? '',
                        'contact'     => $profile?->phone ?? '',
                        'image'       => $imageUrl,
                        'tag'         => 'Featured Spa',
                        'rating'      => 0.0,
                        'reviews'     => 0,
                        'price_note'  => '',
                        'latitude'    => (float) ($profile?->latitude ?? 0),
                        'longitude'   => (float) ($profile?->longitude ?? 0),
                        'amenities'   => $profile?->amenities ?? [],
                        'branches'    => [$this->formatBranch($branch)],
                        'treatments'  => $branch->treatments
                            ->map(fn($t) => $this->formatTreatment($t))
                            ->values(),
                    ];
                }
            }

            return response()->json($flattenedBranches);

        } catch (\Exception $e) {
            \Log::error('Error in featured: ' . $e->getMessage());
            return response()->json([]);
        }
    }

    /**
     * GET /api/spas/cavite
     * Returns spas located in Cavite
     */
    public function cavite()
    {
        try {
            $caviteCities = [
                'Cavite City', 'Carmona', 'Bacoor', 'Imus', 'Dasmariñas', 'Dasmarinas',
                'General Trias', 'Kawit', 'Noveleta', 'Rosario', 'Tanza',
                'Naic', 'Trece Martires', 'Silang', 'Tagaytay', 'Alfonso',
                'Amadeo', 'General Mariano Alvarez', 'GMA', 'Mendez',
                'Magallanes', 'Maragondon', 'Ternate', 'Indang',
            ];

            $caviteSpas = Spa::with([
                'branches.treatments',
                'branches.operatingHours',
                'branches.profile',
                'subscriptions',
            ])
            ->where('verification_status', 'verified')
            ->whereHas('branches', function ($query) use ($caviteCities) {
                $query->where(function ($q) use ($caviteCities) {
                    foreach ($caviteCities as $city) {
                        $q->orWhere('location', 'like', "%{$city}%");
                    }
                });
            })
            ->get();

            return response()->json(
                $caviteSpas->map(fn($spa) => $this->formatSpa($spa))->values()
            );
        } catch (\Exception $e) {
            return response()->json([]);
        }
    }

    /**
     * GET /api/spas/other
     * Returns basic tier spas (non-featured)
     */
    public function getOtherSpas()
    {
        try {
            $spas = Spa::with([
                'branches.treatments',
                'branches.operatingHours',
                'branches.profile',
            ])
            ->where('verification_status', 'verified')
            ->where('business_tier', 'basic')
            ->get();

            $flattenedBranches = [];

            foreach ($spas as $spa) {
                foreach ($spa->branches as $branch) {
                    $profile = $branch->profile;
                    $startingPrice = $branch->treatments->min('price') ?? 0;

                    $flattenedBranches[] = [
                        'id'            => $branch->id,
                        'name'          => $branch->name,
                        'location'      => $branch->location,
                        'address'       => $profile?->address ?? $branch->location ?? '',
                        'contact'       => $profile?->phone ?? '',
                        'description'   => $profile?->description ?? $spa->description ?? '',
                        'image'         => $profile?->cover_image ? url('storage/' . $profile->cover_image) : '',
                        'tag'           => 'Verified Spa',
                        'rating'        => 0.0,
                        'reviews'       => 0,
                        'price_note'    => '',
                        'latitude'      => (float) ($profile?->latitude ?? 0),
                        'longitude'     => (float) ($profile?->longitude ?? 0),
                        'amenities'     => $profile?->amenities ?? [],
                        'starting_price'=> $startingPrice,
                        'branches'      => [$this->formatBranch($branch)],
                        'treatments'    => $branch->treatments->map(fn($t) => $this->formatTreatment($t))->values(),
                    ];
                }
            }

            return response()->json($flattenedBranches);

        } catch (\Exception $e) {
            \Log::error('Error in getOtherSpas: ' . $e->getMessage());
            return response()->json([]);
        }
    }

    /**
     * GET /api/spas/{id}
     * Returns a single spa with full details.
     */
    public function show($id)
    {
        $spa = Spa::with([
            'branches.treatments',
            'branches.operatingHours',
            'branches.profile',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'spa'     => $this->formatSpa($spa),
        ]);
    }

    /**
     * GET /api/spas/nearby
     * Returns spas near the user's location
     */
    public function nearby(Request $request)
    {
        try {
            $lat = $request->query('lat');
            $lng = $request->query('lng');

            // If lat/lng provided via query params, use them
            if ($lat !== null && $lng !== null) {
                $latitude = (float) $lat;
                $longitude = (float) $lng;
            } else {
                // Otherwise try to get from authenticated user
                $user = auth()->user();
                if (!$user || !$user->latitude || !$user->longitude) {
                    return response()->json([]);
                }
                $latitude = (float) $user->latitude;
                $longitude = (float) $user->longitude;
            }

            // Get all verified spas with branches and profiles
            $spas = Spa::with(['branches.treatments', 'branches.profile'])
                ->where('verification_status', 'verified')
                ->whereHas('branches.profile', function ($q) {
                    $q->where('is_listed', true);
                })
                ->get();

            $nearbySpas = [];

            foreach ($spas as $spa) {
                foreach ($spa->branches as $branch) {
                    $profile = $branch->profile;

                    if (!$profile || !$profile->latitude || !$profile->longitude) {
                        continue;
                    }

                    $distance = $this->calculateDistance(
                        $latitude, $longitude,
                        (float) $profile->latitude,
                        (float) $profile->longitude
                    );

                    // Only include spas within 30km
                    if ($distance <= 5) {
                        $imageUrl = $profile->cover_image
                            ? asset('storage/' . $profile->cover_image)
                            : '';

                        $nearbySpas[] = [
                            'id' => $spa->id,
                            'name' => $spa->name,
                            'location' => $branch->location ?? '',
                            'address' => $profile->address ?? '',
                            'image' => $imageUrl,
                            'distance_km' => round($distance, 1),
                            'tag' => 'Near You',
                            'latitude' => (float) $profile->latitude,
                            'longitude' => (float) $profile->longitude,
                            'branches' => [$this->formatBranch($branch)],
                            'treatments' => $branch->treatments->map(fn($t) => $this->formatTreatment($t))->values(),
                        ];
                    }
                }
            }

            // Sort by distance
            usort($nearbySpas, function($a, $b) {
                return $a['distance_km'] <=> $b['distance_km'];
            });

            // Take top 8
            $nearbySpas = array_slice($nearbySpas, 0, 8);

            return response()->json($nearbySpas);

        } catch (\Exception $e) {
            \Log::error('Nearby spas error: ' . $e->getMessage());
            return response()->json([]);
        }
    }

    /**
     * Calculate distance between two points in kilometers using Haversine formula
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        if (!$lat2 || !$lon2) return 999;

        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    // ── Format spa for Flutter ───────────────────────────────────────────
    private function formatSpa(Spa $spa): array
    {
        // Prefer main branch, fall back to first
        $primaryBranch = $spa->branches->firstWhere('is_main', true)
                        ?? $spa->branches->first();
        $profile = $primaryBranch?->profile;

        $imageUrl = '';
        if ($profile && $profile->cover_image) {
            $imageUrl = url('storage/' . $profile->cover_image);
        }

        return [
            'id'          => $spa->id,
            'name'        => $spa->name,
            'location'    => $primaryBranch?->location ?? '',
            'address'     => $profile?->address ?? $primaryBranch?->location ?? '',
            'contact'     => $profile?->phone ?? '',
            'description' => $profile?->description ?? $spa->description ?? '',
            'image'       => $imageUrl,
            'tag'         => $spa->verification_status === 'verified' ? 'Verified Spa' : 'Listed Spa',
            'rating'      => 0.0,
            'reviews'     => 0,
            'price_note'  => '',
            'latitude'    => (float) ($profile?->latitude ?? 0),
            'longitude'   => (float) ($profile?->longitude ?? 0),
            'amenities'   => $profile?->amenities ?? [],
            'branches'    => $spa->branches->map(fn($b) => $this->formatBranch($b))->values(),
            'treatments'  => $spa->branches->flatMap(fn($b) => $b->treatments ?? [])
                ->map(fn($t) => $this->formatTreatment($t))
                ->values(),
        ];
    }

    // ── Format branch for Flutter ────────────────────────────────────────
    private function formatBranch($branch): array
    {
        $profile = $branch->profile;
        $startingPrice = $branch->treatments->min('price') ?? 0;

        $imageUrl = '';
        if ($profile && $profile->cover_image) {
            $imageUrl = url('storage/' . $profile->cover_image);
        }

        $amenities = [];
        if ($profile && $profile->amenities) {
            $amenities = is_string($profile->amenities)
                ? json_decode($profile->amenities, true)
                : ($profile->amenities ?? []);
        }

        $galleryImages = [];
        if ($profile && $profile->gallery_images) {
            $galleryImages = is_array($profile->gallery_images)
                ? $profile->gallery_images
                : json_decode($profile->gallery_images, true) ?? [];
            $galleryImages = array_map(function($img) {
                return asset('storage/' . $img);
            }, $galleryImages);
        }

        return [
            'id'             => $branch->id,
            'name'           => $branch->name,
            'location'       => $branch->location ?? '',
            'is_main'        => (bool) $branch->is_main,
            'starting_price' => (float) $startingPrice,
            'open_time'      => $branch->getOpenTimeForApi(),
            'close_time'     => $branch->getCloseTimeForApi(),
            'closed_days'    => $branch->getClosedDaysForApi(),
            'description'    => $profile?->description ?? '',
            'address'        => $profile?->address ?? $branch->location ?? '',
            'phone'          => $profile?->phone ?? '',
            'gallery_images' => $galleryImages,
            'image'          => $imageUrl,
            'amenities'      => $amenities,
            'treatments'     => ($branch->treatments ?? collect())->map(
                fn($t) => $this->formatTreatment($t)
            )->values(),
        ];
    }

    // ── Format treatment for Flutter ─────────────────────────────────────
    private function formatTreatment($treatment): array
    {
        $serviceType = match ($treatment->service_type) {
            'in_branch_only'    => 'In-Branch',
            'home_service_only' => 'Home Service',
            'both'              => 'In-Branch & Home Service',
            default             => $treatment->service_type ?? 'In-Branch',
        };

        $duration = is_numeric($treatment->duration)
            ? "{$treatment->duration} mins"
            : ($treatment->duration ?? '60 mins');

        return [
            'id'          => $treatment->id,
            'name'        => $treatment->name,
            'type'        => $serviceType,
            'duration'    => $duration,
            'price'       => (float) ($treatment->price ?? 0),
            'description' => $treatment->description ?? '',
        ];
    }
}
