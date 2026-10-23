<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Staff;
use App\Models\Branch;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Mail\StaffCredentialsMail;
use App\Services\Payroll\PayrollPermissions;
use App\Services\Subscription\PlanLimitEnforcer;
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

        // Staff paused because the spa is over its plan's staff limit.
        $pausedStaffCount = $spa?->planPausedStaffCount() ?? 0;

        return view('staff.index', compact(
            'staff',
            'hasManpower',
            'staffLimit',
            'activeStaffCount',
            'pausedStaffCount'
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
            'roles' => 'required|in:therapist,receptionist,manager,hr,finance,hr_head,finance_head',
        ]);

        $currentUser = Auth::user();
        $branchId    = $currentUser->currentBranchId();
        $spa         = $currentUser->spa;
        $branch   = Branch::find($branchId);

        if (!$branchId) {
            return back()->with('error', 'No valid branch selected. Please switch to a valid branch and try again.');
        }

        if (
            in_array($validated['roles'], self::PREMIUM_ROLES, true) &&
            ! $spa->hasFeature('manpower')
        ) {
            return back()->with(
                'error',
                'HR and Finance accounts require the Premium plan.'
            );
        }

        if ($error = $this->headRoleError($validated['roles'], null)) {
            return back()->withInput()->with('error', $error);
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
        $this->assertManageable($staff);

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
            'roles' => 'required|in:therapist,receptionist,manager,hr,finance,hr_head,finance_head',
        ]);

        $this->assertManageable($staff);

        $spa = Auth::user()->spa;

        if (
            in_array($validated['roles'], self::PREMIUM_ROLES, true) &&
            ! $spa->hasFeature('manpower')
        ) {
            return back()->with(
                'error',
                'HR and Finance accounts require the Premium plan.'
            );
        }

        if ($error = $this->headRoleError($validated['roles'], $staff)) {
            return back()->with('error', $error);
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
        $this->assertManageable($staff);

        if ($this->isHeadAccount($staff) && ! Auth::user()->hasRole('owner')) {
            return back()->with('error', 'Only the owner can remove an HR Head or Finance Head account.');
        }

        try {
            if ($staff->user) {
                $staff->user->delete();
            }

            $staff->delete();

            // A freed slot lets a paused staff member back in.
            if ($spa = Auth::user()->spa) {
                app(PlanLimitEnforcer::class)->sync($spa);
            }

            return redirect()
                ->route('staff.index')
                ->with('success', 'Staff member deleted successfully!');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Error deleting staff: ' . $e->getMessage());
        }
    }

    // =====================================================================
    // Access rules
    // =====================================================================

    /** Roles that need the Premium plan's manpower feature. */
    private const PREMIUM_ROLES = ['hr', 'finance', 'hr_head', 'finance_head'];

    /**
     * A staff record can only be read or changed by someone in the same spa. Roles
     * that are pinned to one branch can only manage that branch's staff; the owner,
     * HR Head and Finance Head can manage any branch (they switch branches).
     */
    private function assertManageable(Staff $staff): void
    {
        $user = Auth::user();

        abort_unless((int) $staff->spa_id === (int) $user->spa_id, 404);

        if (! $user->canSwitchBranches()) {
            abort_unless((int) $staff->branch_id === (int) $user->branch_id, 403, 'You can only manage staff of your own branch.');
        }
    }

    private function isHeadAccount(Staff $staff): bool
    {
        return (bool) $staff->user?->hasAnyRole(PayrollPermissions::HEAD_ROLES);
    }

    /**
     * HR Head / Finance Head rules: only the owner assigns or changes them, and a spa
     * has at most one active account of each. Returns an error message, or null.
     */
    private function headRoleError(string $newRole, ?Staff $staff): ?string
    {
        $user = Auth::user();
        $assigningHead = in_array($newRole, PayrollPermissions::HEAD_ROLES, true);
        $changingHead = $staff !== null && $this->isHeadAccount($staff)
            && $staff->user?->getRoleNames()->first() !== $newRole;

        if (($assigningHead || $changingHead) && ! $user->hasRole('owner')) {
            return 'Only the owner can assign or change the HR Head and Finance Head roles.';
        }

        if (! $assigningHead) {
            return null;
        }

        $taken = Staff::query()
            ->where('spa_id', $user->spa_id)
            ->where('employment_status', 'active')
            ->when($staff, fn ($q) => $q->whereKeyNot($staff->id))
            ->whereHas('user', fn ($q) => $q->role($newRole))
            ->exists();

        return $taken
            ? 'This spa already has an active ' . PayrollPermissions::roleLabel($newRole) . '. Change or remove that account first.'
            : null;
    }
}
