<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class BranchController extends Controller
{
    /**
     * Display a listing of the branches.
     */
    public function index()
    {
        // Get the spa associated with the authenticated user
        $spa = Auth::user()->spa;

        // Get all branches for this spa with user count
        $branches = $spa->branches()->withCount('users')->get();

        return view('branches.index', compact('branches', 'spa'));
    }

    /**
     * Store a newly created branch.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string', // CHANGED from 'address'
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'is_main' => 'boolean'
        ]);

        // Get the user's spa
        $spa = Auth::user()->spa;

        // If this is the first branch, make it the main branch
        if ($spa->branches()->count() === 0) {
            $validated['is_main'] = true;
        } else {
            // If setting this as main, update all others to not be main
            if ($request->has('is_main') && $request->is_main) {
                $spa->branches()->update(['is_main' => false]);
            }
        }

        // Add spa_id to validated data
        $validated['spa_id'] = $spa->id;

        // Create the branch
        $branch = Branch::create($validated);

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
        // Check if user has access to this branch
        if ($branch->spa_id !== Auth::user()->spa_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // SIMPLIFIED VALIDATION FOR NOW
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string',
            'phone' => 'nullable|string|max:20', // Changed to simple validation
            'email' => 'nullable|email',
            'is_main' => 'sometimes|boolean'
        ]);

        // Clean phone number if provided
        if (!empty($validated['phone'])) {
            $validated['phone'] = preg_replace('/\D/', '', $validated['phone']);

            // Optional: Validate Philippine format
            if (strlen($validated['phone']) === 11 && !str_starts_with($validated['phone'], '09')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone number must start with 09 for Philippine numbers'
                ], 422);
            }
        }

        // If setting this as main, update all others to not be main
        if ($request->has('is_main') && $request->is_main) {
            Branch::where('spa_id', $branch->spa_id)
                ->where('id', '!=', $branch->id)
                ->update(['is_main' => false]);
        }

        $branch->update($validated);

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
        // Check if user has access to this branch
        if ($branch->spa_id !== Auth::user()->spa_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Check if this is the main branch
        if ($branch->is_main) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the main branch. Please set another branch as main first.'
            ], 422);
        }

        // Check if branch has any users
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
        $branch = Branch::findOrFail($request->branch_id);

        // Check if user has access to this branch
        if (!$user->spa || $branch->spa_id !== $user->spa_id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this branch'
            ], 403);
        }

        // Store selected branch in session
        Session::put('current_branch_id', $branch->id);

        // Also update user's branch_id if you have that column
        if (in_array('branch_id', \Schema::getColumnListing('users'))) {
            $user->branch_id = $branch->id;
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Branch switched successfully',
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'location' => $branch->location, // CHANGED from 'address'
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

        if (Session::has('current_branch_id')) {
            $branch = Branch::where('spa_id', $user->spa_id)
                          ->where('id', Session::get('current_branch_id'))
                          ->first();
            if ($branch) {
                return response()->json([
                    'success' => true,
                    'branch' => $branch
                ]);
            }
        }

        // Default to main branch or first branch
        $branch = $user->spa->branches()->where('is_main', true)->first()
                ?: $user->spa->branches()->first();

        if ($branch) {
            Session::put('current_branch_id', $branch->id);
        }

        return response()->json([
            'success' => true,
            'branch' => $branch
        ]);
    }

    public function show(Branch $branch)
    {
        // Check if user has access to this branch
        $user = Auth::user();

        if (!$user->spa || $branch->spa_id !== $user->spa_id) {
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
                'is_main' => $branch->is_main,
                'spa_id' => $branch->spa_id,
                'created_at' => $branch->created_at,
                'updated_at' => $branch->updated_at,
            ]
        ]);
    }
}
