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

        if (!$spa) {
            abort(403, 'No spa assigned to this account.');
        }

        if ($user->hasRole('owner')) {
            // Owner sees all branches
            $branches = $spa->branches()
                ->withCount('users')
                ->get();
        } else {
            // Manager / Staff see ONLY their assigned branch
            $branches = $spa->branches()
                ->where('id', $user->branch_id)
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

        return view('branches.edit', compact('branch'));
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
            'is_main' => 'nullable'
        ]);

        $wantsMain = filter_var($request->input('is_main'), FILTER_VALIDATE_BOOLEAN);

        $isFirstBranch = ($spa->branches()->count() === 0);
        if ($isFirstBranch) {
            $wantsMain = true;
        }

        if ($wantsMain) {
            $spa->branches()->update(['is_main' => false]);
        }

        $branch = Branch::create([
            'spa_id' => $spa->id,
            'name' => $validated['name'],
            'location' => $validated['location'],
            'is_main' => $wantsMain,
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
            'phone' => 'nullable|string|size:11',
            'email' => 'nullable|email|max:100',
            'is_main' => 'nullable'
        ]);

        $wantsMain = filter_var($request->input('is_main'), FILTER_VALIDATE_BOOLEAN);

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
            'is_main' => $wantsMain,
        ]);

        // Redirect if coming from edit page
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Branch updated successfully',
                'branch' => $branch->fresh()
            ]);
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