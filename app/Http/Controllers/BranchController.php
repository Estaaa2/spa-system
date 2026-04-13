<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\OperatingHours;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BranchController extends Controller
{
    // -----------------------------------------------------------------------
    // INDEX
    // -----------------------------------------------------------------------

    public function index()
    {
        $user = Auth::user();
        $spa  = $user->spa;

        if (!$spa) abort(403, 'No spa assigned to this account.');

        if ($user->hasRole('owner')) {
            $branches = $spa->branches()->with(['profile'])->withCount('users')->get();
        } else {
            $branches = $spa->branches()->where('id', $user->branch_id)->with(['profile'])->withCount('users')->get();
        }

        if (!Session::has('current_branch_id') && $branches->isNotEmpty()) {
            $main = $branches->firstWhere('is_main', true) ?? $branches->first();
            Session::put('current_branch_id', $main->id);
        }

        return view('branches.index', compact('branches', 'spa'));
    }

    // -----------------------------------------------------------------------
    // EDIT (show page)
    // -----------------------------------------------------------------------

    public function edit(Branch $branch)
    {
        $user = Auth::user();
        $spa  = $user->spa;

        if (!$spa || $branch->spa_id !== $spa->id) abort(403, 'Unauthorized');

        $daysOfWeek     = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $operatingHours = $branch->operatingHours()->get();

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
    // STORE
    // -----------------------------------------------------------------------

    public function store(Request $request)
    {
        $user = Auth::user();
        $spa  = $user->spa;
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        if (!$spa) {
            return response()->json(['success' => false, 'message' => 'No spa assigned.'], 403);
        }

        if (($spa->business_tier ?? null) !== 'professional') {
            if (Branch::where('spa_id', $spa->id)->count() >= 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your Basic plan allows only up to 2 branches. Upgrade to add more.',
                ], 422);
            }
        }

        $validated = $request->validate([
            'name'                        => 'required|string|max:255',
            'location'                    => 'required|string',
            'is_main'                     => 'nullable',
            'has_workforce_finance_suite' => 'nullable|boolean',
            'hours'                       => 'required|array',
            'hours.*.day_of_week'         => 'required|string',
            'hours.*.opening_time'        => 'required|date_format:H:i',
            'hours.*.closing_time'        => 'required|date_format:H:i',
            'hours.*.is_closed'           => 'required|boolean',
        ]);

        if (($spa->business_tier ?? null) !== 'professional' && $request->boolean('has_workforce_finance_suite')) {
            return response()->json([
                'success' => false,
                'message' => 'Workforce & Finance Suite is only available on the Professional tier.',
            ], 422);
        }

        $wantsMain     = filter_var($request->input('is_main'), FILTER_VALIDATE_BOOLEAN);
        $isFirstBranch = ($spa->branches()->count() === 0);
        if ($isFirstBranch) $wantsMain = true;
        if ($wantsMain) $spa->branches()->update(['is_main' => false]);

        $canUseSuite = (($spa->business_tier ?? null) === 'professional');

        $branch = Branch::create([
            'spa_id'                      => $spa->id,
            'name'                        => $validated['name'],
            'location'                    => $validated['location'],
            'is_main'                     => $wantsMain,
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
    // UPDATE — GENERAL INFO
    // -----------------------------------------------------------------------

    public function updateGeneral(Request $request, Branch $branch)
    {
        $user = Auth::user();
        $spa  = $user->spa;

        if (!$spa || $branch->spa_id !== $spa->id) abort(403, 'Unauthorized');

        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|max:255',
            'is_main' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->to(route('branches.edit', $branch->id) . '?tab=general')
                ->withErrors($validator, 'general')
                ->withInput();
        }

        $wantsMain = $request->boolean('is_main');

        if ($wantsMain) {
            Branch::where('spa_id', $spa->id)->where('id', '!=', $branch->id)->update(['is_main' => false]);
        }

        $branch->update(['name' => $request->input('name'), 'is_main' => $wantsMain]);

        return redirect()
            ->to(route('branches.edit', $branch->id) . '?tab=general')
            ->with('tab_success', 'general');
    }

    public function updateHours(Request $request, Branch $branch)
    {
        $user = Auth::user();
        $spa  = $user->spa;

        if (!$spa || $branch->spa_id !== $spa->id) abort(403, 'Unauthorized');

        $validator = Validator::make($request->all(), [
            'hours'                => 'required|array',
            'hours.*.day_of_week'  => 'required|string',
            'hours.*.is_closed'    => 'nullable|boolean',
            'hours.*.opening_time' => ['nullable', 'date_format:H:i'],
            'hours.*.closing_time' => ['nullable', 'date_format:H:i'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->to(route('branches.edit', $branch->id) . '?tab=hours')
                ->withErrors($validator, 'hours')
                ->withInput();
        }

        // Server-side time-range check: closing must be strictly after opening.
        $rangeErrors = [];

        foreach ($request->input('hours', []) as $hourData) {
            $isClosed = isset($hourData['is_closed']) && (bool) $hourData['is_closed'];

            if (
                !$isClosed
                && !empty($hourData['opening_time'])
                && !empty($hourData['closing_time'])
                && $hourData['closing_time'] <= $hourData['opening_time']
            ) {
                $day           = $hourData['day_of_week'] ?? 'Unknown day';
                $rangeErrors[] = "Closing time must be after opening time for {$day}.";
            }
        }

        if (!empty($rangeErrors)) {
            return redirect()
                ->to(route('branches.edit', $branch->id) . '?tab=hours')
                ->withErrors($rangeErrors, 'hours')
                ->withInput();
        }

        // Persist
        foreach ($request->input('hours', []) as $hourData) {
            $hourId   = $hourData['id'] ?? null;
            $hour     = $hourId ? OperatingHours::find($hourId) : null;
            $hour     = $hour ?? new OperatingHours();
            $isClosed = isset($hourData['is_closed']) && (bool) $hourData['is_closed'];

            $hour->branch_id   = $branch->id;
            $hour->day_of_week = $hourData['day_of_week'] ?? $hour->day_of_week;
            $hour->is_closed   = $isClosed;

            // For closed days: keep existing times so they are restored when
            // the day is re-opened later.
            if (!$isClosed) {
                $hour->opening_time = $hourData['opening_time'] ?? $hour->opening_time ?? '09:00';
                $hour->closing_time = $hourData['closing_time'] ?? $hour->closing_time ?? '18:00';
            }

            $hour->save();
        }

        return redirect()
            ->to(route('branches.edit', $branch->id) . '?tab=hours')
            ->with('tab_success', 'hours');
    }

    // -----------------------------------------------------------------------
    // UPDATE — PUBLIC PROFILE
    // -----------------------------------------------------------------------

    public function updateProfile(Request $request, Branch $branch)
    {
        $user = Auth::user();
        $spa  = $user->spa;

        if (!$spa || $branch->spa_id !== $spa->id) abort(403, 'Unauthorized');

        if ($spa->verification_status !== 'verified') {
            if ($branch->profile) $branch->profile->update(['is_listed' => false]);

            return redirect()
                ->to(route('branches.edit', $branch->id) . '?tab=profile')
                ->withErrors(['Your spa must be verified before this branch can be listed publicly.'], 'profile');
        }

        $validator = Validator::make($request->all(), [
            'cover_image'      => 'nullable|image|max:2048',
            'gallery_images.*' => 'nullable|image|max:2048',
            'description'      => 'nullable|string',
            'phone'            => 'nullable|string|max:50',
            'address'          => 'nullable|string|max:255',
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180',
            'amenities'        => 'nullable|array',
            'amenities.*'      => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->to(route('branches.edit', $branch->id) . '?tab=profile')
                ->withErrors($validator, 'profile')
                ->withInput();
        }

        // Cavite-only guard
        if ($request->filled('latitude') && $request->filled('longitude')) {
            $lat = (float) $request->latitude;
            $lng = (float) $request->longitude;

            if (!($lat >= 13.983 && $lat <= 14.600 && $lng >= 120.850 && $lng <= 121.200)) {
                return redirect()
                    ->to(route('branches.edit', $branch->id) . '?tab=profile')
                    ->withErrors(['Pinned location must be within Cavite only.'], 'profile')
                    ->withInput();
            }
        }

        $profile = $branch->profile ?? $branch->profile()->create(['branch_id' => $branch->id]);

        $profileData              = $validator->validated();
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
        $finalGallery          = [];
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
            ->with('tab_success', 'profile');
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
            return response()->json(['success' => false, 'message' => 'Cannot remove the main branch. Set another branch as main first.'], 422);
        }

        if ($branch->users()->exists()) {
            return response()->json(['success' => false, 'message' => 'Cannot remove a branch with assigned users. Reassign users first.'], 422);
        }

        $branch->delete();

        return response()->json(['success' => true, 'message' => 'Branch removed successfully.']);
    }

    // -----------------------------------------------------------------------
    // SWITCH / GET CURRENT / SHOW — unchanged
    // -----------------------------------------------------------------------

    public function switch(Request $request)
    {
        $request->validate(['branch_id' => 'required|exists:branches,id']);
        $user = Auth::user();
        $spa  = $user->spa;

        if (!$spa) return response()->json(['success' => false, 'message' => 'No spa assigned.'], 403);
        if (!$user->hasRole('owner')) return response()->json(['success' => false, 'message' => 'Not allowed.'], 403);

        $branch = Branch::where('spa_id', $spa->id)->where('id', $request->branch_id)->first();
        if (!$branch) return response()->json(['success' => false, 'message' => 'Branch not found.'], 403);

        Session::put('current_branch_id', $branch->id);

        return response()->json([
            'success' => true, 'message' => 'Branch switched.',
            'branch'  => ['id' => $branch->id, 'name' => $branch->name, 'location' => $branch->location, 'is_main' => $branch->is_main],
        ]);
    }

    public function getCurrentBranch()
    {
        $user = Auth::user();
        $spa  = $user->spa;

        if (!$spa) return response()->json(['success' => false, 'message' => 'No spa assigned.'], 403);

        if (Session::has('current_branch_id')) {
            $branch = Branch::where('spa_id', $spa->id)->where('id', Session::get('current_branch_id'))->first();
            if ($branch) return response()->json(['success' => true, 'branch' => $branch]);
        }

        $branch = $spa->branches()->where('is_main', true)->first() ?: $spa->branches()->first();
        if ($branch) Session::put('current_branch_id', $branch->id);

        return response()->json(['success' => true, 'branch' => $branch]);
    }

    public function show(Branch $branch)
    {
        $user = Auth::user();
        $spa  = $user->spa;

        if (!$spa || $branch->spa_id !== $spa->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if (request()->wantsJson()) return response()->json(['success' => true, 'branch' => $branch]);

        return view('branches.edit', compact('branch'));
    }
}