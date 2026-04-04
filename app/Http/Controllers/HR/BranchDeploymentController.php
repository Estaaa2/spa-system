<?php
// File path: app/Http/Controllers/HR/BranchDeploymentController.php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Staff;
use App\Models\StaffBranchDeployment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BranchDeploymentController extends Controller
{
    /**
     * Display the deployment management page.
     * Loads staff list + all their deployment data for ALL branches (so 1 HR can handle all staff)
     */
    public function index(Request $request)
    {
        $user     = Auth::user();
        $branchId = $user->currentBranchId();

        if (!$branchId) {
            return redirect()->route('branches.index')
                ->with('error', 'No branch found. Please create or select a branch first.');
        }

        // ✅ CHANGE: Load ALL staff across ALL branches (not just current branch)
        // This allows 1 HR to manage deployments for the entire spa
        $staff = Staff::with([
            'user.roles',
            'branch',
            'deployments' => fn($q) => $q
                ->with(['fromBranch', 'toBranch', 'requestedBy', 'reviewedBy'])
                ->latest(),
        ])
            ->where('spa_id', $user->spa_id)
            // ❌ REMOVE THIS LINE: ->where('branch_id', $branchId)
            ->get();

        // Summary counts across the whole spa (not just current branch)
        $summaryCounts = StaffBranchDeployment::where('spa_id', $user->spa_id)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // ✅ CHANGE: Get ALL branches as deployment targets (including current branch? No, exclude current)
        $branches = Branch::where('spa_id', $user->spa_id)
            ->where('id', '!=', $branchId)
            ->get();

        // ✅ ADD: Get ALL branches for the branch filter dropdown
        $allBranches = Branch::where('spa_id', $user->spa_id)->get();

        // Pre-build staff deployment data keyed by staff ID for JS rendering
        $staffDeploymentData = $staff->keyBy('id')->map(function (Staff $member) {
            return [
                'id'    => $member->id,
                'name'  => $member->user?->name ?? 'Unknown',
                'email' => $member->user?->email ?? '',
                'role'  => $member->user?->getRoleNames()->first() ?? '',
                'branch' => $member->branch ? [
                    'id'       => $member->branch->id,
                    'name'     => $member->branch->name,
                    'location' => $member->branch->location,
                ] : null,
                'deployments' => $member->deployments->map(fn($d) => [
                    'id'             => $d->id,
                    'from_branch'    => ['id' => $d->fromBranch->id, 'name' => $d->fromBranch->name],
                    'to_branch'      => ['id' => $d->toBranch->id, 'name' => $d->toBranch->name],
                    'start_date_fmt' => $d->start_date?->format('M d, Y') ?? '—',
                    'end_date_fmt'   => $d->is_permanent
                        ? 'Permanent'
                        : ($d->end_date?->format('M d, Y') ?? 'Open-ended'),
                    'is_permanent'   => $d->is_permanent,
                    'status'         => $d->status,
                    'rejection_reason' => $d->rejection_reason,
                    'notes'          => $d->notes,
                    'requested_by'   => $d->requestedBy?->name ?? '—',
                    'reviewed_by'    => $d->reviewedBy?->name,
                    'created_at_fmt' => $d->created_at?->format('M d, Y'),
                ])->values()->toArray(),

                // Convenience flags used by the JS rendering
                'has_pending'   => $member->deployments->where('status', 'pending')->isNotEmpty(),
                'has_approved'  => $member->deployments->where('status', 'approved')->isNotEmpty(),
                'has_active'    => $member->deployments->where('status', 'active')->isNotEmpty(),
                'pending_id'    => $member->deployments->where('status', 'pending')->first()?->id,
                'approved_id'   => $member->deployments->where('status', 'approved')->first()?->id,
                'latest_status' => $member->deployments->first()?->status,
            ];
        })->toArray();

        return view('hr.deployment.index', compact(
            'staff',
            'summaryCounts',
            'branches',
            'allBranches',        // ← ADD THIS
            'staffDeploymentData'
        ));
    }

    /**
     * HR submits a new branch deployment request.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'staff_id'     => 'required|integer|exists:staff,id',
            'to_branch_id' => 'required|integer|exists:branches,id',
            'start_date'   => 'required|date|after_or_equal:today',
            'is_permanent' => 'nullable|boolean',
            'end_date'     => 'nullable|date|after:start_date',
            'notes'        => 'nullable|string|max:1000',
        ]);

        $user  = Auth::user();
        $staff = Staff::findOrFail($validated['staff_id']);

        // Scope check — staff must belong to this spa
        if ($staff->spa_id !== $user->spa_id) {
            abort(403, 'This staff member does not belong to your spa.');
        }

        // Block if an open request already exists
        $hasBlockingRequest = StaffBranchDeployment::where('staff_id', $staff->id)
            ->whereIn('status', ['pending', 'approved', 'active'])
            ->exists();

        if ($hasBlockingRequest) {
            return back()->with('error', 'This staff member already has a pending, approved, or active deployment. Resolve the existing request first.');
        }

        // Cannot deploy to the same branch
        if ((int) $validated['to_branch_id'] === $staff->branch_id) {
            return back()->with('error', 'Target branch cannot be the same as the staff member\'s current branch.');
        }

        $isPermanent = (bool) ($validated['is_permanent'] ?? false);

        StaffBranchDeployment::create([
            'staff_id'      => $staff->id,
            'spa_id'        => $user->spa_id,
            'requested_by'  => $user->id,
            'from_branch_id' => $staff->branch_id,
            'to_branch_id'  => (int) $validated['to_branch_id'],
            'start_date'    => $validated['start_date'],
            'end_date'      => $isPermanent ? null : ($validated['end_date'] ?? null),
            'is_permanent'  => $isPermanent,
            'status'        => 'pending',
            'notes'         => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('deployment.index', ['staff_id' => $staff->id])
            ->with('success', 'Deployment request submitted. Awaiting Owner approval.');
    }

    /**
     * Owner approves a pending deployment request.
     */
    public function approve(StaffBranchDeployment $deployment)
    {
        $user = Auth::user();

        if ($deployment->spa_id !== $user->spa_id) {
            abort(403);
        }

        if ($deployment->status !== 'pending') {
            return back()->with('error', 'Only pending requests can be approved.');
        }

        $deployment->update([
            'status'      => 'approved',
            'reviewed_by' => $user->id,
        ]);

        return redirect()
            ->route('deployment.index', ['staff_id' => $deployment->staff_id])
            ->with('success', 'Deployment approved. The staff member will be moved on the scheduled start date.');
    }

    /**
     * Owner rejects a pending (or already-approved) deployment with a reason.
     */
    public function reject(Request $request, StaffBranchDeployment $deployment)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $user = Auth::user();

        if ($deployment->spa_id !== $user->spa_id) {
            abort(403);
        }

        if (!in_array($deployment->status, ['pending', 'approved'])) {
            return back()->with('error', 'This deployment request cannot be rejected in its current state.');
        }

        $deployment->update([
            'status'           => 'rejected',
            'reviewed_by'      => $user->id,
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return redirect()
            ->route('deployment.index', ['staff_id' => $deployment->staff_id])
            ->with('success', 'Deployment request rejected.');
    }

    /**
     * HR cancels their own pending request.
     */
    public function cancel(StaffBranchDeployment $deployment)
    {
        $user = Auth::user();

        if ($deployment->spa_id !== $user->spa_id) {
            abort(403);
        }

        if ($deployment->status !== 'pending') {
            return back()->with('error', 'Only pending requests can be cancelled.');
        }

        $deployment->update(['status' => 'cancelled']);

        return redirect()
            ->route('deployment.index', ['staff_id' => $deployment->staff_id])
            ->with('success', 'Deployment request cancelled.');
    }
}
