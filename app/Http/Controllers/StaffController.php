<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Staff;
use App\Models\Branch;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    /**
     * Display a listing of the staff.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $branchId = session('current_branch_id');

        if (!$branchId) {
            abort(409, 'Branch context not initialized');
        }

        $staff = Staff::with(['user.roles', 'branch'])
            ->where('spa_id', $user->spa_id)
            ->where('branch_id', $branchId)
            ->latest()
            ->get();

        $branches = Branch::where('spa_id', $user->spa_id)->get();

        return view('staff.index', compact('staff', 'branches'));
    }

    /**
     * Store a newly created staff in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'roles' => 'required|in:therapist,receptionist,manager',
        ]);

        $currentUser = Auth::user();
        $branchId = $currentUser->currentBranchId();

        if (!$branchId) {
            return back()->with('error', 'No branch selected.');
        }

        DB::transaction(function () use ($validated, $currentUser, $branchId) {
            $tempPassword = Str::random(12);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($tempPassword),
                'spa_id' => $currentUser->spa_id,
                'branch_id' => $branchId,
                'temp_password' => $tempPassword,
                'password_reset_required' => true,
            ]);

            $user->assignRole($validated['roles']);

            Staff::create([
                'user_id' => $user->id,
                'spa_id' => $currentUser->spa_id,
                'branch_id' => $branchId,
                'employment_status' => 'active',
                'hire_date' => now(),
            ]);
        });

        return redirect()
            ->route('staff.index')
            ->with('success', 'Staff member added successfully.');
    }

    /**
     * Display the specified staff member (for edit modal fetch).
     */
    public function show(Staff $staff)
    {
        // Return what your modal actually needs
        return response()->json([
            'name' => $staff->user?->name ?? '',
            'roles' => $staff->user?->getRoleNames()->first() ?? '',
            'branch_id' => $staff->branch_id,
            'employment_status' => $staff->employment_status ?? 'active',
        ]);
    }

    /**
     * Update the specified staff in storage.
     * ✅ FIX: do not require email/phone/status if your modal doesn't submit them
     */
    public function update(Request $request, Staff $staff)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'roles' => 'required|in:therapist,receptionist,manager,admin',
        ]);

        try {
            // Update user name + role
            if ($staff->user) {
                $staff->user->update([
                    'name' => $validated['name'],
                ]);

                // Replace role with selected role
                $staff->user->syncRoles([$validated['roles']]);
            }

            return redirect()
                ->route('staff.index')
                ->with('success', 'Staff member updated successfully!');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Error updating staff: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified staff from storage.
     */
    public function destroy(Staff $staff)
    {
        try {
            if ($staff->user) {
                $staff->user->delete();
            }

            $staff->delete();

            return redirect()
                ->route('staff.index')
                ->with('success', 'Staff member deleted successfully!');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Error deleting staff: ' . $e->getMessage());
        }
    }
}
