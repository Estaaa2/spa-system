<?php

namespace App\Http\Controllers;

use App\Models\Spa;
use App\Models\Treatment;
use App\Models\Package;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function index(Request $request)
    {
        $city = trim($request->input('city', ''));

        $baseQuery = fn() => Spa::with([
            'branches' => function ($query) use ($city) {
                $query->whereHas('profile', function ($q) {
                    $q->where('is_listed', 1);
                })
                ->when($city, function ($q) use ($city) {
                    $q->where('location', 'LIKE', "%{$city}%");
                })
                ->with(['profile', 'treatments', 'packages']);
            },
            'subscriptions',
        ])
        ->where('verification_status', 'verified')
        ->whereHas('branches', function ($q) use ($city) {
            $q->whereHas('profile', fn($p) => $p->where('is_listed', 1));
            if ($city) {
                $q->where('location', 'LIKE', "%{$city}%");
            }
        });

        $allSpas   = $baseQuery()->get();
        $spas      = $allSpas->filter(fn($spa) => $spa->isProfessional());
        $basicSpas = $allSpas->filter(fn($spa) => !$spa->isProfessional());

        $treatments = Treatment::withoutGlobalScope('spa_branch')->get()->groupBy('branch_id');
        $packages   = Package::withoutGlobalScope('spa_branch')->get()->groupBy('branch_id');

        return view('welcome', compact('spas', 'basicSpas', 'treatments', 'packages', 'city'));
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
