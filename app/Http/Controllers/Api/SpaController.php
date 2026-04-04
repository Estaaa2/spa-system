<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Spa;
use App\Models\Branch;
use App\Models\OperatingHours;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

            return response()->json(
                $featuredSpas->map(fn($spa) => $this->formatSpa($spa))->values()
            );
        } catch (\Exception $e) {
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

            // Flatten branches - each branch becomes its own "spa" item
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

    // ── Format spa for Flutter ───────────────────────────────────────────
    private function formatSpa(Spa $spa): array
    {
        $firstBranch = $spa->branches->first();
        $profile = $firstBranch?->profile;

        $imageUrl = '';
        if ($profile && $profile->cover_image) {
            $imageUrl = url('storage/' . $profile->cover_image);
        }

        return [
            'id'          => $spa->id,
            'name'        => $spa->name,
            'location'    => $firstBranch?->location ?? '',
            'address'     => $profile?->address ?? $firstBranch?->location ?? '',
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

        return [
            'id'             => $branch->id,
            'name'           => $branch->name,
            'location'       => $branch->location ?? '',
            'is_main'        => (bool) $branch->is_main,
            'starting_price' => (float) $startingPrice,

            // Use helpers so open/close come from a non-closed day,
            // and closed_days is a Flutter-ready int array e.g. [6, 0]
            'open_time'      => $branch->getOpenTimeForApi(),
            'close_time'     => $branch->getCloseTimeForApi(),
            'closed_days'    => $branch->getClosedDaysForApi(),

            'description'    => $profile?->description ?? '',
            'address'        => $profile?->address ?? $branch->location ?? '',
            'phone'          => $profile?->phone ?? '',
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
