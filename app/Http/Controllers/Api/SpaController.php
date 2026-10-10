<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Spa;
use Illuminate\Http\Request;

class SpaController extends Controller
{
    /**
     * Only verified spas with active access and the public-listing feature
     * may be returned by public API endpoints.
     */
    private function publicSpa(Spa $spa): bool
    {
        return $spa->verification_status === 'verified'
            && $spa->hasAccess()
            && $spa->hasFeature('branch_public_listing');
    }

        /**
     * Featured = publicly listed AND plan has featured_listing (Premium, Business).
     */
    private function featuredSpa(Spa $spa): bool
    {
        return $this->publicSpa($spa)
            && $spa->hasFeature('featured_listing');
    }

    /**
     * Keep only branches that are explicitly listed publicly AND are
     * operational: approved, inside the plan's branch limit, and without
     * an expired Business Permit.
     */
    private function publicBranches(Spa $spa): void
    {
        $spa->setRelation(
            'branches',
            $spa->branches
                ->filter(function ($branch) use ($spa) {
                    if (! $branch->profile?->is_listed) {
                        return false;
                    }

                    $branch->setRelation('spa', $spa);

                    $operational = $branch->isOperational();

                    // Unset again so the spa is not nested inside
                    // each branch when the response is built.
                    $branch->unsetRelation('spa');

                    return $operational;
                })
                ->values()
        );
    }

    /**
     * GET /api/spas
     */
    public function index(Request $request)
    {
        $query = Spa::with([
            'branches.treatments',
            'branches.operatingHours',
            'branches.profile',
            'subscriptions',
        ])->where('verification_status', 'verified');

        if ($request->has('city')) {
            $city = $request->get('city');

            $caviteCities = [
                'Cavite City',
                'Carmona',
                'Bacoor',
                'Imus',
                'Dasmariñas',
                'Dasmarinas',
                'General Trias',
                'Kawit',
                'Noveleta',
                'Rosario',
                'Tanza',
                'Naic',
                'Trece Martires',
                'Silang',
                'Tagaytay',
                'Alfonso',
                'Amadeo',
                'General Mariano Alvarez',
                'GMA',
                'Mendez',
                'Magallanes',
                'Maragondon',
                'Ternate',
                'Indang',
            ];

            $query->whereHas('branches', function ($q) use ($caviteCities) {
                $q->where(function ($inner) use ($caviteCities) {
                    foreach ($caviteCities as $cityName) {
                        $inner->orWhere(
                            'location',
                            'like',
                            "%{$cityName}%"
                        );
                    }
                });
            });
        }

        $spas = $query->get()
            ->filter(fn (Spa $spa) => $this->publicSpa($spa))
            ->filter(function (Spa $spa) use ($request) {
                                if ($request->has('featured')) {
                    return $spa->hasFeature('featured_listing');
                }

                if ($request->has('exclude_featured')) {
                    return ! $spa->hasFeature('featured_listing');
                }

                return true;
            })
            ->values();

        $spas->each(fn (Spa $spa) => $this->publicBranches($spa));

        return response()->json([
            'success' => true,
            'spas' => $spas
                ->map(fn (Spa $spa) => $this->formatSpa($spa))
                ->values(),
        ]);
    }

        /**
     * GET /api/featured-spas
     */
    public function featured()
    {
        try {
            $spas = Spa::with([
                'branches.treatments',
                'branches.operatingHours',
                'branches.profile',
                'subscriptions',
            ])
                ->where('verification_status', 'verified')
                ->get()
                ->filter(fn (Spa $spa) => $this->featuredSpa($spa))
                ->values();

            $flattenedBranches = [];

            foreach ($spas as $spa) {
                $this->publicBranches($spa);

                foreach ($spa->branches as $branch) {
                    $profile = $branch->profile;

                    $flattenedBranches[] = [
                        'id' => $spa->id,
                        'name' => $spa->name,
                        'location' => $branch->location ?? '',
                        'address' => $profile?->address
                            ?? $branch->location
                            ?? '',
                        'contact' => $profile?->phone ?? '',
                        'image' => $profile?->cover_image
                            ? url('storage/' . $profile->cover_image)
                            : '',
                        'tag' => 'Featured Spa',
                        'is_featured' => true,
                        'can_book_online' => $spa->hasFeature('online_reservation'),
                        'rating' => 0.0,
                        'reviews' => 0,
                        'price_note' => '',
                        'latitude' => (float) ($profile?->latitude ?? 0),
                        'longitude' => (float) ($profile?->longitude ?? 0),
                        'amenities' => $profile?->amenities ?? [],
                        'branches' => [$this->formatBranch($branch)],
                        'treatments' => $branch->treatments
                            ->map(fn ($t) => $this->formatTreatment($t))
                            ->values(),
                    ];
                }
            }

            return response()->json($flattenedBranches);
        } catch (\Throwable $e) {
            \Log::error('Error in featured spas API: ' . $e->getMessage());

            return response()->json([]);
        }
    }

    /**
     * GET /api/spas/cavite
     */
    public function cavite()
    {
        try {
            $caviteCities = [
                'Cavite City',
                'Carmona',
                'Bacoor',
                'Imus',
                'Dasmariñas',
                'Dasmarinas',
                'General Trias',
                'Kawit',
                'Noveleta',
                'Rosario',
                'Tanza',
                'Naic',
                'Trece Martires',
                'Silang',
                'Tagaytay',
                'Alfonso',
                'Amadeo',
                'General Mariano Alvarez',
                'GMA',
                'Mendez',
                'Magallanes',
                'Maragondon',
                'Ternate',
                'Indang',
            ];

            $spas = Spa::with([
                'branches.treatments',
                'branches.operatingHours',
                'branches.profile',
                'subscriptions',
            ])
                ->where('verification_status', 'verified')
                ->whereHas('branches', function ($query) use ($caviteCities) {
                    $query->where(function ($q) use ($caviteCities) {
                        foreach ($caviteCities as $cityName) {
                            $q->orWhere(
                                'location',
                                'like',
                                "%{$cityName}%"
                            );
                        }
                    });
                })
                ->get()
                ->filter(fn (Spa $spa) => $this->publicSpa($spa))
                ->values();

            $spas->each(fn (Spa $spa) => $this->publicBranches($spa));

            return response()->json(
                $spas
                    ->map(fn (Spa $spa) => $this->formatSpa($spa))
                    ->values()
            );
        } catch (\Throwable $e) {
            \Log::error('Error in Cavite spas API: ' . $e->getMessage());

            return response()->json([]);
        }
    }

    /**
     * GET /api/spas/other
     *
     * Basic/non-public spas must no longer be exposed by this endpoint.
     */
        /**
     * GET /api/spas/other
     *
     * Publicly listed spas that are NOT featured (Basic plan).
     */
    public function getOtherSpas()
    {
        try {
            $spas = Spa::with([
                'branches.treatments',
                'branches.operatingHours',
                'branches.profile',
                'subscriptions',
            ])
                ->where('verification_status', 'verified')
                ->get()
                ->filter(fn (Spa $spa) => $this->publicSpa($spa)
                    && ! $spa->hasFeature('featured_listing'))
                ->values();

            $spas->each(fn (Spa $spa) => $this->publicBranches($spa));

            return response()->json(
                $spas
                    ->filter(fn (Spa $spa) => $spa->branches->isNotEmpty())
                    ->map(fn (Spa $spa) => $this->formatSpa($spa))
                    ->values()
            );
        } catch (\Throwable $e) {
            \Log::error('Error in other spas API: ' . $e->getMessage());

            return response()->json([]);
        }
    }

    /**
     * GET /api/spas/{id}
     */
    public function show($id)
    {
        $spa = Spa::with([
            'branches.treatments',
            'branches.operatingHours',
            'branches.profile',
            'subscriptions',
        ])->findOrFail($id);

        if (! $this->publicSpa($spa)) {
            return response()->json([
                'success' => false,
                'message' => 'This spa is not currently available for public listing.',
            ], 404);
        }

        $this->publicBranches($spa);

        return response()->json([
            'success' => true,
            'spa' => $this->formatSpa($spa),
        ]);
    }

    /**
     * GET /api/spas/nearby
     */
    public function nearby(Request $request)
    {
        try {
            $lat = $request->query('lat');
            $lng = $request->query('lng');

            if ($lat !== null && $lng !== null) {
                $latitude = (float) $lat;
                $longitude = (float) $lng;
            } else {
                $user = auth()->user();

                if (! $user || $user->latitude === null || $user->longitude === null) {
                    return response()->json([]);
                }

                $latitude = (float) $user->latitude;
                $longitude = (float) $user->longitude;
            }

            $spas = Spa::with([
                'branches.treatments',
                'branches.profile',
                'subscriptions',
            ])
                ->where('verification_status', 'verified')
                ->whereHas('branches.profile', function ($q) {
                    $q->where('is_listed', true);
                })
                ->get()
                ->filter(fn (Spa $spa) => $this->publicSpa($spa))
                ->values();

            $nearbySpas = [];

            foreach ($spas as $spa) {
                $this->publicBranches($spa);

                foreach ($spa->branches as $branch) {
                    $profile = $branch->profile;

                    if (
                        ! $profile
                        || $profile->latitude === null
                        || $profile->longitude === null
                    ) {
                        continue;
                    }

                    $distance = $this->calculateDistance(
                        $latitude,
                        $longitude,
                        (float) $profile->latitude,
                        (float) $profile->longitude
                    );

                    if ($distance > 5) {
                        continue;
                    }

                    $nearbySpas[] = [
                        'id' => $spa->id,
                        'name' => $spa->name,
                        'location' => $branch->location ?? '',
                        'address' => $profile->address ?? '',
                        'image' => $profile->cover_image
                            ? asset('storage/' . $profile->cover_image)
                            : '',
                        'distance_km' => round($distance, 1),
                        'tag' => 'Near You',
                        'latitude' => (float) $profile->latitude,
                        'longitude' => (float) $profile->longitude,
                        'branches' => [$this->formatBranch($branch)],
                        'treatments' => $branch->treatments
                            ->map(fn ($t) => $this->formatTreatment($t))
                            ->values(),
                    ];
                }
            }

            usort(
                $nearbySpas,
                fn ($a, $b) => $a['distance_km'] <=> $b['distance_km']
            );

            return response()->json(array_slice($nearbySpas, 0, 8));
        } catch (\Throwable $e) {
            \Log::error('Nearby spas error: ' . $e->getMessage());

            return response()->json([]);
        }
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        if ($lat2 === null || $lon2 === null) {
            return 999;
        }

        $earthRadius = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function formatSpa(Spa $spa): array
    {
        $primaryBranch = $spa->branches->firstWhere('is_main', true)
            ?? $spa->branches->first();

        $profile = $primaryBranch?->profile;

        return [
            'id' => $spa->id,
            'name' => $spa->name,
            'location' => $primaryBranch?->location ?? '',
            'address' => $profile?->address
                ?? $primaryBranch?->location
                ?? '',
            'contact' => $profile?->phone ?? '',
            'description' => $profile?->description
                ?? $spa->description
                ?? '',
            'image' => $profile?->cover_image
                ? url('storage/' . $profile->cover_image)
                : '',
            'tag' => 'Verified Spa',
            'is_featured' => $spa->hasFeature('featured_listing'),
            'can_book_online' => $spa->hasFeature('online_reservation'),
            'rating' => 0.0,
            'reviews' => 0,
            'price_note' => '',
            'latitude' => (float) ($profile?->latitude ?? 0),
            'longitude' => (float) ($profile?->longitude ?? 0),
            'amenities' => $profile?->amenities ?? [],
            'branches' => $spa->branches
                ->map(fn ($branch) => $this->formatBranch($branch))
                ->values(),
            'treatments' => $spa->branches
                ->flatMap(fn ($branch) => $branch->treatments ?? [])
                ->map(fn ($treatment) => $this->formatTreatment($treatment))
                ->values(),
        ];
    }

    private function formatBranch($branch): array
    {
        $profile = $branch->profile;
        $startingPrice = $branch->treatments->min('price') ?? 0;

        $amenities = [];

        if ($profile?->amenities) {
            $amenities = is_string($profile->amenities)
                ? json_decode($profile->amenities, true)
                : $profile->amenities;
        }

        $galleryImages = [];

        if ($profile?->gallery_images) {
            $galleryImages = is_array($profile->gallery_images)
                ? $profile->gallery_images
                : json_decode($profile->gallery_images, true) ?? [];

            $galleryImages = array_map(
                fn ($image) => asset('storage/' . $image),
                $galleryImages
            );
        }

        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'location' => $branch->location ?? '',
            'is_main' => (bool) $branch->is_main,
            'starting_price' => (float) $startingPrice,
            'open_time' => $branch->getOpenTimeForApi(),
            'close_time' => $branch->getCloseTimeForApi(),
            'closed_days' => $branch->getClosedDaysForApi(),
            'description' => $profile?->description ?? '',
            'address' => $profile?->address ?? $branch->location ?? '',
            'phone' => $profile?->phone ?? '',
            'gallery_images' => $galleryImages,
            'image' => $profile?->cover_image
                ? url('storage/' . $profile->cover_image)
                : '',
            'amenities' => $amenities,
            'treatments' => ($branch->treatments ?? collect())
                ->map(fn ($treatment) => $this->formatTreatment($treatment))
                ->values(),
        ];
    }

    private function formatTreatment($treatment): array
    {
        $serviceType = match ($treatment->service_type) {
            'in_branch_only' => 'In-Branch',
            'home_service_only' => 'Home Service',
            'both' => 'In-Branch & Home Service',
            default => $treatment->service_type ?? 'In-Branch',
        };

        $duration = is_numeric($treatment->duration)
            ? "{$treatment->duration} mins"
            : ($treatment->duration ?? '60 mins');

        return [
            'id' => $treatment->id,
            'name' => $treatment->name,
            'type' => $serviceType,
            'duration' => $duration,
            'price' => (float) ($treatment->price ?? 0),
            'description' => $treatment->description ?? '',
        ];
    }
}