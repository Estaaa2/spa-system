<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Staff;
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

        $staff = Staff::with(['user.roles', 'branch'])
            ->where('spa_id', $user->spa_id)
            ->where('branch_id', $branchId)
            ->latest()
            ->get();

        return view('staff.index', compact('staff'));
    }

    /**
     * Store a newly created staff in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'roles' => 'required|in:therapist,receptionist,manager,hr,finance',
        ]);

        $currentUser = Auth::user();
        $branchId    = $currentUser->currentBranchId();
        $spa         = $currentUser->spa;

        if (!$branchId) {
            return back()->with('error', 'No valid branch selected. Please switch to a valid branch and try again.');
        }

        if (in_array($validated['roles'], ['hr', 'finance']) && !$spa->isProfessional()) {
            return back()->with('error', 'HR and Finance accounts are only available on the Professional plan.');
        }

        DB::transaction(function () use ($validated, $currentUser, $branchId) {
            $tempPassword = Str::random(12);

            $user = User::create([
                'name'                    => $validated['name'],
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
            'name' => $staff->user?->name ?? '',
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

        $spa = Auth::user()->spa;

        if (in_array($validated['roles'], ['hr', 'finance']) && !$spa->isProfessional()) {
            return back()->with('error', 'HR and Finance roles require the Professional plan.');
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
