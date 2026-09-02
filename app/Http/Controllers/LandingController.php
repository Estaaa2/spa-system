<?php

namespace App\Http\Controllers;

use App\Models\BranchProfile;
use App\Models\Spa;
use App\Models\Treatment;
use App\Models\Package;
use App\Models\Rating;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    // This method handles the search request for spas based on the provided place and treatment.
    private function spaSearchQuery(string $place, string $treatment)
    {
        $branchMatches = function ($query) use ($place, $treatment) {
            $query->whereHas('profile', fn($p) => $p->where('is_listed', 1));

            if ($place) {
                $query->where(function ($sub) use ($place) {
                    $sub->where('location', 'LIKE', "%{$place}%")
                        ->orWhere('name', 'LIKE', "%{$place}%")
                        ->orWhereIn('spa_id', Spa::query()
                            ->where('name', 'LIKE', "%{$place}%")
                            ->select('id'));
                });
            }

            if ($treatment) {
                $query->where(function ($sub) use ($treatment) {
                    $sub->whereHas('treatments', function ($t) use ($treatment) {
                            $t->withoutGlobalScope('spa_branch')->where('name', 'LIKE', "%{$treatment}%");
                        })
                        ->orWhereHas('packages', function ($p) use ($treatment) {
                            $p->withoutGlobalScope('spa_branch')->where('name', 'LIKE', "%{$treatment}%");
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
        ->where('verification_status', 'verified')
        ->whereHas('branches', $branchMatches);
    }

    // This method retrieves treatment suggestions based on the most popular treatments and packages across all listed spas.
    private function treatmentSuggestions(): array
    {
        $treatmentNames = Treatment::withoutGlobalScope('spa_branch')
            ->whereHas('branch.profile', fn($q) => $q->where('is_listed', 1))
            ->select('name')
            ->selectRaw('COUNT(*) as cnt')
            ->groupBy('name')
            ->orderByDesc('cnt')
            ->pluck('name');

        $packageNames = Package::withoutGlobalScope('spa_branch')
            ->whereHas('branch.profile', fn($q) => $q->where('is_listed', 1))
            ->select('name')
            ->selectRaw('COUNT(*) as cnt')
            ->groupBy('name')
            ->orderByDesc('cnt')
            ->pluck('name');

        return $treatmentNames->merge($packageNames)->unique()->values()->take(40)->all();
    }

    // This method combines the results of spa searches based on place and treatment, sorts them by professional status, and builds the spa cards for display.
    private function unifiedResults(string $place, string $treatment): array
    {
        $allSpas = $this->spaSearchQuery($place, $treatment)->get();

        $sorted = $allSpas->sortByDesc(fn($spa) => $spa->isProfessional() ? 1 : 0)->values();

        return $this->buildSpaCards($sorted);
    }

    public function index(Request $request)
    {
        $place     = trim($request->input('place', ''));
        $treatment = trim($request->input('treatment', ''));

        // Falls back to the older single-field `search`/`city` params from
        // earlier iterations of this page, treating them as a Place search.
        if (!$place && !$treatment) {
            $legacy = trim($request->input('search', $request->input('city', '')));
            if ($legacy) {
                $place = $legacy;
            }
        }

        $isSearching = (bool) ($place || $treatment);

        // Always compute the default browse listing, even while searching,
        // so clearing the search restores it instantly with no round trip.
        $allSpas = Spa::with([
                'branches' => fn($q) => $q->whereHas('profile', fn($p) => $p->where('is_listed', 1))
                    ->with(['profile', 'treatments', 'packages']),
                'subscriptions',
            ])
            ->where('verification_status', 'verified')
            ->whereHas('branches', fn($q) => $q->whereHas('profile', fn($p) => $p->where('is_listed', 1)))
            ->get();

        $spas      = $allSpas->filter(fn($spa) => $spa->isProfessional());
        $basicSpas = $allSpas->filter(fn($spa) => !$spa->isProfessional());

        $results = $isSearching ? $this->unifiedResults($place, $treatment) : [];

        $treatments = Treatment::withoutGlobalScope('spa_branch')->get()->groupBy('branch_id');
        $packages   = Package::withoutGlobalScope('spa_branch')->get()->groupBy('branch_id');

        return view('welcome', compact(
            'spas', 'basicSpas', 'treatments', 'packages',
            'isSearching', 'place', 'treatment', 'results'
        ) + ['treatmentSuggestions' => $this->treatmentSuggestions()]);
    }

    // This method handles the search request for spas based on the provided place and treatment. 
    public function searchSpas(Request $request)
    {
        $place     = trim($request->input('place', ''));
        $treatment = trim($request->input('treatment', ''));

        return response()->json([
            'place'      => $place,
            'treatment'  => $treatment,
            'results'    => $this->unifiedResults($place, $treatment),
        ]);
    }

    private function buildSpaCards($spas): array
    {
        $cards = [];

        foreach ($spas as $spa) {
            $isFeatured = $spa->isProfessional();

            foreach ($spa->branches as $branch) {
                $profile = $branch->profile;
                if (!$profile?->is_listed) continue;

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
                ->join('bookings', 'bookings.id', '=', 'ratings.booking_id')
                ->where('bookings.spa_id', $spa->id)
                ->where('bookings.branch_id', $branch->id)
                ->whereNotNull('ratings.spa_rating')
                ->selectRaw('AVG(ratings.spa_rating) as avg_rating, COUNT(*) as rating_count')
                ->first();

                $cards[] = [
                    'id'              => $spa->id,
                    'name'            => $spa->name,
                    'tag'             => $isFeatured ? 'Featured Spa' : 'Listed Spa',
                    'is_featured'     => $isFeatured,
                    'branch_id'       => $branch->id,
                    'branch_name'     => $branch->name,
                    'branch_location' => $branch->location ?? '',
                    'desc'            => $profile->description ?? '',
                    'price_note'      => $lowestPrice ? number_format($lowestPrice, 2) : null,
                    'photos'          => $photos,
                    'address'         => $profile->address ?? $branch->location ?? 'Location unavailable',
                    'location_summary'=> BranchProfile::resolveCitySummary(
                        $profile->city ?? null,
                        $profile->address ?? null,
                        $branch->location ?? null
                    ) ?? 'Location unavailable',
                    'phone'           => $profile->phone ?? '',
                    'lat'             => $profile->latitude,
                    'lng'             => $profile->longitude,
                    'treatments'      => $branchTreatments,
                    'packages'        => $branchPackages,
                    'amenities'       => $profile->amenities ?? [],
                    'is_hiring'       => $profile->is_hiring ?? false,
                    'hiring_note'     => $profile->hiring_note ?? null,
                    'rating_avg'      => $ratingAgg->avg_rating ? round($ratingAgg->avg_rating, 1) : null,
                    'rating_count'    => (int) ($ratingAgg->rating_count ?? 0),
                ];
            }
        }

        return $cards;
    }

    public function nearbySpasList(Request $request)
    {
        $user = auth()->user();

        if (!$user || !$user->latitude || !$user->longitude) {
            return response()->json(['error' => 'no_location']);
        }

        $lat    = (float) $user->latitude;
        $lng    = (float) $user->longitude;
        $radius = 5; // km — adjust as needed

        // Haversine: get branch IDs within radius, ordered by distance
        $nearby = \DB::table('branch_profiles')
            ->select('branch_id', \DB::raw("
                ROUND((6371 * acos(
                    cos(radians($lat)) * cos(radians(latitude))
                    * cos(radians(longitude) - radians($lng))
                    + sin(radians($lat)) * sin(radians(latitude))
                )), 2) AS distance_km
            "))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('is_listed', true)
            ->havingRaw('distance_km < ?', [$radius])
            ->orderBy('distance_km')
            ->limit(8)
            ->get()
            ->keyBy('branch_id');

        if ($nearby->isEmpty()) {
            return response()->json([]);
        }

        $branchIds = $nearby->keys()->toArray();

        $spas = Spa::where('verification_status', 'verified')
            ->with([
                'branches' => fn($q) => $q->whereIn('id', $branchIds)->with('profile'),
            ])
            ->whereHas('branches', fn($q) => $q->whereIn('id', $branchIds))
            ->get();

        $result = [];

        foreach ($spas as $spa) {
            foreach ($spa->branches as $branch) {
                if (!isset($nearby[$branch->id])) continue;

                $profile     = $branch->profile;
                $lowestPrice = Treatment::withoutGlobalScopes()
                    ->where('spa_id', $spa->id)
                    ->where('branch_id', $branch->id)
                    ->min('price');

                // Same canonical payload as buildSpaCards() - see the note there.
                $photos = BranchProfile::photoPayload($profile);

                $treatments = Treatment::withoutGlobalScope('spa_branch')
                    ->where('branch_id', $branch->id)
                    ->where('spa_id', $spa->id)
                    ->get();

                $packages = Package::withoutGlobalScope('spa_branch')
                    ->where('branch_id', $branch->id)
                    ->where('spa_id', $spa->id)
                    ->get();

                $ratingAgg = Rating::query()
                    ->join('bookings', 'bookings.id', '=', 'ratings.booking_id')
                    ->where('bookings.spa_id', $spa->id)
                    ->where('bookings.branch_id', $branch->id)
                    ->whereNotNull('ratings.spa_rating')
                    ->selectRaw('AVG(ratings.spa_rating) as avg_rating, COUNT(*) as rating_count')
                    ->first();

                $result[] = [
                    'id'              => $spa->id,
                    'name'            => $spa->name,
                    'tag'             => 'Nearby Spa',
                    'branch_id'       => $branch->id,
                    'branch_name'     => $branch->name,
                    'branch_location' => $branch->location ?? '',
                    'desc'            => $profile->description ?? '',
                    'price_note'      => $lowestPrice ? number_format($lowestPrice, 2) : null,
                    'photos'          => $photos,
                    'address'         => $profile->address ?? $branch->location ?? 'Location unavailable',
                    'location_summary'=> BranchProfile::resolveCitySummary(
                        $profile->city ?? null,
                        $profile->address ?? null,
                        $branch->location ?? null
                    ) ?? 'Location unavailable',
                    'phone'           => $profile->phone ?? '',
                    'lat'             => $profile->latitude,
                    'lng'             => $profile->longitude,
                    'treatments'      => $treatments,
                    'packages'        => $packages,
                    'amenities'       => $profile->amenities ?? [],
                    'distance_km'     => $nearby[$branch->id]->distance_km,
                    'rating_avg'      => $ratingAgg->avg_rating ? round($ratingAgg->avg_rating, 1) : null,
                    'rating_count'    => (int) ($ratingAgg->rating_count ?? 0),
                ];
            }
        }

        // Sort by distance (cross-branch edge case)
        usort($result, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);

        return response()->json($result);
    }

    public function spaReviews(Request $request, $spaId, $branchId)
    {
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

        $reviews = $base->orderByDesc('ratings.created_at')
            ->get([
                'ratings.spa_rating as rating',
                'ratings.spa_comment as comment',
                'ratings.created_at',
                'users.first_name',
                'users.last_name',
            ])
            ->map(fn($r) => [
                'rating'  => (int) $r->rating,
                'comment' => $r->comment,
                'name'    => trim($r->first_name . ' ' . substr($r->last_name ?? '', 0, 1) . '.'),
                'date'    => $r->created_at?->format('M d, Y'),
            ]);

        return response()->json([
            'total'   => $reviews->count(),
            'counts'  => $counts,
            'reviews' => $reviews,
        ]);
    }
}