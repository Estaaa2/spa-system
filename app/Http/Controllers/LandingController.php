<?php

namespace App\Http\Controllers;

use App\Models\BranchProfile;
use App\Models\Package;
use App\Models\Rating;
use App\Models\Spa;
use App\Models\Treatment;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    /**
     * A spa is publicly listable when it is verified, has active access,
     * and its plan includes normal public listing (Basic, Premium, Business).
     */
    private function isPubliclyListable(Spa $spa): bool
    {
        return $spa->verification_status === 'verified'
            && $spa->hasAccess()
            && $spa->hasFeature('branch_public_listing');
    }

    /**
     * A spa is Featured only when it is publicly listable AND its plan
     * includes the separate featured_listing feature (Premium, Business).
     */
    private function isFeatured(Spa $spa): bool
    {
        return $this->isPubliclyListable($spa)
            && $spa->hasFeature('featured_listing');
    }

    private $publicSpaIdsCache = null;

    private function publicSpaIds()
    {
        // index() and its helpers call this several times per request;
        // compute it once.
        return $this->publicSpaIdsCache ??= Spa::query()
            ->where('verification_status', 'verified')
            ->get()
            ->filter(fn (Spa $spa) => $this->isPubliclyListable($spa))
            ->pluck('id');
    }

    private function spaSearchQuery(string $place, string $treatment)
    {
        $publicSpaIds = $this->publicSpaIds();

        $branchMatches = function ($query) use ($place, $treatment) {
            $query->whereHas('profile', function ($profileQuery) {
                $profileQuery->where('is_listed', true);
            });

            if ($place) {
                $query->where(function ($subQuery) use ($place) {
                    $subQuery
                        ->where('location', 'like', "%{$place}%")
                        ->orWhere('name', 'like', "%{$place}%")
                        ->orWhereIn(
                            'spa_id',
                            Spa::query()
                                ->where('name', 'like', "%{$place}%")
                                ->select('id')
                        );
                });
            }

            if ($treatment) {
                $query->where(function ($subQuery) use ($treatment) {
                    $subQuery
                        ->whereHas('treatments', function ($treatmentQuery) use ($treatment) {
                            $treatmentQuery
                                ->withoutGlobalScope('spa_branch')
                                ->where('name', 'like', "%{$treatment}%");
                        })
                        ->orWhereHas('packages', function ($packageQuery) use ($treatment) {
                            $packageQuery
                                ->withoutGlobalScope('spa_branch')
                                ->where('name', 'like', "%{$treatment}%");
                        });
                });
            }
        };

        return Spa::with([
            'branches' => function ($query) use ($branchMatches) {
                $branchMatches($query);
                $query->with(['profile', 'treatments', 'packages']);
            },
            'subscriptions',
        ])
            ->whereIn('id', $publicSpaIds)
            ->whereHas('branches', $branchMatches);
    }

    private function treatmentSuggestions(): array
    {
        $publicSpaIds = $this->publicSpaIds();

        $treatmentNames = Treatment::withoutGlobalScope('spa_branch')
            ->whereIn('spa_id', $publicSpaIds)
            ->whereHas('branch.profile', function ($query) {
                $query->where('is_listed', true);
            })
            ->select('name')
            ->selectRaw('COUNT(*) as cnt')
            ->groupBy('name')
            ->orderByDesc('cnt')
            ->pluck('name');

        $packageNames = Package::withoutGlobalScope('spa_branch')
            ->whereIn('spa_id', $publicSpaIds)
            ->whereHas('branch.profile', function ($query) {
                $query->where('is_listed', true);
            })
            ->select('name')
            ->selectRaw('COUNT(*) as cnt')
            ->groupBy('name')
            ->orderByDesc('cnt')
            ->pluck('name');

        return $treatmentNames
            ->merge($packageNames)
            ->unique()
            ->values()
            ->take(40)
            ->all();
    }

    private function unifiedResults(string $place, string $treatment): array
    {
        $spas = $this->spaSearchQuery($place, $treatment)
            ->get()
            ->filter(fn (Spa $spa) => $this->isPubliclyListable($spa))
            ->values();

        // Featured spas are shown first in search results.
        return collect($this->buildSpaCards($spas))
            ->sortByDesc('is_featured')
            ->values()
            ->all();
    }

    public function index(Request $request)
    {
        $place = trim($request->input('place', ''));
        $treatment = trim($request->input('treatment', ''));

        if (! $place && ! $treatment) {
            $legacy = trim(
                $request->input(
                    'search',
                    $request->input('city', '')
                )
            );

            if ($legacy) {
                $place = $legacy;
            }
        }

        $isSearching = (bool) ($place || $treatment);
        $publicSpaIds = $this->publicSpaIds();

        $eligible = Spa::with([
            'branches' => function ($query) {
                $query
                    ->whereHas('profile', function ($profileQuery) {
                        $profileQuery->where('is_listed', true);
                    })
                    ->with(['profile', 'treatments', 'packages']);
            },
            'subscriptions',
        ])
            ->whereIn('id', $publicSpaIds)
            ->whereHas('branches', function ($query) {
                $query->whereHas('profile', function ($profileQuery) {
                    $profileQuery->where('is_listed', true);
                });
            })
            ->get()
            ->filter(fn (Spa $spa) => $this->isPubliclyListable($spa))
            ->values();

        // $spas = Featured section (Premium and Business only).
        $spas = $eligible
            ->filter(fn (Spa $spa) => $this->isFeatured($spa))
            ->values();

        // $basicSpas = normal public listing (Basic).
        // Featured spas are excluded so they don't appear twice.
        $basicSpas = $eligible
            ->reject(fn (Spa $spa) => $this->isFeatured($spa))
            ->values();

        $results = $isSearching
            ? $this->unifiedResults($place, $treatment)
            : [];

        $treatments = Treatment::withoutGlobalScope('spa_branch')
            ->whereIn('spa_id', $publicSpaIds)
            ->get()
            ->groupBy('branch_id');

        $packages = Package::withoutGlobalScope('spa_branch')
            ->whereIn('spa_id', $publicSpaIds)
            ->get()
            ->groupBy('branch_id');

        return view('welcome', compact(
            'spas',
            'basicSpas',
            'treatments',
            'packages',
            'isSearching',
            'place',
            'treatment',
            'results'
        ) + [
            'treatmentSuggestions' => $this->treatmentSuggestions(),
        ]);
    }

    public function searchSpas(Request $request)
    {
        $place = trim($request->input('place', ''));
        $treatment = trim($request->input('treatment', ''));

        return response()->json([
            'place' => $place,
            'treatment' => $treatment,
            'results' => $this->unifiedResults($place, $treatment),
        ]);
    }

    private function buildSpaCards($spas): array
    {
        $cards = [];

        foreach ($spas as $spa) {
            if (! $this->isPubliclyListable($spa)) {
                continue;
            }

            $featured = $this->isFeatured($spa);

            foreach ($spa->branches as $branch) {
                $profile = $branch->profile;

                if (! $profile?->is_listed) {
                    continue;
                }

                $lowestPrice = Treatment::withoutGlobalScopes()
                    ->where('spa_id', $spa->id)
                    ->where('branch_id', $branch->id)
                    ->min('price');

                $photos = BranchProfile::photoPayload($profile);

                $branchTreatments = Treatment::withoutGlobalScope('spa_branch')
                    ->where('branch_id', $branch->id)
                    ->where('spa_id', $spa->id)
                    ->get();

                $branchPackages = Package::withoutGlobalScope('spa_branch')
                    ->where('branch_id', $branch->id)
                    ->where('spa_id', $spa->id)
                    ->get();

                $ratingAgg = Rating::query()
                    ->join(
                        'bookings',
                        'bookings.id',
                        '=',
                        'ratings.booking_id'
                    )
                    ->where('bookings.spa_id', $spa->id)
                    ->where('bookings.branch_id', $branch->id)
                    ->whereNotNull('ratings.spa_rating')
                    ->selectRaw(
                        'AVG(ratings.spa_rating) as avg_rating, COUNT(*) as rating_count'
                    )
                    ->first();

                $cards[] = [
                    'id' => $spa->id,
                    'name' => $spa->name,
                    'tag' => $featured ? 'Featured Spa' : 'Spa',
                    'can_book_online' => $spa->hasFeature('online_reservation'),
                    'is_featured' => $featured,
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name,
                    'branch_location' => $branch->location ?? '',
                    'desc' => $profile->description ?? '',
                    'price_note' => $lowestPrice
                        ? number_format($lowestPrice, 2)
                        : null,
                    'photos' => $photos,
                    'address' => $profile->address
                        ?? $branch->location
                        ?? 'Location unavailable',
                    'location_summary' => BranchProfile::resolveCitySummary(
                        $profile->city ?? null,
                        $profile->address ?? null,
                        $branch->location ?? null
                    ) ?? 'Location unavailable',
                    'phone' => $profile->phone ?? '',
                    'lat' => $profile->latitude,
                    'lng' => $profile->longitude,
                    'treatments' => $branchTreatments,
                    'packages' => $branchPackages,
                    'amenities' => $profile->amenities ?? [],
                    'is_hiring' => $profile->is_hiring ?? false,
                    'hiring_note' => $profile->hiring_note ?? null,
                    'rating_avg' => $ratingAgg->avg_rating
                        ? round($ratingAgg->avg_rating, 1)
                        : null,
                    'rating_count' => (int) ($ratingAgg->rating_count ?? 0),
                ];
            }
        }

        return $cards;
    }

    public function nearbySpasList(Request $request)
    {
        $validated = $request->validate([
            'lat' => 'nullable|numeric|between:-90,90|required_with:lng',
            'lng' => 'nullable|numeric|between:-180,180|required_with:lat',
        ]);

        $user = auth()->user();

        if (
            isset($validated['lat'], $validated['lng'])
        ) {
            $lat = (float) $validated['lat'];
            $lng = (float) $validated['lng'];
        } elseif (
            $user
            && $user->latitude !== null
            && $user->longitude !== null
        ) {
            $lat = (float) $user->latitude;
            $lng = (float) $user->longitude;
        } else {
            return response()->json([]);
        }

        $nearby = \DB::table('branch_profiles')
            ->select('branch_id')
            ->selectRaw(
                'ROUND(6371 * ACOS(
                    COS(RADIANS(?))
                    * COS(RADIANS(latitude))
                    * COS(RADIANS(longitude) - RADIANS(?))
                    + SIN(RADIANS(?))
                    * SIN(RADIANS(latitude))
                ), 2) AS distance_km',
                [$lat, $lng, $lat]
            )
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('is_listed', true)
            ->havingRaw('distance_km <= ?', [5])
            ->orderBy('distance_km')
            ->limit(8)
            ->get()
            ->keyBy('branch_id');

        if ($nearby->isEmpty()) {
            return response()->json([]);
        }

        $branchIds = $nearby->keys()->toArray();

        $spas = Spa::with([
            'branches' => function ($query) use ($branchIds) {
                $query
                    ->whereIn('id', $branchIds)
                    ->with(['profile', 'treatments', 'packages']);
            },
            'subscriptions',
        ])
            ->whereIn('id', $this->publicSpaIds())
            ->whereHas('branches', function ($query) use ($branchIds) {
                $query->whereIn('id', $branchIds);
            })
            ->get()
            ->filter(fn (Spa $spa) => $this->isPubliclyListable($spa))
            ->values();

        $result = [];

        foreach ($spas as $spa) {
            $featured = $this->isFeatured($spa);

            foreach ($spa->branches as $branch) {
                if (! isset($nearby[$branch->id])) {
                    continue;
                }

                $profile = $branch->profile;

                if (! $profile || ! $profile->is_listed) {
                    continue;
                }

                $lowestPrice = Treatment::withoutGlobalScopes()
                    ->where('spa_id', $spa->id)
                    ->where('branch_id', $branch->id)
                    ->min('price');

                $result[] = [
                    'id' => $spa->id,
                    'name' => $spa->name,
                    'tag' => 'Nearby Spa',
                    'can_book_online' => $spa->hasFeature('online_reservation'),
                    'is_featured' => $featured,
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name,
                    'branch_location' => $branch->location ?? '',
                    'desc' => $profile->description ?? '',
                    'price_note' => $lowestPrice
                        ? number_format($lowestPrice, 2)
                        : null,
                    'photos' => BranchProfile::photoPayload($profile),
                    'address' => $profile->address
                        ?? $branch->location
                        ?? 'Location unavailable',
                    'location_summary' => BranchProfile::resolveCitySummary(
                        $profile->city ?? null,
                        $profile->address ?? null,
                        $branch->location ?? null
                    ) ?? 'Location unavailable',
                    'phone' => $profile->phone ?? '',
                    'lat' => $profile->latitude,
                    'lng' => $profile->longitude,
                    'treatments' => $branch->treatments,
                    'packages' => $branch->packages,
                    'amenities' => $profile->amenities ?? [],
                    'is_hiring' => $profile->is_hiring ?? false,
                    'hiring_note' => $profile->hiring_note ?? null,
                    'distance_km' => $nearby[$branch->id]->distance_km,
                ];
            }
        }

        usort(
            $result,
            fn ($a, $b) => $a['distance_km'] <=> $b['distance_km']
        );

        return response()->json($result);
    }

    public function spaReviews(Request $request, $spaId, $branchId)
    {
        $spa = Spa::find($spaId);

        if (! $spa || ! $this->isPubliclyListable($spa)) {
            return response()->json([
                'total' => 0,
                'counts' => [],
                'reviews' => [],
                'message' => 'This spa is not currently available for public listing.',
            ], 404);
        }

        $base = Rating::query()
            ->join('bookings', 'bookings.id', '=', 'ratings.booking_id')
            ->join('users', 'users.id', '=', 'ratings.customer_id')
            ->where('bookings.spa_id', $spaId)
            ->where('bookings.branch_id', $branchId)
            ->whereNotNull('ratings.spa_rating');

        $countsRaw = (clone $base)
            ->selectRaw('ratings.spa_rating as rating, COUNT(*) as total')
            ->groupBy('ratings.spa_rating')
            ->pluck('total', 'rating');

        $counts = [];

        for ($i = 5; $i >= 1; $i--) {
            $counts[$i] = (int) ($countsRaw[$i] ?? 0);
        }

        $reviews = $base
            ->orderByDesc('ratings.created_at')
            ->get([
                'ratings.id as rating_id',
                'ratings.spa_rating as rating',
                'ratings.spa_comment as comment',
                'ratings.created_at',
                'users.first_name',
                'users.last_name',
            ]);

        $photosByRating = \App\Models\RatingPhoto::whereIn(
            'rating_id',
            $reviews->pluck('rating_id')
        )
            ->get()
            ->groupBy('rating_id');

        $reviews = $reviews->map(fn ($review) => [
            'rating' => (int) $review->rating,
            'comment' => $review->comment,
            'name' => trim(
                $review->first_name
                . ' '
                . substr($review->last_name ?? '', 0, 1)
                . '.'
            ),
            'date' => $review->created_at?->format('M d, Y'),
            'photos' => ($photosByRating[$review->rating_id] ?? collect())
                ->map(fn ($photo) => $photo->url)
                ->values(),
        ]);

        return response()->json([
            'total' => $reviews->count(),
            'counts' => $counts,
            'reviews' => $reviews,
        ]);
    }
}
