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
    // -----------------------------------------------------------------------
    // INDEX
    // -----------------------------------------------------------------------

    public function index()
    {
        $user = Auth::user();
        $spa  = $user->spa;

        if (!$spa) {
            abort(403, 'No spa assigned to this account.');
        }

        if ($user->hasRole('owner')) {
            $branches = $spa->branches()
                ->with(['profile'])
                ->withCount('users')
                ->get();
        } else {
            $branches = $spa->branches()
                ->where('id', $user->branch_id)
                ->with(['profile'])
                ->withCount('users')
                ->get();
        }

        return view('branches.index', compact('branches', 'spa'));
    }

    // -----------------------------------------------------------------------
    // EDIT (show the edit page)
    // -----------------------------------------------------------------------

    public function edit(Branch $branch)
    {
        $user = Auth::user();
        $spa  = $user->spa;

        if (!$spa || $branch->spa_id !== $spa->id) {
            abort(403, 'Unauthorized');
        }

        $daysOfWeek     = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $operatingHours = $branch->operatingHours()->get();

        // Fill any missing days so the form always shows all 7
        foreach ($daysOfWeek as $day) {
            if (!$operatingHours->where('day_of_week', $day)->first()) {
                $operatingHours->push(new OperatingHours([
                    'day_of_week'  => $day,
                    'opening_time' => '09:00',
                    'closing_time' => '18:00',
                    'is_closed'    => false,
                ]));
            }
        }

        return view('branches.edit', compact('branch', 'spa', 'operatingHours'));
    }

    // -----------------------------------------------------------------------
    // STORE (create new branch — unchanged)
    // -----------------------------------------------------------------------

    public function store(Request $request)
    {
        $user = Auth::user();
        $spa  = $user->spa;
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        if (!$spa) {
            return response()->json(['success' => false, 'message' => 'No spa assigned to this account.'], 403);
        }

        if (($spa->business_tier ?? null) !== 'professional') {
            $branchCount = Branch::where('spa_id', $spa->id)->count();
            if ($branchCount >= 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your Basic plan allows only up to 2 branches. Upgrade your subscription to add more.',
                ], 422);
            }
        }

        $validated = $request->validate([
            'name'                       => 'required|string|max:255',
            'location'                   => 'required|string',
            'is_main'                    => 'nullable',
            'has_workforce_finance_suite' => 'nullable|boolean',
            'hours'                      => 'required|array',
            'hours.*.day_of_week'        => 'required|string',
            'hours.*.opening_time'       => 'required|date_format:H:i',
            'hours.*.closing_time'       => 'required|date_format:H:i',
            'hours.*.is_closed'          => 'required|boolean',
        ]);

        if (($spa->business_tier ?? null) !== 'professional' && $request->boolean('has_workforce_finance_suite')) {
            return response()->json([
                'success' => false,
                'message' => 'Workforce & Finance Suite is only available on the Professional tier.',
                'errors'  => ['has_workforce_finance_suite' => ['Professional tier required.']],
            ], 422);
        }

        $wantsMain     = filter_var($request->input('is_main'), FILTER_VALIDATE_BOOLEAN);
        $isFirstBranch = ($spa->branches()->count() === 0);
        if ($isFirstBranch) $wantsMain = true;
        if ($wantsMain) $spa->branches()->update(['is_main' => false]);

        $canUseSuite = (($spa->business_tier ?? null) === 'professional');

        $branch = Branch::create([
            'spa_id'                     => $spa->id,
            'name'                       => $validated['name'],
            'location'                   => $validated['location'],
            'is_main'                    => $wantsMain,
            'has_workforce_finance_suite' => $canUseSuite ? $request->boolean('has_workforce_finance_suite') : false,
        ]);

        foreach ($request->hours as $index => $hourData) {
            OperatingHours::create([
                'branch_id'    => $branch->id,
                'day_of_week'  => $hourData['day_of_week']  ?? $days[$index],
                'opening_time' => $hourData['opening_time'] ?? '09:00',
                'closing_time' => $hourData['closing_time'] ?? '18:00',
                'is_closed'    => isset($hourData['is_closed']) ? (bool) $hourData['is_closed'] : false,
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Branch created successfully.', 'branch' => $branch]);
    }

    // -----------------------------------------------------------------------
    // UPDATE — GENERAL INFO (name, is_main)
    // -----------------------------------------------------------------------

    public function updateGeneral(Request $request, Branch $branch)
    {
        $user = Auth::user();
        $spa  = $user->spa;

        if (!$spa || $branch->spa_id !== $spa->id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'is_main' => 'nullable|boolean',
        ]);

        $wantsMain = $request->boolean('is_main');

        if ($wantsMain) {
            Branch::where('spa_id', $spa->id)
                ->where('id', '!=', $branch->id)
                ->update(['is_main' => false]);
        }

        $branch->update([
            'name'    => $validated['name'],
            'is_main' => $wantsMain,
        ]);

        return redirect()
            ->to(route('branches.edit', $branch->id) . '?tab=general')
            ->with('success', 'Branch information updated.');
    }

    // -----------------------------------------------------------------------
    // UPDATE — OPERATING HOURS
    // -----------------------------------------------------------------------

    public function updateHours(Request $request, Branch $branch)
    {
        $user = Auth::user();
        $spa  = $user->spa;

        if (!$spa || $branch->spa_id !== $spa->id) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'hours'                  => 'required|array',
            'hours.*.day_of_week'    => 'required|string',
            'hours.*.opening_time'   => 'required_unless:hours.*.is_closed,1|nullable|date_format:H:i:s',
            'hours.*.closing_time'   => 'required_unless:hours.*.is_closed,1|nullable|date_format:H:i:s',
            'hours.*.is_closed'      => 'nullable|boolean',
        ]);

        foreach ($request->hours as $hourData) {
            $hourId = $hourData['id'] ?? null;
            $hour   = $hourId ? OperatingHours::find($hourId) : null;
            $hour   = $hour ?? new OperatingHours();

            $isClosed = isset($hourData['is_closed']) ? (bool) $hourData['is_closed'] : false;

            $hour->branch_id    = $branch->id;
            $hour->day_of_week  = $hourData['day_of_week']  ?? $hour->day_of_week;
            $hour->opening_time = $isClosed ? ($hour->opening_time ?? '09:00') : ($hourData['opening_time'] ?? '09:00');
            $hour->closing_time = $isClosed ? ($hour->closing_time ?? '18:00') : ($hourData['closing_time'] ?? '18:00');
            $hour->is_closed    = $isClosed;
            $hour->save();
        }

        return redirect()
            ->to(route('branches.edit', $branch->id) . '?tab=hours')
            ->with('success', 'Operating hours updated.');
    }

    // -----------------------------------------------------------------------
    // UPDATE — PUBLIC PROFILE (listing, images, amenities, map)
    // -----------------------------------------------------------------------

    public function updateProfile(Request $request, Branch $branch)
    {
        $user = Auth::user();
        $spa  = $user->spa;

        if (!$spa || $branch->spa_id !== $spa->id) {
            abort(403, 'Unauthorized');
        }

        // Profile editing is only meaningful for verified spas,
        // but we still allow the save (just force is_listed = false)
        $profileData = $request->validate([
            'cover_image'       => 'nullable|image|max:2048',
            'gallery_images.*'  => 'nullable|image|max:2048',
            'description'       => 'nullable|string',
            'phone'             => 'nullable|string|max:50',
            'address'           => 'nullable|string|max:255',
            'latitude'          => 'nullable|numeric|between:-90,90',
            'longitude'         => 'nullable|numeric|between:-180,180',
            'amenities'         => 'nullable|array',
            'amenities.*'       => 'nullable|string|max:100',
        ]);

        if ($spa->verification_status !== 'verified') {
            // Unverified: keep whatever is saved but force unlisted
            if ($branch->profile) {
                $branch->profile->update(['is_listed' => false]);
            }

            return redirect()
                ->to(route('branches.edit', $branch->id) . '?tab=profile')
                ->with('error', 'Your spa must be verified before this branch can be listed publicly.');
        }

        // Cavite-only coordinate guard
        if ($request->filled('latitude') && $request->filled('longitude')) {
            $lat = (float) $request->latitude;
            $lng = (float) $request->longitude;

            $withinCavite = $lat >= 13.983 && $lat <= 14.600
                         && $lng >= 120.850 && $lng <= 121.200;

            if (!$withinCavite) {
                return redirect()
                    ->to(route('branches.edit', $branch->id) . '?tab=profile')
                    ->withErrors(['address' => 'Pinned location must be within Cavite only.'])
                    ->withInput();
            }
        }

        $profile = $branch->profile ?? $branch->profile()->create(['branch_id' => $branch->id]);

        $profileData['is_listed'] = $request->boolean('is_listed');

        // Cover image
        if ($request->boolean('remove_cover_image')) {
            if ($profile->cover_image) Storage::disk('public')->delete($profile->cover_image);
            $profileData['cover_image'] = null;
        } elseif ($request->hasFile('cover_image')) {
            if ($profile->cover_image) Storage::disk('public')->delete($profile->cover_image);
            $profileData['cover_image'] = $request->file('cover_image')->store('branch_profiles', 'public');
        } else {
            $profileData['cover_image'] = $profile->cover_image;
        }

        // Gallery (4 slots)
        $finalGallery         = [];
        $existingGalleryInputs = $request->input('existing_gallery_images', []);
        $removeGalleryInputs   = $request->input('remove_gallery_images', []);
        $newGalleryFiles       = $request->file('gallery_images', []);

        for ($i = 0; $i < 4; $i++) {
            $existingPath = $existingGalleryInputs[$i] ?? null;
            $removeThis   = isset($removeGalleryInputs[$i]) && (int) $removeGalleryInputs[$i] === 1;
            $newFile      = $newGalleryFiles[$i] ?? null;

            if ($removeThis) {
                if ($existingPath) Storage::disk('public')->delete($existingPath);
                continue;
            }

            if ($newFile) {
                if ($existingPath) Storage::disk('public')->delete($existingPath);
                $finalGallery[$i] = $newFile->store('branch_profiles', 'public');
            } elseif ($existingPath) {
                $finalGallery[$i] = $existingPath;
            }
        }

        $profileData['gallery_images'] = array_values(array_filter($finalGallery));
        $profileData['amenities']      = $profileData['amenities'] ?? $profile->amenities ?? [];

        $profile->update($profileData);

        return redirect()
            ->to(route('branches.edit', $branch->id) . '?tab=profile')
            ->with('success', 'Public profile updated.');
    }

    // -----------------------------------------------------------------------
    // DESTROY
    // -----------------------------------------------------------------------

    public function destroy(Branch $branch)
    {
        $user = Auth::user();
        $spa  = $user->spa;

        if (!$spa || $branch->spa_id !== $spa->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($branch->is_main) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove the main branch. Set another branch as main first.',
            ], 422);
        }

        if ($branch->users()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove a branch with assigned users. Reassign users first.',
            ], 422);
        }

        $branch->delete();

        return response()->json(['success' => true, 'message' => 'Branch removed successfully.']);
    }

    // -----------------------------------------------------------------------
    // SWITCH BRANCH (sidebar switcher)
    // -----------------------------------------------------------------------

    public function switch(Request $request)
    {
        $request->validate(['branch_id' => 'required|exists:branches,id']);

        $user = Auth::user();
        $spa  = $user->spa;

        if (!$spa) {
            return response()->json(['success' => false, 'message' => 'No spa assigned.'], 403);
        }

        if (!$user->hasRole('owner')) {
            return response()->json(['success' => false, 'message' => 'You are not allowed to switch branches.'], 403);
        }

        $branch = Branch::where('spa_id', $spa->id)->where('id', $request->branch_id)->first();

        if (!$branch) {
            return response()->json(['success' => false, 'message' => 'You do not have access to this branch.'], 403);
        }

        Session::put('current_branch_id', $branch->id);

        return response()->json([
            'success' => true,
            'message' => 'Branch switched successfully.',
            'branch'  => ['id' => $branch->id, 'name' => $branch->name, 'location' => $branch->location, 'is_main' => $branch->is_main],
        ]);
    }

    // -----------------------------------------------------------------------
    // GET CURRENT BRANCH
    // -----------------------------------------------------------------------

    public function getCurrentBranch()
    {
        $user = Auth::user();
        $spa  = $user->spa;

        if (!$spa) {
            return response()->json(['success' => false, 'message' => 'No spa assigned.'], 403);
        }

        if (Session::has('current_branch_id')) {
            $branch = Branch::where('spa_id', $spa->id)->where('id', Session::get('current_branch_id'))->first();
            if ($branch) {
                return response()->json(['success' => true, 'branch' => $branch]);
            }
        }

        $branch = $spa->branches()->where('is_main', true)->first() ?: $spa->branches()->first();
        if ($branch) Session::put('current_branch_id', $branch->id);

        return response()->json(['success' => true, 'branch' => $branch]);
    }

    // -----------------------------------------------------------------------
    // SHOW (AJAX)
    // -----------------------------------------------------------------------

    public function show(Branch $branch)
    {
        $user = Auth::user();
        $spa  = $user->spa;

        if (!$spa || $branch->spa_id !== $spa->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'branch' => $branch]);
        }

        return view('branches.edit', compact('branch'));
    }
}