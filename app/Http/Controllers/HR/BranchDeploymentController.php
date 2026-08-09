<?php
// File path: app/Http/Controllers/HR/BranchDeploymentController.php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Mail\DeploymentApproved;
use App\Mail\DeploymentAwaitingResponse;
use App\Mail\DeploymentRejected;
use App\Models\Branch;
use App\Models\Staff;
use App\Models\StaffBranchDeployment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class BranchDeploymentController extends Controller
{
    /**
     * Display the deployment management page.
     * Loads staff list + all their deployment data for ALL branches (so 1 HR can handle all staff)
     */
    public function index(Request $request)
    {
        $user     = Auth::user();
        $spaId = $user->spa_id;
        $branchId = session('current_branch_id') ?? $user->branch_id;

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
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->get();

        $branchId = session('current_branch_id') ?? $user->branch_id;
        $summaryCounts = StaffBranchDeployment::where('spa_id', $spaId)
            ->whereHas('staff', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            })
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

                    // ✅ ADD: independent staff consent track
                    'staff_response'        => $d->staff_response,
                    'staff_responded_at_fmt' => $d->staff_responded_at?->format('M d, Y h:i A'),
                    'staff_decline_reason'  => $d->staff_decline_reason,
                ])->values()->toArray(),

                // Convenience flags used by the JS rendering
                'has_pending'   => $member->deployments->where('status', 'pending')->isNotEmpty(),
                'has_approved'  => $member->deployments->where('status', 'approved')->isNotEmpty(),
                'has_active'    => $member->deployments->where('status', 'active')->isNotEmpty(),
                'pending_id'    => $member->deployments->where('status', 'pending')->first()?->id,
                'approved_id'   => $member->deployments->where('status', 'approved')->first()?->id,
                'latest_status' => $member->deployments->first()?->status,

                // ✅ ADD: flag if any open deployment was declined by staff and needs HR/Owner attention
                'needs_staff_review' => $member->deployments->first()?->staff_response === 'declined',
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

        $deployment = StaffBranchDeployment::create([
            'staff_id'      => $staff->id,
            'spa_id'        => $user->spa_id,
            'requested_by'  => $user->id,
            'from_branch_id' => $staff->branch_id,
            'to_branch_id'  => (int) $validated['to_branch_id'],
            'start_date'    => $validated['start_date'],
            'end_date'      => $isPermanent ? null : ($validated['end_date'] ?? null),
            'is_permanent'  => $isPermanent,
            'status'        => 'pending',
            'staff_response' => 'pending',
            'notes'         => $validated['notes'] ?? null,
        ]);

        // ✅ Notify the staff member immediately — their consent is independent
        // of Owner review, so they shouldn't wait for approval to find out.
        $deployment->load(['staff.user', 'fromBranch', 'toBranch']);
        if ($deployment->staff?->user?->email) {
            Mail::to($deployment->staff->user->email)->send(new DeploymentAwaitingResponse($deployment));
        }

        return redirect()
            ->route('deployment.index', ['staff_id' => $staff->id])
            ->with('success', 'Deployment request submitted. Awaiting Owner approval and staff response.');
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

        // ✅ Notify the staff member by email
        $deployment->load(['staff.user', 'fromBranch', 'toBranch']);
        if ($deployment->staff?->user?->email) {
            Mail::to($deployment->staff->user->email)->send(new DeploymentApproved($deployment));
        }

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

        // Guard: once staff has accepted, HR/Owner can no longer reject or
        // revoke — the staff member has already committed to the move.
        if ($deployment->staff_response === 'accepted') {
            return back()->with('error', 'This deployment has already been accepted by the staff member and can no longer be rejected or revoked.');
        }

        $deployment->update([
            'status'           => 'rejected',
            'reviewed_by'      => $user->id,
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        $deployment->load(['staff.user', 'fromBranch', 'toBranch']);
        if ($deployment->staff?->user?->email) {
            Mail::to($deployment->staff->user->email)->send(new DeploymentRejected($deployment));
        }

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

    /**
     * Staff member accepts or declines their own deployment request.
     * Independent of Owner approval — both tracks must be affirmative
     * before the deployment can go active.
     */
    public function staffRespond(Request $request, StaffBranchDeployment $deployment)
    {
        $user = Auth::user();

        $deployment->load('staff');
        if (!$deployment->staff || $deployment->staff->user_id !== $user->id) {
            abort(403, 'You are not authorized to respond to this deployment.');
        }

        if (!in_array($deployment->status, ['pending', 'approved'])) {
            return back()->with('error', 'This deployment request is no longer open for a response.');
        }

        if ($deployment->staff_response !== 'pending') {
            return back()->with('error', 'You have already responded to this deployment request.');
        }

        $validated = $request->validate([
            'response'       => 'required|in:accepted,declined',
            'decline_reason' => 'required_if:response,declined|nullable|string|max:1000',
        ]);

        if ($validated['response'] === 'declined') {
            // Auto-reset: declining immediately frees the staff member and closes
            // the request, rather than leaving it stuck for HR/Owner to manually
            // reject. HR sees the reason in deployment history and can file a
            // fresh request if needed.
            $deployment->update([
                'staff_response'       => 'declined',
                'staff_responded_at'   => now(),
                'staff_decline_reason' => $validated['decline_reason'] ?? null,
                'status'               => 'cancelled',
            ]);

            return redirect()->route('dashboard')
                ->with('success', 'You have declined the deployment request. HR/Owner has been notified for reassignment.');
        }

        // Accepted
        $deployment->update([
            'staff_response'      => 'accepted',
            'staff_responded_at'  => now(),
            'staff_decline_reason'=> null,
        ]);

        return redirect()->route('dashboard')
            ->with('success', 'You have accepted the deployment request.');
    }
}
