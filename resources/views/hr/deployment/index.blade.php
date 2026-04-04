{{-- File path: resources/views/hr/deployment/index.blade.php --}}

@extends('layouts.app')
@section('content')
@php
    $user = auth()->user();
    $canCreateDeployments = $user?->hasBranchPermission('create deployments') ?? false;
    $canApproveDeployments = $user?->hasBranchPermission('approve deployments') ?? false;
    $canDeleteDeployments = $user?->hasBranchPermission('delete deployments') ?? false;

    $pendingCount   = $summaryCounts['pending']   ?? 0;
    $approvedCount  = $summaryCounts['approved']  ?? 0;
    $activeCount    = $summaryCounts['active']    ?? 0;
    $completedCount = $summaryCounts['completed'] ?? 0;
@endphp

<div class="p-6 mx-auto space-y-6 max-w-7xl">

    <x-page-header
        title="Branch Deployment"
        subtitle="Manage and track staff branch deployment requests."
    />

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="flex items-center gap-3 p-4 text-green-800 border border-green-200 rounded-2xl bg-green-50 dark:bg-green-900/10 dark:border-green-800 dark:text-green-300">
            <i class="fa-solid fa-circle-check shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="flex items-center gap-3 p-4 text-red-800 border border-red-200 rounded-2xl bg-red-50 dark:bg-red-900/10 dark:border-red-800 dark:text-red-300">
            <i class="fa-solid fa-circle-xmark shrink-0"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Pending</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-yellow-600 dark:text-yellow-400">{{ $pendingCount }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">Awaiting review</span>
            </div>
        </div>
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Approved</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-blue-600 dark:text-blue-400">{{ $approvedCount }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">Scheduled</span>
            </div>
        </div>
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Active</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-green-600 dark:text-green-400">{{ $activeCount }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">In progress</span>
            </div>
        </div>
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Completed</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-gray-600 dark:text-gray-400">{{ $completedCount }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">Finished</span>
            </div>
        </div>
    </div>

    {{-- Staff List --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Staff Members</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Click a staff member to view or manage their deployment request.
                </p>
            </div>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $staff->count() }} member(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Staff Member</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Role</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Current Branch</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Latest Deployment</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    @forelse($staff as $member)
                    @php
                        $latestDeploy = $member->deployments->first();
                        $latestStatus = $latestDeploy?->status;
                        $statusBadge = match($latestStatus) {
                            'pending'   => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
                            'approved'  => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                            'active'    => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                            'rejected'  => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                            'completed' => 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                            'cancelled' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
                            default     => null,
                        };
                        $statusLabel = match($latestStatus) {
                            'pending'   => 'Pending',
                            'approved'  => 'Approved',
                            'active'    => 'Active',
                            'rejected'  => 'Rejected',
                            'completed' => 'Completed',
                            'cancelled' => 'Cancelled',
                            default     => null,
                        };
                        $role = $member->user?->getRoleNames()->first();
                        $roleColors = [
                            'manager'      => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                            'therapist'    => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                            'receptionist' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
                            'hr'           => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
                            'finance'      => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
                        ];
                        $roleColor = $roleColors[$role] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                    @endphp
                    <tr
                        id="staff-row-{{ $member->id }}"
                        class="transition-colors border-l-4 border-transparent cursor-pointer staff-row hover:bg-gray-50 dark:hover:bg-gray-900"
                        onclick="selectStaff({{ $member->id }})"
                    >
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-10 h-10 text-sm font-semibold text-white rounded-full bg-[#8B7355] shrink-0">
                                    {{ strtoupper(substr($member->user->first_name ?? 'S', 0, 1)) }}
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        {{ trim($member->user->first_name . ' ' . ($member->user->middle_name ? $member->user->middle_name . ' ' : '') . $member->user->last_name) }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $member->user->email ?? 'No email' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            @if($role)
                                <span class="px-3 py-1 text-xs font-medium rounded-full {{ $roleColor }}">{{ ucfirst($role) }}</span>
                            @else
                                <span class="text-sm text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($member->branch)
                                <p class="text-sm text-gray-800 dark:text-white">{{ $member->branch->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $member->branch->location }}</p>
                            @else
                                <span class="text-sm text-gray-400 dark:text-gray-500">No branch</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($statusBadge)
                                <span class="px-3 py-1 text-xs font-medium rounded-full {{ $statusBadge }}">{{ $statusLabel }}</span>
                            @else
                                <span class="text-sm text-gray-400 dark:text-gray-500">No records</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            <div class="flex flex-col items-center justify-center">
                                <i class="mb-3 text-4xl text-gray-300 fas fa-users"></i>
                                <p>No staff members found in this branch.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Default hint (shown before any staff is selected) --}}
    <div id="detailHint" class="flex flex-col items-center justify-center gap-2 p-12 text-gray-400 bg-white border border-gray-200 border-dashed rounded-2xl dark:bg-gray-800 dark:border-gray-600">
        <i class="text-4xl fas fa-hand-pointer"></i>
        <p class="font-medium text-gray-500 dark:text-gray-400">Select a staff member above</p>
        <p class="text-sm">Click any row to view deployment details and available actions.</p>
    </div>

    {{-- Detail Panel — populated by JavaScript --}}
    <div id="detailPanel" class="hidden space-y-4"></div>

</div>

{{-- ═══════════════════════════════════════════════════════════
     NEW DEPLOYMENT REQUEST MODAL (HR / Owner)
════════════════════════════════════════════════════════════ --}}
@if($canCreateDeployments)
<div id="newRequestModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black bg-opacity-50">
    <div class="flex items-center justify-center min-h-screen px-4 py-8">
        <div class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <form id="newRequestForm" action="{{ route('branch-deployments.store') }}" method="POST">
                @csrf
                <input type="hidden" name="staff_id" id="newRequestStaffId">

                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">New Deployment Request</h3>
                            <p id="newRequestSubtitle" class="mt-0.5 text-sm text-gray-500 dark:text-gray-400"></p>
                        </div>
                        <button type="button" onclick="closeNewRequestModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <i class="text-lg fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <div class="p-6 space-y-4">
                    {{-- Target Branch --}}
                    <div>
                        <label class="block mb-1.5 text-sm font-medium text-gray-900 dark:text-white">
                            Target Branch <span class="text-red-500">*</span>
                        </label>
                        <select name="to_branch_id" id="newRequestBranchSelect" required
                            class="block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="">Select target branch</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }} — {{ $branch->location }}</option>
                            @endforeach
                        </select>
                        @if($branches->isEmpty())
                            <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                <i class="mr-1 fa-solid fa-triangle-exclamation"></i>
                                No other branches found. Create another branch first.
                            </p>
                        @endif
                    </div>

                    {{-- Start Date --}}
                    <div>
                        <label class="block mb-1.5 text-sm font-medium text-gray-900 dark:text-white">
                            Start Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="start_date" id="newRequestStartDate" required
                            min="{{ now()->toDateString() }}"
                            class="block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>

                    {{-- Permanent Toggle --}}
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl dark:bg-gray-700">
                        <input type="checkbox" id="isPermanentToggle" name="is_permanent" value="1"
                            class="w-4 h-4 text-[#8B7355] border-gray-300 rounded focus:ring-[#8B7355]"
                            onchange="togglePermanent(this.checked)">
                        <div>
                            <label for="isPermanentToggle" class="text-sm font-medium text-gray-900 cursor-pointer dark:text-white">
                                Permanent transfer
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Staff stays indefinitely — no return to original branch.</p>
                        </div>
                    </div>

                    {{-- End Date --}}
                    <div id="endDateWrapper">
                        <label class="block mb-1.5 text-sm font-medium text-gray-900 dark:text-white">
                            End Date
                            <span class="font-normal text-gray-400">(optional)</span>
                        </label>
                        <input type="date" name="end_date" id="newRequestEndDate"
                            class="block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <p class="mt-1 text-xs text-gray-400">
                            If left blank, staff stays at the new branch until recalled or a new deployment request is submitted.
                        </p>
                    </div>

                    {{-- Notes --}}
                    <div>
                        <label class="block mb-1.5 text-sm font-medium text-gray-900 dark:text-white">
                            Notes
                            <span class="font-normal text-gray-400">(optional)</span>
                        </label>
                        <textarea name="notes" rows="3"
                            placeholder="Reason for deployment, special instructions, etc."
                            class="block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    <button type="button" onclick="closeNewRequestModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-5 py-2 text-sm font-medium text-white bg-[#8B7355] rounded-xl hover:bg-[#7A6348] focus:ring-4 focus:outline-none focus:ring-[#8B7355]/40">
                        <i class="mr-1.5 fa-solid fa-paper-plane"></i>
                        Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════
     REJECT MODAL (Owner)
════════════════════════════════════════════════════════════ --}}
@if($canApproveDeployments)
<div id="rejectModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black bg-opacity-50">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="w-full max-w-md bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <form id="rejectForm" method="POST">
                @csrf
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Reject Deployment</h3>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Provide a reason so HR can address the concern.</p>
                        </div>
                        <button type="button" onclick="closeRejectModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <i class="text-lg fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="p-6">
                    <label class="block mb-1.5 text-sm font-medium text-gray-900 dark:text-white">
                        Rejection Reason <span class="text-red-500">*</span>
                    </label>
                    <textarea name="rejection_reason" required rows="4"
                        placeholder="Explain why this deployment is being rejected..."
                        class="block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"></textarea>
                </div>
                <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    <button type="button" onclick="closeRejectModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-5 py-2 text-sm font-medium text-white bg-red-600 rounded-xl hover:bg-red-700 focus:ring-4 focus:outline-none focus:ring-red-300">
                        <i class="mr-1.5 fa-solid fa-ban"></i>
                        Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Hidden action forms (approve & cancel use these to POST without AJAX) --}}
<form id="approveForm" method="POST" class="hidden">
    @csrf
</form>
<form id="cancelForm" method="POST" class="hidden">
    @csrf
</form>

{{-- ═══════════════════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════════════════════ --}}
<script>
// Data passed from PHP — all deployment info preloaded for this branch (≤10 staff on Basic plan)
const staffData       = @json($staffDeploymentData);
const deployBaseUrl   = @json(url('/branch-deployments'));
const canCreate       = {{ $canCreateDeployments  ? 'true' : 'false' }};
const canApprove      = {{ $canApproveDeployments ? 'true' : 'false' }};
const canDelete       = {{ $canDeleteDeployments  ? 'true' : 'false' }};

// ── Label / colour maps ───────────────────────────────────────────────────────

const STATUS_LABELS = {
    pending:   'Pending Review',
    approved:  'Approved — Scheduled',
    rejected:  'Rejected',
    active:    'Active',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

const STATUS_COLORS = {
    pending:   'bg-yellow-100 text-yellow-800',
    approved:  'bg-blue-100   text-blue-800',
    rejected:  'bg-red-100    text-red-800',
    active:    'bg-green-100  text-green-800',
    completed: 'bg-gray-200   text-gray-700',
    cancelled: 'bg-gray-100   text-gray-500',
};

const ROLE_COLORS = {
    manager:      'bg-blue-100   text-blue-800',
    therapist:    'bg-green-100  text-green-800',
    receptionist: 'bg-yellow-100 text-yellow-800',
    hr:           'bg-purple-100 text-purple-800',
    finance:      'bg-orange-100 text-orange-800',
};

// ── Helpers ───────────────────────────────────────────────────────────────────

function statusBadge(status) {
    const color = STATUS_COLORS[status] || 'bg-gray-100 text-gray-600';
    const label = STATUS_LABELS[status] || status;
    return `<span class="px-2.5 py-1 text-xs font-medium rounded-full ${color}">${label}</span>`;
}

function esc(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ── Staff row selection ───────────────────────────────────────────────────────

function selectStaff(staffId) {
    // Highlight selected row, clear others
    document.querySelectorAll('.staff-row').forEach(r => {
        r.classList.remove('bg-amber-50', 'dark:bg-amber-900/10', 'border-l-[#8B7355]');
        r.style.borderLeftColor = '';
    });
    const row = document.getElementById('staff-row-' + staffId);
    if (row) {
        row.classList.add('bg-amber-50', 'dark:bg-amber-900/10');
        row.style.borderLeftColor = '#8B7355';
    }

    // Hide hint, show panel
    document.getElementById('detailHint').classList.add('hidden');
    const panel = document.getElementById('detailPanel');
    panel.classList.remove('hidden');

    const data = staffData[staffId];
    if (!data) {
        panel.innerHTML = '<p class="p-8 text-center text-red-500">Staff data not found.</p>';
        return;
    }

    renderDetail(data, panel);
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ── Main detail panel renderer ────────────────────────────────────────────────

function renderDetail(data, panel) {
    const roleColor = ROLE_COLORS[data.role] || 'bg-gray-100 text-gray-700';
    const initials  = data.name ? data.name.charAt(0).toUpperCase() : 'S';
    const roleCap   = data.role ? data.role.charAt(0).toUpperCase() + data.role.slice(1) : '';

    // Find open/relevant deployments
    const pending  = data.deployments.find(d => d.status === 'pending');
    const approved = data.deployments.find(d => d.status === 'approved');
    const active   = data.deployments.find(d => d.status === 'active');

    // ── Action card based on current state ───────────────────────────────────

    let actionCard = '';

    if (pending) {
        // ── PENDING: On Review → Owner can Accept or Reject, HR can Cancel ──
        let ownerBtns = '';
        if (canApprove) {
            ownerBtns = `
                <div class="flex flex-wrap gap-2 mt-4">
                    <button onclick="doApprove(${pending.id})"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-xl hover:bg-green-700">
                        <i class="mr-1.5 fa-solid fa-check"></i>Approve
                    </button>
                    <button onclick="openRejectModal(${pending.id})"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-xl hover:bg-red-700">
                        <i class="mr-1.5 fa-solid fa-ban"></i>Reject
                    </button>
                </div>`;
        }
        let cancelBtn = '';
        if (canDelete) {
            cancelBtn = `
                <button onclick="doCancel(${pending.id})"
                    class="inline-flex items-center px-4 py-2 mt-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                    <i class="mr-1.5 fa-solid fa-xmark"></i>Cancel Request
                </button>`;
        }
        const notesHtml = pending.notes
            ? `<p class="mt-3 text-xs italic text-yellow-700 dark:text-yellow-400"><i class="mr-1 fa-solid fa-note-sticky"></i>${esc(pending.notes)}</p>`
            : '';
        actionCard = `
            <div class="p-5 border border-yellow-200 rounded-2xl bg-yellow-50 dark:bg-yellow-900/10 dark:border-yellow-800">
                <p class="mb-3 text-xs font-semibold tracking-wide text-yellow-800 uppercase dark:text-yellow-300">
                    <i class="mr-1 fa-solid fa-clock"></i>On Review — Pending Approval
                </p>
                <div class="grid grid-cols-2 text-sm text-yellow-900 gap-x-6 gap-y-2 dark:text-yellow-200">
                    <div><span class="font-medium">From:</span> ${esc(pending.from_branch.name)}</div>
                    <div><span class="font-medium">To:</span> ${esc(pending.to_branch.name)}</div>
                    <div><span class="font-medium">Start:</span> ${esc(pending.start_date_fmt)}</div>
                    <div><span class="font-medium">End:</span> ${esc(pending.end_date_fmt)}</div>
                </div>
                ${notesHtml}
                <p class="mt-3 text-xs text-yellow-700 dark:text-yellow-400">
                    Requested by: <strong>${esc(pending.requested_by)}</strong> &bull; ${esc(pending.created_at_fmt)}
                </p>
                ${ownerBtns}
                ${cancelBtn}
            </div>`;

    } else if (approved) {
        // ── APPROVED: Scheduled — Owner can still revoke ──────────────────────
        let revokeBtns = '';
        if (canApprove) {
            revokeBtns = `
                <div class="flex gap-2 mt-4">
                    <button onclick="openRejectModal(${approved.id})"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-xl hover:bg-red-700">
                        <i class="mr-1.5 fa-solid fa-rotate-left"></i>Revoke Approval
                    </button>
                </div>`;
        }
        actionCard = `
            <div class="p-5 border border-blue-200 rounded-2xl bg-blue-50 dark:bg-blue-900/10 dark:border-blue-800">
                <p class="mb-3 text-xs font-semibold tracking-wide text-blue-800 uppercase dark:text-blue-300">
                    <i class="mr-1 fa-solid fa-calendar-check"></i>Approved — Activates on Start Date
                </p>
                <div class="grid grid-cols-2 text-sm text-blue-900 gap-x-6 gap-y-2 dark:text-blue-200">
                    <div><span class="font-medium">From:</span> ${esc(approved.from_branch.name)}</div>
                    <div><span class="font-medium">To:</span> ${esc(approved.to_branch.name)}</div>
                    <div><span class="font-medium">Start:</span> ${esc(approved.start_date_fmt)}</div>
                    <div><span class="font-medium">End:</span> ${esc(approved.end_date_fmt)}</div>
                </div>
                <p class="mt-3 text-xs text-blue-700 dark:text-blue-400">
                    Approved by: <strong>${esc(approved.reviewed_by || '—')}</strong>
                </p>
                ${revokeBtns}
            </div>`;

    } else if (active) {
        // ── ACTIVE: Currently deployed ────────────────────────────────────────
        const permanentNote = active.is_permanent
            ? `<p class="mt-3 text-xs text-green-700 dark:text-green-400"><i class="mr-1 fa-solid fa-infinity"></i>Permanent transfer — will not revert automatically.</p>`
            : '';
        actionCard = `
            <div class="p-5 border border-green-200 rounded-2xl bg-green-50 dark:bg-green-900/10 dark:border-green-800">
                <p class="mb-3 text-xs font-semibold tracking-wide text-green-800 uppercase dark:text-green-300">
                    <i class="mr-1 fa-solid fa-location-dot"></i>Currently Deployed
                </p>
                <div class="grid grid-cols-2 text-sm text-green-900 gap-x-6 gap-y-2 dark:text-green-200">
                    <div><span class="font-medium">Original Branch:</span> ${esc(active.from_branch.name)}</div>
                    <div><span class="font-medium">Deployed To:</span> ${esc(active.to_branch.name)}</div>
                    <div><span class="font-medium">Since:</span> ${esc(active.start_date_fmt)}</div>
                    <div><span class="font-medium">Until:</span> ${esc(active.end_date_fmt)}</div>
                </div>
                ${permanentNote}
            </div>`;

    } else if (canCreate) {
        // ── NO OPEN REQUEST: Show deploy button ───────────────────────────────
        const branchName = data.branch ? esc(data.branch.name) : 'no branch';
        actionCard = `
            <div class="flex items-center justify-between gap-4 p-5 border border-gray-200 rounded-2xl bg-gray-50 dark:bg-gray-700 dark:border-gray-600">
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">No active deployment</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Currently assigned to <strong>${branchName}</strong>.</p>
                </div>
                <button onclick="openNewRequestModal(${data.id}, '${data.name.replace(/'/g, "&#39;")}')"
                    class="shrink-0 inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-[#8B7355] rounded-xl hover:bg-[#7A6348]">
                    <i class="mr-1.5 fa-solid fa-paper-plane"></i>Deploy Staff
                </button>
            </div>`;
    } else {
        // ── VIEW ONLY: No create permission ──────────────────────────────────
        const branchName = data.branch ? esc(data.branch.name) : 'no branch';
        actionCard = `
            <div class="p-5 border border-gray-200 rounded-2xl bg-gray-50 dark:bg-gray-700 dark:border-gray-600">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    No active deployment. Currently at <strong class="text-gray-700 dark:text-gray-200">${branchName}</strong>.
                </p>
            </div>`;
    }

    // ── History table ─────────────────────────────────────────────────────────

    let historySection = '';
    if (data.deployments.length > 0) {
        const rows = data.deployments.map(d => {
            const rejectionNote = d.rejection_reason
                ? `<span class="block text-xs text-red-500 mt-0.5 italic">${esc(d.rejection_reason)}</span>`
                : '';
            return `
                <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                    <td class="px-4 py-3 text-sm text-gray-800 dark:text-white">${esc(d.from_branch.name)}</td>
                    <td class="px-4 py-3 text-sm text-gray-800 dark:text-white">${esc(d.to_branch.name)}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">${esc(d.start_date_fmt)}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">${esc(d.end_date_fmt)}</td>
                    <td class="px-4 py-3">
                        ${statusBadge(d.status)}
                        ${rejectionNote}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">${esc(d.requested_by)}</td>
                    <td class="px-4 py-3 text-sm text-gray-400 dark:text-gray-500">${esc(d.created_at_fmt)}</td>
                </tr>`;
        }).join('');

        historySection = `
            <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h4 class="text-base font-semibold text-gray-900 dark:text-white">Deployment History</h4>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">All deployment records for this staff member.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase">From</th>
                                <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase">To</th>
                                <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase">Start</th>
                                <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase">End</th>
                                <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase">Requested By</th>
                                <th class="px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase">Date Filed</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                            ${rows}
                        </tbody>
                    </table>
                </div>
            </div>`;
    }

    // ── Assemble and inject ───────────────────────────────────────────────────

    panel.innerHTML = `
        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center gap-4 px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-center w-14 h-14 text-xl font-bold text-white rounded-full bg-[#8B7355] shrink-0">
                    ${initials}
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">${esc(data.name)}</h3>
                    <p class="text-sm text-gray-500 truncate dark:text-gray-400">${esc(data.email)}</p>
                </div>
                <div class="flex flex-col items-end gap-1.5 shrink-0">
                    ${roleCap ? `<span class="px-3 py-1 text-xs font-medium rounded-full ${roleColor}">${roleCap}</span>` : ''}
                    ${data.branch ? `<span class="text-xs text-gray-500 dark:text-gray-400"><i class="mr-1 fa-solid fa-location-dot"></i>${esc(data.branch.name)}</span>` : ''}
                </div>
            </div>
            <div class="p-6">
                ${actionCard}
            </div>
        </div>
        ${historySection}
    `;
}

// ── Modal controllers ─────────────────────────────────────────────────────────

function openNewRequestModal(staffId, staffName) {
    document.getElementById('newRequestStaffId').value = staffId;
    document.getElementById('newRequestSubtitle').textContent = 'For: ' + staffName;
    // Reset form fields
    document.getElementById('newRequestForm').reset();
    document.getElementById('newRequestStaffId').value = staffId;  // re-set after reset()
    document.getElementById('newRequestSubtitle').textContent = 'For: ' + staffName;
    document.getElementById('endDateWrapper').classList.remove('hidden');
    document.getElementById('newRequestModal').classList.remove('hidden');
}

function closeNewRequestModal() {
    document.getElementById('newRequestModal').classList.add('hidden');
}

function openRejectModal(deploymentId) {
    document.getElementById('rejectForm').action = `${deployBaseUrl}/${deploymentId}/reject`;
    document.querySelector('#rejectForm textarea').value = '';
    document.getElementById('rejectModal').classList.remove('hidden');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
}

// ── Action form submissions ───────────────────────────────────────────────────

function doApprove(deploymentId) {
    if (!confirm('Approve this deployment request?')) return;
    const form = document.getElementById('approveForm');
    form.action = `${deployBaseUrl}/${deploymentId}/approve`;
    form.submit();
}

function doCancel(deploymentId) {
    if (!confirm('Cancel this deployment request? This cannot be undone.')) return;
    const form = document.getElementById('cancelForm');
    form.action = `${deployBaseUrl}/${deploymentId}/cancel`;
    form.submit();
}

// ── Permanent toggle ──────────────────────────────────────────────────────────

function togglePermanent(checked) {
    const wrapper = document.getElementById('endDateWrapper');
    const input   = document.getElementById('newRequestEndDate');
    if (checked) {
        wrapper.classList.add('hidden');
        input.value = '';
    } else {
        wrapper.classList.remove('hidden');
    }
}

// ── Auto-select from URL ?staff_id= ──────────────────────────────────────────
// Allows the controller to redirect back to a specific staff's detail panel
// after an action (approve/reject/cancel/store).

const urlParams     = new URLSearchParams(window.location.search);
const preselectedId = parseInt(urlParams.get('staff_id'));
if (preselectedId && staffData[preselectedId]) {
    document.addEventListener('DOMContentLoaded', () => selectStaff(preselectedId));
}
</script>
@endsection
