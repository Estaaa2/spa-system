<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Staff;
use App\Models\Branch;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Mail\StaffCredentialsMail;
use Illuminate\Support\Facades\Mail;
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
        $branchId = $user->currentBranchId();

        if (!$branchId) {
            return redirect()->route('branches.index')
                ->with('error', 'No branch found. Please create a branch first.');
        }

        $branch = Branch::find($branchId);
        $spa = $user->spa;

        $hasManpower = $spa?->hasFeature('manpower') ?? false;
        $staffLimit = $spa?->planLimit('staff') ?? 0;

        $activeStaffCount = Staff::query()
            ->where('spa_id', $user->spa_id)
            ->where('employment_status', 'active')
            ->whereNull('deleted_at')
            ->count();

        $staff = Staff::with(['user.roles', 'branch'])
            ->where('spa_id', $user->spa_id)
            ->where('branch_id', $branchId)
            ->latest()
            ->get();

        return view('staff.index', compact(
            'staff',
            'hasManpower',
            'staffLimit',
            'activeStaffCount'
        ));
    }

    /**
     * Store a newly created staff in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'roles' => 'required|in:therapist,receptionist,manager,hr,finance',
        ]);

        $currentUser = Auth::user();
        $branchId    = $currentUser->currentBranchId();
        $spa         = $currentUser->spa;
        $branch   = Branch::find($branchId);

        if (!$branchId) {
            return back()->with('error', 'No valid branch selected. Please switch to a valid branch and try again.');
        }

        if (
            in_array($validated['roles'], ['hr', 'finance'], true) &&
            ! $spa->hasFeature('manpower')
        ) {
            return back()->with(
                'error',
                'HR and Finance accounts require the Business plan.'
            );
        }

        if (!$spa->canAddStaff()) {
            $planName = ucfirst($spa->currentPlan());
            $staffLimit = $spa->planLimit('staff');

            return back()->with(
                'error',
                "Your {$planName} plan allows {$staffLimit} active staff members. Upgrade to add more."
            );
        }

        DB::transaction(function () use ($validated, $currentUser, $branchId) {
            $tempPassword = Str::random(12);

            $user = User::create([
                'first_name'              => $validated['first_name'],
                'middle_name'             => $validated['middle_name'] ?? null,
                'last_name'               => $validated['last_name'],
                'email'                   => $validated['email'],
                'password'                => Hash::make($tempPassword),
                'spa_id'                  => $currentUser->spa_id,
                'branch_id'               => $branchId,
                'temp_password'           => $tempPassword,
                'password_reset_required' => true,
            ]);

            $user->assignRole($validated['roles']);
            $user->markEmailAsVerified();

            Staff::create([
                'user_id'           => $user->id,
                'spa_id'            => $currentUser->spa_id,
                'branch_id'         => $branchId,
                'employment_status' => 'active',
                'hire_date'         => now(),
            ]);

            Mail::to($user->email)->send(new StaffCredentialsMail($user, $tempPassword));
        });

        return redirect()
            ->route('staff.index')
            ->with('success', 'Staff member added successfully and credentials sent via email.');
    }

    /**
     * Display the specified staff member (for edit modal fetch).
     */
    public function show(Staff $staff)
    {
        return response()->json([
            'name' => $staff->user ? trim($staff->user->first_name . ' ' . $staff->user->last_name) : '',
            'roles' => $staff->user?->getRoleNames()->first() ?? '',
            'branch_id' => $staff->branch_id,
            'employment_status' => $staff->employment_status ?? 'active',
        ]);
    }

    /**
     * Update the specified staff in storage.
     */
    public function update(Request $request, Staff $staff)
    {
        $validated = $request->validate([
            'roles' => 'required|in:therapist,receptionist,manager,hr,finance',
        ]);

        $branchId = $staff->branch_id;
        $branch   = Branch::find($branchId);

        $spa = Auth::user()->spa;

        if (
            in_array($validated['roles'], ['hr', 'finance'], true) &&
            ! $spa->hasFeature('manpower')
        ) {
            return back()->with(
                'error',
                'HR and Finance accounts require the Business plan.'
            );
        }

        try {
            if ($staff->user) {
                $staff->user->syncRoles([$validated['roles']]);
            }

            return redirect()
                ->route('staff.index')
                ->with('success', 'Staff member updated successfully!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Error updating staff: ' . $e->getMessage());
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
