<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\OperatingHours;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class BranchController extends Controller
{
    /**
     * Display a listing of the branches.
     */
    public function index()
    {
        $user = Auth::user();
        $spa = $user->spa;

        if (!$spa) {
            abort(403, 'No spa assigned to this account.');
        }

        if ($user->hasRole('owner')) {
            // Owner sees all branches
            $branches = $spa->branches()
                ->with(['profile'])
                ->withCount('users')
                ->get();
        } else {
            // Manager / Staff see ONLY their assigned branch
            $branches = $spa->branches()
                ->where('id', $user->branch_id)
                ->with(['profile'])
                ->withCount('users')
                ->get();
        }

        return view('branches.index', compact('branches', 'spa'));
    }

    /**
     * Show the form for editing the specified branch.
     */
    public function edit(Branch $branch)
    {
        $user = Auth::user();
        $spa = $user->spa;

        if (!$spa || $branch->spa_id !== $spa->id) {
            abort(403, 'Unauthorized');
        }

        $daysOfWeek = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
        $operatingHours = $branch->operatingHours()->get();

        // If some days are missing (new branch), fill them
        foreach ($daysOfWeek as $day) {
            if (!$operatingHours->where('day_of_week', $day)->first()) {
                $operatingHours->push(new \App\Models\OperatingHours([
                    'day_of_week' => $day,
                    'opening_time' => '09:00',
                    'closing_time' => '18:00',
                    'is_closed' => false,
                ]));
            }
        }

        return view('branches.edit', compact('branch', 'spa', 'operatingHours'));
    }

    /**
     * Store a newly created branch.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $spa = $user->spa;
        $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

        if (!$spa) {
            return response()->json([
                'success' => false,
                'message' => 'No spa assigned to this account.'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string',
            'is_main' => 'nullable',
            'has_workforce_finance_suite' => 'nullable|boolean',
            'hours' => 'required|array',
            'hours.*.day_of_week' => 'required|string',
            'hours.*.opening_time' => 'required|date_format:H:i',
            'hours.*.closing_time' => 'required|date_format:H:i',
            'hours.*.is_closed' => 'required|boolean',
        ]);

        if ($spa->business_tier !== 'professional' && $request->boolean('has_workforce_finance_suite')) {
            return response()->json([
                'success' => false,
                'message' => 'Workforce & Finance Suite is only available for spas on the Professional business tier.',
                'errors' => [
                    'has_workforce_finance_suite' => [
                        'Workforce & Finance Suite is only available for spas on the Professional business tier.',
                    ],
                ],
            ], 422);
        }

        $wantsMain = filter_var($request->input('is_main'), FILTER_VALIDATE_BOOLEAN);

        $isFirstBranch = ($spa->branches()->count() === 0);
        if ($isFirstBranch) {
            $wantsMain = true;
        }

        if ($wantsMain) {
            $spa->branches()->update(['is_main' => false]);
        }

        $canUseSuite = ($spa->business_tier === 'professional');

        $branch = Branch::create([
            'spa_id' => $spa->id,
            'name' => $validated['name'],
            'location' => $validated['location'],
            'is_main' => $wantsMain,
            'has_workforce_finance_suite' => $canUseSuite
                ? $request->boolean('has_workforce_finance_suite')
                : false,
        ]);

        // Save operating hours from the request
        if ($request->has('hours')) {
            foreach ($request->hours as $index => $hourData) {
                OperatingHours::create([
                    'branch_id' => $branch->id,
                    'day_of_week' => $hourData['day_of_week'] ?? $days[$index],
                    'opening_time' => $hourData['opening_time'] ?? '09:00',
                    'closing_time' => $hourData['closing_time'] ?? '18:00',
                    'is_closed' => isset($hourData['is_closed']) ? (bool)$hourData['is_closed'] : false,
                ]);
            }
        } else {
            // fallback to default hours if no data sent
            foreach ($days as $day) {
                OperatingHours::create([
                    'branch_id' => $branch->id,
                    'day_of_week' => $day,
                    'opening_time' => '09:00',
                    'closing_time' => '18:00',
                    'is_closed' => false,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Branch created successfully',
            'branch' => $branch
        ]);
    }

    /**
     * Update the specified branch.
     */
    public function update(Request $request, Branch $branch)
    {
        $user = Auth::user();
        $spa = $user->spa;

        if (!$spa || $branch->spa_id !== $spa->id) {
            abort(403, 'Unauthorized');
        }

        // ----- Branch Management Fields -----
        $validatedBranch = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string',
            'is_main' => 'nullable',
            'has_workforce_finance_suite' => 'nullable|boolean',
        ]);

        if ($spa->business_tier !== 'professional' && $request->boolean('has_workforce_finance_suite')) {
            return back()->withErrors([
                'has_workforce_finance_suite' => 'Workforce & Finance Suite is only available for spas on the Professional business tier.',
            ])->withInput();
        }

        $wantsMain = $request->boolean('is_main');

        if ($wantsMain) {
            Branch::where('spa_id', $spa->id)
                ->where('id', '!=', $branch->id)
                ->update(['is_main' => false]);
        }

        $canUseSuite = ($spa->business_tier === 'professional');

        $branch->update([
            'name' => $validatedBranch['name'],
            'location' => $validatedBranch['location'],
            'is_main' => $wantsMain,
            'has_workforce_finance_suite' => $canUseSuite
                ? $request->boolean('has_workforce_finance_suite')
                : false,
        ]);

        // ----- Branch Profile Fields -----
        if ($spa->verification_status === 'verified') {

            // Validate all profile fields
            $profileData = $request->validate([
                'cover_image' => 'nullable|image|max:2048',
                'gallery_images.*' => 'nullable|image|max:2048',
                'description' => 'nullable|string',
                'phone' => 'nullable|string|max:50',
                'address' => 'nullable|string|max:255',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'amenities' => 'nullable|array',
                'amenities.*' => 'nullable|string|max:100',
            ]);

            $profile = $branch->profile ?? $branch->profile()->create([
                'branch_id' => $branch->id,
            ]);
            
            $profileData['is_listed'] = $request->boolean('is_listed');

            // Handle cover image
            if ($request->boolean('remove_cover_image')) {
                if ($profile->cover_image) {
                    Storage::disk('public')->delete($profile->cover_image);
                }
                $profileData['cover_image'] = null;
            } elseif ($request->hasFile('cover_image')) {
                if ($profile->cover_image) {
                    Storage::disk('public')->delete($profile->cover_image);
                }
                $profileData['cover_image'] = $request->file('cover_image')->store('branch_profiles', 'public');
            } else {
                $profileData['cover_image'] = $profile->cover_image;
            }

            // Handle gallery images as 4 slots
            $finalGallery = [];
            $existingGalleryInputs = $request->input('existing_gallery_images', []);
            $removeGalleryInputs = $request->input('remove_gallery_images', []);
            $newGalleryFiles = $request->file('gallery_images', []);

            for ($i = 0; $i < 4; $i++) {
                $existingPath = $existingGalleryInputs[$i] ?? null;
                $removeThis = isset($removeGalleryInputs[$i]) && (int)$removeGalleryInputs[$i] === 1;
                $newFile = $newGalleryFiles[$i] ?? null;

                if ($removeThis) {
                    if ($existingPath) {
                        Storage::disk('public')->delete($existingPath);
                    }
                    continue;
                }

                if ($newFile) {
                    if ($existingPath) {
                        Storage::disk('public')->delete($existingPath);
                    }

                    $finalGallery[$i] = $newFile->store('branch_profiles', 'public');
                } elseif ($existingPath) {
                    $finalGallery[$i] = $existingPath;
                }
            }

            $profileData['gallery_images'] = array_values(array_filter($finalGallery));

            $profileData['amenities'] = $profileData['amenities'] ?? $profile->amenities ?? [];

            $profile->update($profileData);
        } else {
            if ($branch->profile) {
                $branch->profile->update([
                    'is_listed' => false,
                ]);
            }
        }

        // ----- Operating Hours -----
        if ($request->has('hours')) {
            foreach ($request->hours as $hourData) {
                $hourId = $hourData['id'] ?? null;
                $hour = $hourId ? \App\Models\OperatingHours::find($hourId) : null;
                $hour = $hour ?? new \App\Models\OperatingHours();
                $hour->branch_id = $branch->id;
                $hour->day_of_week = $hourData['day_of_week'] ?? $hour->day_of_week;
                $hour->opening_time = $hourData['opening_time'] ?? '09:00';
                $hour->closing_time = $hourData['closing_time'] ?? '18:00';
                $hour->is_closed = isset($hourData['is_closed']) ? (bool)$hourData['is_closed'] : false;
                $hour->save();
            }
        }

        return redirect()->route('branches.index')->with('success', 'Branch updated successfully!');
    }

    /**
     * Remove the specified branch.
     */
    public function destroy(Branch $branch)
    {
        $user = Auth::user();
        $spa = $user->spa;

        if (!$spa || $branch->spa_id !== $spa->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($branch->is_main) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove the main branch. Please set another branch as main first.'
            ], 422);
        }

        if ($branch->users()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove branch with assigned users. Reassign users first.'
            ], 422);
        }

        $branch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Branch removed successfully'
        ]);
    }

    /**
     * Switch the current branch for the authenticated user.
     */
    public function switch(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id'
        ]);

        $user = Auth::user();
        $spa = $user->spa;

        if (!$spa) {
            return response()->json([
                'success' => false,
                'message' => 'No spa assigned.'
            ], 403);
        }

        // 🔥 IMPORTANT: Only owner can switch branches
        if (! $user->hasRole('owner')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to switch branches.'
            ], 403);
        }

        $branch = Branch::where('spa_id', $spa->id)
            ->where('id', $request->branch_id)
            ->first();

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this branch.'
            ], 403);
        }

        Session::put('current_branch_id', $branch->id);

        return response()->json([
            'success' => true,
            'message' => 'Branch switched successfully',
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'location' => $branch->location,
                'is_main' => $branch->is_main
            ]
        ]);
    }

    /**
     * Get the current selected branch.
     */
    public function getCurrentBranch()
    {
        $user = Auth::user();
        $spa = $user->spa;

        if (!$spa) {
            return response()->json([
                'success' => false,
                'message' => 'No spa assigned.'
            ], 403);
        }

        if (Session::has('current_branch_id')) {
            $branch = Branch::where('spa_id', $spa->id)
                ->where('id', Session::get('current_branch_id'))
                ->first();

            if ($branch) {
                return response()->json([
                    'success' => true,
                    'branch' => $branch
                ]);
            }
        }

        $branch = $spa->branches()->where('is_main', true)->first()
            ?: $spa->branches()->first();

        if ($branch) {
            Session::put('current_branch_id', $branch->id);
        }

        return response()->json([
            'success' => true,
            'branch' => $branch
        ]);
    }

    /**
     * Show branch (for modal AJAX fetch).
     */
    public function show(Branch $branch)
    {
        $user = Auth::user();
        $spa = $user->spa;

        if (!$spa || $branch->spa_id !== $spa->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'branch' => $branch
            ]);
        }

        return view('branches.edit', compact('branch'));
    }
}