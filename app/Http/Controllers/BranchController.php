<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Schema;

class BranchController extends Controller
{
    /**
     * Display a listing of the branches.
     */
    public function index()
    {
        $user = Auth::user();
        $spa = $user->spa;

        // If user has no spa, avoid crashing
        if (!$spa) {
            abort(403, 'No spa assigned to this account.');
        }

        $branches = $spa->branches()->withCount('users')->get();

        return view('branches.index', compact('branches', 'spa'));
    }

    /**
     * Store a newly created branch.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $spa = $user->spa;

        if (!$spa) {
            return response()->json([
                'success' => false,
                'message' => 'No spa assigned to this account.'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_main' => 'nullable' // we will normalize manually
        ]);

        // Normalize phone (optional)
        if (!empty($validated['phone'])) {
            $validated['phone'] = preg_replace('/\D/', '', $validated['phone']);

            // If they provided something, enforce PH mobile style (optional)
            if (strlen($validated['phone']) !== 11 || !str_starts_with($validated['phone'], '09')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please enter a valid Philippine mobile number (11 digits starting with 09).',
                    'errors' => ['phone' => ['Invalid phone format']]
                ], 422);
            }
        }

        // Normalize is_main to boolean
        // Accepts: 1, "1", true, "true", "on"
        $wantsMain = filter_var($request->input('is_main'), FILTER_VALIDATE_BOOLEAN);

        // Force first branch to be main
        $isFirstBranch = ($spa->branches()->count() === 0);
        if ($isFirstBranch) {
            $wantsMain = true;
        }

        // Only one main branch rule
        if ($wantsMain) {
            $spa->branches()->update(['is_main' => false]);
        }

        $branch = Branch::create([
            'spa_id' => $spa->id,
            'name' => $validated['name'],
            'location' => $validated['location'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'is_main' => $wantsMain ? true : false,
        ]);

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
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_main' => 'nullable'
        ]);

        // Normalize phone (optional)
        if (!empty($validated['phone'])) {
            $validated['phone'] = preg_replace('/\D/', '', $validated['phone']);

            if (strlen($validated['phone']) !== 11 || !str_starts_with($validated['phone'], '09')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please enter a valid Philippine mobile number (11 digits starting with 09).',
                    'errors' => ['phone' => ['Invalid phone format']]
                ], 422);
            }
        }

        // Normalize is_main
        $wantsMain = filter_var($request->input('is_main'), FILTER_VALIDATE_BOOLEAN);

        // If they set this as main, unset others
        if ($wantsMain) {
            Branch::where('spa_id', $spa->id)
                ->where('id', '!=', $branch->id)
                ->update(['is_main' => false]);
        }

        $branch->update([
            'name' => $validated['name'],
            'location' => $validated['location'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'is_main' => $wantsMain ? true : false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Branch updated successfully',
            'branch' => $branch->fresh()
        ]);
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
                'message' => 'Cannot delete the main branch. Please set another branch as main first.'
            ], 422);
        }

        if ($branch->users()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete branch with assigned users. Reassign users first.'
            ], 422);
        }

        $branch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Branch deleted successfully'
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

        $branch = Branch::where('spa_id', $spa->id)
            ->where('id', $request->branch_id)
            ->first();

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this branch'
            ], 403);
        }

        Session::put('current_branch_id', $branch->id);

        if (Schema::hasColumn('users', 'branch_id')) {
            $user->branch_id = $branch->id;
            $user->save();
        }

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
     * Show branch (for edit modal fetch).
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

        return response()->json([
            'success' => true,
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'location' => $branch->location,
                'phone' => $branch->phone,
                'email' => $branch->email,
                'is_main' => (bool) $branch->is_main,
                'spa_id' => $branch->spa_id,
                'created_at' => $branch->created_at,
                'updated_at' => $branch->updated_at,
            ]
        ]);
    }
}
