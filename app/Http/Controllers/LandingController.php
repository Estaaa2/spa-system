<?php

namespace App\Http\Controllers;

use App\Models\Spa;
use App\Models\Treatment;
use App\Models\Package;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    /**
     * Shared filter: Place matches a branch's own location/name or its
     * owning spa's name (so "Serenity" still finds a spa by name even
     * though the Place segment mainly surfaces city suggestions).
     * Treatment matches a branch's treatment OR package names. Both are
     * independent AND'd constraints - specifying both narrows to spas
     * matching *both*, not either.
     */
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

    /**
     * Frequency-sorted, deduplicated treatment + package names across all
     * listed branches. Powers the Treatment segment's suggestion dropdown.
     * There's no category/type taxonomy in the schema yet, so this is
     * literal names, not curated categories - revisit once categories exist.
     */
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

    /**
     * One merged, relevance-ish list instead of two tiers. Featured
     * (professional-tier) spas still sort first when they have matches -
     * preserving the value of their subscription - but no longer as a
     * separate section a visitor must scroll past when it's empty.
     */
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

    /**
     * Live refine endpoint (no page reload) fired when "Search" is clicked.
     * Same matching + sort rules as index(), returns one unified array of
     * pre-built card payloads shaped exactly like the data-spa attribute
     * already used by the Blade views, so openSpaModal() and the booking
     * flow in welcome.js work unchanged for these results too.
     */
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
        $fallback = asset('storage/branch_profiles/emptyspa.jpg');
        $cards    = [];

        foreach ($spas as $spa) {
            $isFeatured = $spa->isProfessional();

            foreach ($spa->branches as $branch) {
                $profile = $branch->profile;
                if (!$profile?->is_listed) continue;

                $lowestPrice = Treatment::withoutGlobalScopes()
                    ->where('spa_id', $spa->id)
                    ->where('branch_id', $branch->id)
                    ->min('price');

                $coverPhoto = !empty($profile->cover_image)
                    ? asset('storage/' . $profile->cover_image)
                    : $fallback;

                $galleryPhotos = collect($profile->gallery_images ?? [])
                    ->filter()
                    ->map(fn($img) => asset('storage/' . $img))
                    ->values();

                $photos = collect([$coverPhoto])
                    ->merge($galleryPhotos)
                    ->take(5)->pad(5, $fallback)->values()->toArray();

                $branchTreatments = Treatment::withoutGlobalScope('spa_branch')
                    ->where('branch_id', $branch->id)
                    ->where('spa_id', $spa->id)
                    ->get();

                $branchPackages = Package::withoutGlobalScope('spa_branch')
                    ->where('branch_id', $branch->id)
                    ->where('spa_id', $spa->id)
                    ->get();

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
                    'phone'           => $profile->phone ?? '',
                    'lat'             => $profile->latitude,
                    'lng'             => $profile->longitude,
                    'treatments'      => $branchTreatments,
                    'packages'        => $branchPackages,
                    'amenities'       => $profile->amenities ?? [],
                    'is_hiring'       => $profile->is_hiring ?? false,
                    'hiring_note'     => $profile->hiring_note ?? null,
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

        $fallback = asset('storage/branch_profiles/emptyspa.jpg');
        $result   = [];

        foreach ($spas as $spa) {
            foreach ($spa->branches as $branch) {
                if (!isset($nearby[$branch->id])) continue;

                $profile     = $branch->profile;
                $lowestPrice = Treatment::withoutGlobalScopes()
                    ->where('spa_id', $spa->id)
                    ->where('branch_id', $branch->id)
                    ->min('price');

                $coverPhoto = !empty($profile?->cover_image)
                    ? asset('storage/' . $profile->cover_image)
                    : $fallback;

                $galleryPhotos = collect($profile->gallery_images ?? [])
                    ->filter()
                    ->map(fn($img) => asset('storage/' . $img))
                    ->values();

                $photos = collect([$coverPhoto])
                    ->merge($galleryPhotos)
                    ->take(5)->pad(5, $fallback)->values()->toArray();

                $treatments = Treatment::withoutGlobalScope('spa_branch')
                    ->where('branch_id', $branch->id)
                    ->where('spa_id', $spa->id)
                    ->get();

                $packages = Package::withoutGlobalScope('spa_branch')
                    ->where('branch_id', $branch->id)
                    ->where('spa_id', $spa->id)
                    ->get();

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
                    'phone'           => $profile->phone ?? '',
                    'lat'             => $profile->latitude,
                    'lng'             => $profile->longitude,
                    'treatments'      => $treatments,
                    'packages'        => $packages,
                    'amenities'       => $profile->amenities ?? [],
                    'distance_km'     => $nearby[$branch->id]->distance_km,
                ];
            }
        }

        // Sort by distance (cross-branch edge case)
        usort($result, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);

        return response()->json($result);
    }
}