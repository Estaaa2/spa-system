<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\OperatingHours;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BranchController extends Controller
{
    private function authorizeBranch(Branch $branch): void
    {
        $user = Auth::user();
        $spa = $user?->spa;

        if (!$spa || $branch->spa_id !== $spa->id) {
            abort(403, 'Unauthorized');
        }

        if (
            !$user->hasRole('owner') &&
            (int) $branch->id !== (int) $user->branch_id
        ) {
            abort(403, 'You can only manage your own branch.');
        }
    }

    private function denyBranchJson(Branch $branch, bool $ownerOnly = false)
    {
        $user = Auth::user();
        $spa = $user?->spa;

        if (!$spa || $branch->spa_id !== $spa->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($ownerOnly && !$user->hasRole('owner')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if (
            !$ownerOnly &&
            !$user->hasRole('owner') &&
            (int) $branch->id !== (int) $user->branch_id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'You can only manage your own branch.',
            ], 403);
        }

        return null;
    }

    public function index()
    {
        $user = Auth::user();
        $spa = $user->spa;

        if (
            $user->hasRole('owner')
            && ! $spa->hasFeature('all_branches')
        ) {
            $mainBranch = $spa->branches()
                ->where('is_main', true)
                ->first()
                ?? $spa->branches()->orderBy('id')->first();

            if ($mainBranch) {
                return redirect()->route('branches.edit', [
                    'branch' => $mainBranch->id,
                    'tab' => 'profile',
                ]);
            }
        }

        $branchesQuery = $spa->branches()
            ->with(['profile'])
            ->withCount('users')
            ->orderByDesc('is_main')
            ->orderBy('name');

        if (! $user->hasRole('owner')) {
            $branchesQuery->where('id', $user->branch_id);
        }

        $branches = $branchesQuery->get();

        if (! Session::has('current_branch_id') && $branches->isNotEmpty()) {
            $main = $branches->firstWhere('is_main', true)
                ?? $branches->first();

            Session::put('current_branch_id', $main->id);
        }

        return view('branches.index', compact('branches', 'spa'));
    }

    public function edit(Branch $branch)
    {
        $user = Auth::user();
        $spa = $user->spa;

        $this->authorizeBranch($branch);
        $canPubliclyList = $spa->hasFeature('branch_public_listing');

        $daysOfWeek = [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday',
        ];

        $operatingHours = $branch->operatingHours()->get();

        foreach ($daysOfWeek as $day) {
            if (!$operatingHours->where('day_of_week', $day)->first()) {
                $operatingHours->push(new OperatingHours([
                    'day_of_week' => $day,
                    'opening_time' => '09:00',
                    'closing_time' => '18:00',
                    'is_closed' => false,
                ]));
            }
        }

        $canEditGeneral = $user->hasBranchPermission('edit branch general');
        $canEditHours = $user->hasBranchPermission('edit branch hours');
        $canEditProfile = $user->hasBranchPermission('edit branch profile');

        return view('branches.edit', compact(
            'branch',
            'spa',
            'operatingHours',
            'canEditGeneral',
            'canEditHours',
            'canEditProfile',
        ));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $spa = $user->spa;

        $days = [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday',
        ];

        if (!$spa) {
            return response()->json([
                'success' => false,
                'message' => 'No spa assigned.',
            ], 403);
        }

        if (!$user->hasRole('owner')) {
            return response()->json([
                'success' => false,
                'message' => 'Only the spa owner can add branches.',
            ], 403);
        }

        if (!$spa->hasAccess()) {
            return response()->json([
                'success' => false,
                'message' => 'Your spa does not currently have an active trial or subscription.',
            ], 422);
        }

        if (!$spa->canAddBranch()) {
            $planName = ucfirst($spa->currentPlan());
            $branchLimit = $spa->planLimit('branches');

            return response()->json([
                'success' => false,
                'message' => "Your {$planName} plan allows {$branchLimit} branch(es). Upgrade to add more.",
            ], 422);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('branches', 'name')
                    ->where(fn ($query) => $query->where('spa_id', $spa->id)),
            ],
            'location' => 'required|string',
            'is_main' => 'nullable|boolean',
            'hours' => 'required|array',
            'hours.*.day_of_week' => 'required|string',
            'hours.*.opening_time' => 'required|date_format:H:i',
            'hours.*.closing_time' => 'required|date_format:H:i',
            'hours.*.is_closed' => 'required|boolean',
        ]);

        $wantsMain = filter_var(
            $request->input('is_main'),
            FILTER_VALIDATE_BOOLEAN
        );

        $isFirstBranch = !$spa->branches()
            ->whereNull('deleted_at')
            ->exists();

        if ($isFirstBranch) {
            $wantsMain = true;
        }

        if ($wantsMain) {
            $spa->branches()->update(['is_main' => false]);
        }

        /*
         * This column remains for compatibility only.
         * Business/manpower is the real access rule.
         */
        $branch = Branch::create([
            'spa_id' => $spa->id,
            'name' => $validated['name'],
            'location' => $validated['location'],
            'is_main' => $wantsMain,
            'has_workforce_finance_suite' => $spa->hasFeature('manpower'),
        ]);

        foreach ($validated['hours'] as $index => $hourData) {
            OperatingHours::create([
                'branch_id' => $branch->id,
                'day_of_week' => $hourData['day_of_week'] ?? $days[$index],
                'opening_time' => $hourData['opening_time'] ?? '09:00',
                'closing_time' => $hourData['closing_time'] ?? '18:00',
                'is_closed' => (bool) ($hourData['is_closed'] ?? false),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Branch created successfully.',
            'branch' => $branch,
        ]);
    }

    public function updateGeneral(Request $request, Branch $branch)
    {
        $user = Auth::user();
        $spa = $user->spa;

        $this->authorizeBranch($branch);

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('branches', 'name')
                    ->where(fn ($query) => $query->where('spa_id', $spa->id))
                    ->ignore($branch->id),
            ],
            'is_main' => 'nullable|boolean',
        ], [
            'name.unique' => 'This branch name is already used by your spa.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->to(route('branches.edit', $branch->id) . '?tab=general')
                ->withErrors($validator, 'general')
                ->withInput();
        }

        $wantsMain = $user->hasRole('owner')
            ? $request->boolean('is_main')
            : (bool) $branch->is_main;

        if ($wantsMain) {
            Branch::where('spa_id', $spa->id)
                ->where('id', '!=', $branch->id)
                ->update(['is_main' => false]);
        }

        $branch->update([
            'name' => $request->input('name'),
            'is_main' => $wantsMain,
        ]);

        return redirect()
            ->route('branches.edit', [
                'branch' => $branch->id,
                'tab' => 'general',
            ])
            ->with('success', 'Branch information updated successfully.');
    }

    public function updateHours(Request $request, Branch $branch)
    {
        $this->authorizeBranch($branch);

        $validator = Validator::make($request->all(), [
            'hours' => 'required|array',
            'hours.*.day_of_week' => 'required|string',
            'hours.*.is_closed' => 'nullable|boolean',
            'hours.*.opening_time' => ['nullable', 'date_format:H:i'],
            'hours.*.closing_time' => ['nullable', 'date_format:H:i'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->to(route('branches.edit', $branch->id) . '?tab=hours')
                ->withErrors($validator, 'hours')
                ->withInput();
        }

        $rangeErrors = [];

        foreach ($request->input('hours', []) as $hourData) {
            if (!empty($hourData['is_closed'])) {
                continue;
            }

            $opening = $hourData['opening_time'] ?? null;
            $closing = $hourData['closing_time'] ?? null;

            if ($opening && $closing && $closing <= $opening) {
                $rangeErrors[] =
                    "{$hourData['day_of_week']}: closing time must be after opening time.";
            }
        }

        if ($rangeErrors !== []) {
            return redirect()
                ->to(route('branches.edit', $branch->id) . '?tab=hours')
                ->withErrors($rangeErrors, 'hours')
                ->withInput();
        }

        $existingHours = $branch->operatingHours()
            ->get()
            ->keyBy('day_of_week');

        foreach ($request->input('hours', []) as $hourData) {
            $day = $hourData['day_of_week'];

            $hour = $existingHours->get($day)
                ?? new OperatingHours([
                    'branch_id' => $branch->id,
                    'day_of_week' => $day,
                ]);

            $hour->is_closed = !empty($hourData['is_closed']);
            $hour->opening_time = $hourData['opening_time'] ?? '09:00';
            $hour->closing_time = $hourData['closing_time'] ?? '18:00';
            $hour->branch_id = $branch->id;
            $hour->save();
        }

        return redirect()
            ->route('branches.edit', [
                'branch' => $branch->id,
                'tab' => 'hours',
            ])
            ->with('success', 'Operating hours updated successfully.');
    }

    public function updateProfile(Request $request, Branch $branch)
    {
        $user = Auth::user();
        $spa = $user->spa;

        $this->authorizeBranch($branch);

        if ($spa->verification_status !== 'verified') {
            if ($branch->profile) {
                $branch->profile->update(['is_listed' => false]);
            }

            return redirect()
                ->to(route('branches.edit', $branch->id) . '?tab=profile')
                ->withErrors([
                    'Your spa must be verified before this branch can be listed publicly.',
                ], 'profile');
        }

        $validator = Validator::make($request->all(), [
            'cover_image' => 'nullable|image|max:2048',
            'gallery_images.*' => 'nullable|image|max:2048',
            'gallery_captions' => 'nullable|array',
            'gallery_captions.*' => 'nullable|string|max:80',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'location_confirmed' => 'required|accepted',
            'amenities' => 'nullable|array',
            'amenities.*' => 'nullable|string|max:100',
            'is_hiring' => 'nullable|boolean',
            'hiring_note' => 'nullable|string|max:150',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->to(route('branches.edit', $branch->id) . '?tab=profile')
                ->withErrors($validator, 'profile')
                ->withInput();
        }

        if ($request->filled('latitude') && $request->filled('longitude')) {
            $lat = (float) $request->latitude;
            $lng = (float) $request->longitude;

            if (
                !($lat >= 14.020 && $lat <= 14.520) ||
                !($lng >= 120.620 && $lng <= 121.100)
            ) {
                return redirect()
                    ->to(route('branches.edit', $branch->id) . '?tab=profile')
                    ->withErrors([
                        'Pinned location must be within Cavite only.',
                    ], 'profile')
                    ->withInput();
            }
        }

        $profile = $branch->profile
            ?? $branch->profile()->create(['branch_id' => $branch->id]);

        $profileData = $validator->validated();
        unset($profileData['location_confirmed']);

        $spa = $branch->spa;
        $canPubliclyList = $spa?->hasFeature('branch_public_listing') ?? false;

        $profileData['is_listed'] =
            $canPubliclyList && $request->boolean('is_listed');
        $profileData['is_hiring'] = $request->boolean('is_hiring');
        $profileData['hiring_note'] = $profileData['is_hiring']
            ? ($profileData['hiring_note'] ?? null)
            : null;

        if ($request->boolean('remove_cover_image')) {
            if ($profile->cover_image) {
                Storage::disk('public')->delete($profile->cover_image);
            }

            $profileData['cover_image'] = null;
        } elseif ($request->hasFile('cover_image')) {
            if ($profile->cover_image) {
                Storage::disk('public')->delete($profile->cover_image);
            }

            $profileData['cover_image'] = $request
                ->file('cover_image')
                ->store('branch_profiles', 'public');
        } else {
            $profileData['cover_image'] = $profile->cover_image;
        }

        $finalGallery = [];
        $finalCaptions = [];
        $existingGalleryInputs = $request->input(
            'existing_gallery_images',
            []
        );
        $removeGalleryInputs = $request->input(
            'remove_gallery_images',
            []
        );
        $newGalleryFiles = $request->file('gallery_images', []);
        $captionInputs = $request->input('gallery_captions', []);

        for ($i = 0; $i < 4; $i++) {
            $existingPath = $existingGalleryInputs[$i] ?? null;
            $removeThis = isset($removeGalleryInputs[$i])
                && (int) $removeGalleryInputs[$i] === 1;
            $newFile = $newGalleryFiles[$i] ?? null;
            $captionText = trim((string) ($captionInputs[$i] ?? ''));

            if ($removeThis) {
                if ($existingPath) {
                    Storage::disk('public')->delete($existingPath);
                }

                continue;
            }

            $finalPath = null;

            if ($newFile) {
                if ($existingPath) {
                    Storage::disk('public')->delete($existingPath);
                }

                $finalPath = $newFile->store('branch_profiles', 'public');
                $finalGallery[$i] = $finalPath;
            } elseif ($existingPath) {
                $finalPath = $existingPath;
                $finalGallery[$i] = $finalPath;
            }

            if ($finalPath && $captionText !== '') {
                $finalCaptions[$finalPath] = [
                    'caption' => $captionText,
                ];
            }
        }

        $profileData['gallery_images'] = array_values(
            array_filter($finalGallery)
        );
        $profileData['gallery_captions'] = $finalCaptions;
        $profileData['amenities'] =
            $profileData['amenities'] ?? $profile->amenities ?? [];

        $profile->update($profileData);

        $branch->update([
            'location' => $profileData['address'],
        ]);

        $response = redirect()
            ->route('branches.edit', [
                'branch' => $branch->id,
                'tab' => 'profile',
            ])
            ->with('success', 'Public profile updated successfully.');

        if (! $canPubliclyList) {
            $response->with(
                'upgrade_message',
                'Public listing is available on Premium and Business plans. Upgrade your plan to list this branch publicly.'
            );
        }

        return $response;
    }

    public function destroy(Branch $branch)
    {
        if ($denied = $this->denyBranchJson($branch, ownerOnly: true)) {
            return $denied;
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

        return response()->json([
            'success' => true,
            'message' => 'Branch removed successfully.',
        ]);
    }

    public function switch(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
        ]);

        $user = Auth::user();
        $spa = $user->spa;

        if (!$spa) {
            return response()->json([
                'success' => false,
                'message' => 'No spa assigned.',
            ], 403);
        }

        if (!$user->hasRole('owner')) {
            return response()->json([
                'success' => false,
                'message' => 'Not allowed.',
            ], 403);
        }

        $branch = Branch::where('spa_id', $spa->id)
            ->where('id', $request->branch_id)
            ->first();

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found.',
            ], 403);
        }

        Session::put('current_branch_id', $branch->id);

        return response()->json([
            'success' => true,
            'message' => 'Branch switched.',
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'location' => $branch->location,
                'is_main' => $branch->is_main,
            ],
        ]);
    }

    public function getCurrentBranch()
    {
        $user = Auth::user();
        $spa = $user->spa;

        if (!$spa) {
            return response()->json([
                'success' => false,
                'message' => 'No spa assigned.',
            ], 403);
        }

        if (Session::has('current_branch_id')) {
            $branch = Branch::where('spa_id', $spa->id)
                ->where('id', Session::get('current_branch_id'))
                ->first();

            if ($branch) {
                return response()->json([
                    'success' => true,
                    'branch' => $branch,
                ]);
            }
        }

        $branch = $spa->branches()
            ->where('is_main', true)
            ->first()
            ?? $spa->branches()->first();

        if ($branch) {
            Session::put('current_branch_id', $branch->id);
        }

        return response()->json([
            'success' => true,
            'branch' => $branch,
        ]);
    }

    public function show(Branch $branch)
    {
        if ($denied = $this->denyBranchJson($branch)) {
            return $denied;
        }

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'branch' => $branch,
            ]);
        }

        return view('branches.edit', compact('branch'));
    }
}
