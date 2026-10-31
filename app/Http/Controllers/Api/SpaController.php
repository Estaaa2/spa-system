<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LandingController;
use App\Models\BranchProfile;
use App\Models\Promo;
use App\Models\Rating;
use App\Models\Spa;
use Illuminate\Http\Request;

class SpaController extends Controller
{
    private const CAVITE_CITIES = [
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

    /** The web search's city chips (welcome.js CAVITE_PLACES), in the same order. */
    private const SEARCH_PLACES = [
        'Bacoor', 'Cavite City', 'Dasmariñas', 'General Trias', 'Imus', 'Tagaytay', 'Trece Martires',
        'Alfonso', 'Amadeo', 'Carmona', 'Gen. Emilio Aguinaldo', 'Gen. Mariano Alvarez', 'Indang', 'Kawit',
        'Magallanes', 'Maragondon', 'Mendez', 'Naic', 'Noveleta', 'Rosario', 'Silang', 'Tanza', 'Ternate',
    ];

    /**
     * Relations every endpoint loads. Treatments and packages skip the
     * spa_branch scope the same way LandingController does.
     */
    private function spaRelations(): array
    {
        $noBranchScope = fn ($query) => $query->withoutGlobalScope('spa_branch');

        return [
            'branches.treatments' => $noBranchScope,
            'branches.packages'   => $noBranchScope,
            'branches.operatingHours',
            'branches.profile',
            'subscriptions',
            Spa::OWNER_PUBLIC_COLUMNS,
        ];
    }

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
     * operational: approved, inside the plan's branch limit, without
     * an expired Business Permit, and with a therapist and a service.
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

                    // Also needs a therapist and a service to be bookable.
                    $operational = $branch->isOperational()
                        && $branch->isReadyForBookings();

                    // Unset again so the spa is not nested inside
                    // each branch when the response is built.
                    $branch->unsetRelation('spa');

                    return $operational;
                })
                ->values()
        );
    }

    private function whereInCavite($query): void
    {
        $query->where(function ($q) {
            foreach (self::CAVITE_CITIES as $cityName) {
                $q->orWhere('location', 'like', "%{$cityName}%");
            }
        });
    }

    /** One card per listed, operational branch: the same unit the web's lists use. */
    private function branchCards($spas, string $tag): array
    {
        $cards = [];

        foreach ($spas as $spa) {
            $this->publicBranches($spa);

            foreach ($spa->branches as $branch) {
                $cards[] = $this->branchCard($spa, $branch, $tag);
            }
        }

        return $cards;
    }

    /**
     * GET /api/spas
     */
    public function index(Request $request)
    {
        $query = Spa::with($this->spaRelations())
            ->where('verification_status', 'verified');

        if ($request->has('city')) {
            $query->whereHas('branches', fn ($q) => $this->whereInCavite($q));
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
            $spas = Spa::with($this->spaRelations())
                ->where('verification_status', 'verified')
                ->get()
                ->filter(fn (Spa $spa) => $this->featuredSpa($spa))
                ->values();

            return response()->json($this->branchCards($spas, 'Featured Spa'));
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
            $spas = Spa::with($this->spaRelations())
                ->where('verification_status', 'verified')
                ->whereHas('branches', fn ($q) => $this->whereInCavite($q))
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
     * Publicly listed spas that are NOT featured (Basic plan), one card per
     * branch like the web's "Other Spas in Cavite".
     */
    public function getOtherSpas()
    {
        try {
            $spas = Spa::with($this->spaRelations())
                ->where('verification_status', 'verified')
                ->get()
                ->filter(fn (Spa $spa) => $this->publicSpa($spa)
                    && ! $spa->hasFeature('featured_listing'))
                ->values();

            return response()->json($this->branchCards($spas, 'Verified Spa'));
        } catch (\Throwable $e) {
            \Log::error('Error in other spas API: ' . $e->getMessage());

            return response()->json([]);
        }
    }

    /**
     * GET /api/spas/search?place=&treatment=
     *
     * Matching is done by the web's own LandingController::searchSpas(), so
     * mobile finds exactly what the web finds. Each result is then sent in
     * the app's format (with services, hours and closed days for booking).
     */
    public function search(Request $request)
    {
        $place     = trim((string) $request->query('place', ''));
        $treatment = trim((string) $request->query('treatment', ''));

        try {
            $webResponse = app(LandingController::class)->searchSpas($request);
            $webResults  = collect($webResponse->getData(true)['results'] ?? []);

            if ($webResults->isEmpty()) {
                return response()->json(['place' => $place, 'treatment' => $treatment, 'results' => []]);
            }

            $spas = Spa::with($this->spaRelations())
                ->whereIn('id', $webResults->pluck('id')->unique()->values())
                ->get()
                ->keyBy('id');

            $cards = [];

            // Same order as the web (featured first).
            foreach ($webResults as $result) {
                $spa    = $spas->get($result['id'] ?? null);
                $branch = $spa?->branches->firstWhere('id', $result['branch_id'] ?? null);

                if (! $spa || ! $branch) {
                    continue;
                }

                $cards[] = $this->branchCard(
                    $spa,
                    $branch,
                    ($result['is_featured'] ?? false) ? 'Featured Spa' : 'Verified Spa'
                );
            }

            return response()->json(['place' => $place, 'treatment' => $treatment, 'results' => $cards]);
        } catch (\Throwable $e) {
            \Log::error('Spa search API error: ' . $e->getMessage());

            return response()->json(['place' => $place, 'treatment' => $treatment, 'results' => []]);
        }
    }

    /**
     * GET /api/spas/search-suggestions
     * The web search's city chips and its treatment suggestions.
     */
    public function searchSuggestions()
    {
        try {
            $treatments = app(LandingController::class)->treatmentSuggestions();
        } catch (\Throwable $e) {
            \Log::error('Search suggestions API error: ' . $e->getMessage());
            $treatments = [];
        }

        return response()->json([
            'places'     => self::SEARCH_PLACES,
            'treatments' => $treatments,
        ]);
    }

    /**
     * GET /api/spas/{id}
     */
    public function show($id)
    {
        $spa = Spa::with($this->spaRelations())->findOrFail($id);

        if (! $this->publicSpa($spa)) {
            return response()->json([
                'success' => false,
                'message' => "This spa isn't taking bookings right now.",
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
                $user = auth('sanctum')->user();

                if (! $user || $user->latitude === null || $user->longitude === null) {
                    return response()->json([]);
                }

                $latitude = (float) $user->latitude;
                $longitude = (float) $user->longitude;
            }

            $spas = Spa::with($this->spaRelations())
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

                    $nearbySpas[] = $this->branchCard($spa, $branch, 'Near You') + [
                        'distance_km' => round($distance, 1),
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

    /**
     * One branch as a card: the spa's details plus that single branch, the
     * same unit as a card in the web's Featured / Other / Search lists.
     */
    private function branchCard(Spa $spa, $branch, string $tag): array
    {
        $profile = $branch->profile;
        $b = $this->formatBranch($branch);

        return [
            'id' => $spa->id,
            'name' => $spa->name,
            // D11: owner's first + last name and the business-verified badge.
            'owner_name' => $spa->ownerDisplayName(),
            'business_verified' => $spa->isBusinessVerified(),
            'location' => $branch->location ?? '',
            'address' => $profile?->address ?? $branch->location ?? '',
            'contact' => $profile?->phone ?? '',
            'description' => $profile?->description ?? '',
            'image' => $b['thumbnail'],
            'tag' => $tag,
            'is_featured' => $spa->hasFeature('featured_listing'),
            'can_book_online' => $spa->hasFeature('online_reservation'),
            'rating' => $b['rating_avg'] ?? 0.0,
            'reviews' => $b['rating_count'],
            'price_note' => '',
            'latitude' => (float) ($profile?->latitude ?? 0),
            'longitude' => (float) ($profile?->longitude ?? 0),
            'amenities' => $b['amenities'],
            'branches' => [$b],
            'treatments' => $b['treatments'],
            'packages' => $b['packages'],
        ];
    }

    private function formatSpa(Spa $spa): array
    {
        $primaryBranch = $spa->branches->firstWhere('is_main', true)
            ?? $spa->branches->first();

        $profile = $primaryBranch?->profile;

        return [
            'id' => $spa->id,
            'name' => $spa->name,
            // D11: owner's first + last name and the business-verified badge.
            'owner_name' => $spa->ownerDisplayName(),
            'business_verified' => $spa->isBusinessVerified(),
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
            'treatments' => $this->formatServices(
                $spa->branches->flatMap(fn ($branch) => $branch->treatments ?? []),
                'treatment'
            ),
            'packages' => $this->formatServices(
                $spa->branches->flatMap(fn ($branch) => $branch->packages ?? []),
                'package'
            ),
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

        // Same rating aggregate as the web's spa cards (ratings.spa_rating
        // joined through bookings, since ratings has no branch column).
        $ratingAgg = Rating::query()
            ->join('bookings', 'bookings.id', '=', 'ratings.booking_id')
            ->where('bookings.spa_id', $branch->spa_id)
            ->where('bookings.branch_id', $branch->id)
            ->whereNotNull('ratings.spa_rating')
            ->selectRaw('AVG(ratings.spa_rating) as avg_rating, COUNT(*) as rating_count')
            ->first();

        // Same promo lookup as the web's cards.
        $activePromo = Promo::withoutGlobalScopes()
            ->where('spa_id', $branch->spa_id)
            ->where('branch_id', $branch->id)
            ->activeToday()
            ->first();

        // Same photo payload and card thumbnail as the web.
        $photos = $profile ? BranchProfile::photoPayload($profile) : [];
        $thumbnail = $profile
            ? BranchProfile::thumbnailFor($profile)
            : BranchProfile::fallbackPhotoUrl();

        return [
            'id' => $branch->id,
            'spa_id' => $branch->spa_id,
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
            'latitude' => (float) ($profile?->latitude ?? 0),
            'longitude' => (float) ($profile?->longitude ?? 0),
            'gallery_images' => $galleryImages,
            'photos' => array_values(array_map(
                fn ($p) => ['url' => $p['url'] ?? '', 'caption' => $p['caption'] ?? null],
                $photos
            )),
            'thumbnail' => $thumbnail,
            'image' => $profile?->cover_image
                ? url('storage/' . $profile->cover_image)
                : '',
            'amenities' => $amenities ?? [],
            'rating_avg' => $ratingAgg?->avg_rating ? round((float) $ratingAgg->avg_rating, 1) : null,
            'rating_count' => (int) ($ratingAgg?->rating_count ?? 0),
            'has_promo' => (bool) $activePromo,
            'promo_label' => $activePromo?->name,
            'is_hiring' => (bool) ($profile?->is_hiring ?? false),
            'hiring_note' => $profile?->hiring_note,
            'treatments' => $this->formatServices($branch->treatments ?? collect(), 'treatment'),
            'packages' => $this->formatServices($branch->packages ?? collect(), 'package'),
        ];
    }

    private function formatServices($items, string $kind)
    {
        return collect($items)
            ->map(fn ($item) => $this->formatService($item, $kind))
            ->values();
    }

    /**
     * One treatment or package, in the shape both the booking list and the
     * web's checkout need.
     */
    private function formatService($item, string $kind): array
    {
        $price = (float) ($item->price ?? 0);

        // Same promo rule as OnlineBookingCheckoutController::store().
        $promo = $item->promos()->withoutGlobalScopes()->activeToday()->first();
        $finalPrice = $promo ? (float) $promo->discountedPrice($price) : $price;

        // Only two values exist (services/index.blade.php). Packages have no
        // service type; welcome.js treats a missing one as in_branch_only.
        $serviceType = $kind === 'treatment'
            ? ($item->service_type ?: 'in_branch_only')
            : 'in_branch_only';

        $typeLabel = $serviceType === 'in_branch_and_home'
            ? 'In-Branch & Home Service'
            : 'In-Branch';

        // Package::duration falls back to the sum of its treatments.
        $minutes = (int) ($item->duration ?: 60);

        return [
            'id' => $item->id,
            'key' => "{$kind}_{$item->id}",
            'kind' => $kind,
            'name' => $item->name,
            'type' => $typeLabel,
            'service_type' => $serviceType,
            'duration' => "{$minutes} mins",
            'duration_minutes' => $minutes,
            'price' => $price,
            'final_price' => round($finalPrice, 2),
            'has_promo' => $promo !== null,
            'description' => $item->description ?? '',
            'image_url' => $item->image_url,
            'image_url_2' => $item->image_url_2,
        ];
    }
}
