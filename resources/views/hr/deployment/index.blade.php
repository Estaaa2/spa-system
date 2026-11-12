{{-- File path: resources/views/hr/deployment/index.blade.php --}}

@extends('layouts.app')

@section('title', 'Staff Deployment')
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

    // Button / input tokens, same block as staff/index.blade.php and appointments.blade.php
    // (design language §5). Shared with the JS-built detail panel through @json below.
    // Reject / revoke / cancel are status changes, not deletions: amber, never solid red (§8).
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';
    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'edit'    => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        'neutral' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
        'cancel'  => $btnBase . ' border border-amber-300 bg-white text-amber-800 hover:bg-amber-50 '
                   . 'dark:border-amber-700 dark:bg-gray-800 dark:text-amber-300 dark:hover:bg-amber-900/20',
        'process' => $btnBase . ' bg-amber-700 text-white hover:bg-amber-800',
    ];
    $inputClass = 'block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 '
                . 'dark:placeholder-gray-400 dark:text-white';
    $labelClass = 'block mb-2 text-sm font-medium text-gray-900 dark:text-white';
    $closeBtn = 'inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 '
              . 'hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200';

    // Role badges: same map and labels as the Staff page (canon palette §6).
    $roleColors = [
        'manager'      => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        'therapist'    => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'receptionist' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'hr'           => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
        'finance'      => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
        'hr_head'      => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
        'finance_head' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
    ];
    $roleColorFallback = 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
    $roleLabels = [];
    foreach (array_keys($roleColors) as $r) {
        $roleLabels[$r] = \App\Services\Payroll\PayrollPermissions::roleLabel($r);
    }

    // Deployment status / staff response chips (canon palette §6).
    $deployStatus = [
        'pending'   => ['Pending',   'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'],
        'approved'  => ['Approved',  'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'],
        'active'    => ['Active',    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
        'rejected'  => ['Rejected',  'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'],
        'completed' => ['Completed', 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300'],
        'cancelled' => ['Cancelled', 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'],
    ];
    $responseStatus = [
        'pending'  => ['Awaiting Response', 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'],
        'accepted' => ['Accepted', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
        'declined' => ['Declined', 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'],
    ];
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">

    <x-page-header
        title="Branch Deployment"
        subtitle="Manage and track staff branch deployment requests."
    />

    {{-- Summary cards (same recipe as the Staff page). Flash messages show as the layout's toast. --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        @foreach ([
            ['Pending', $pendingCount, 'Awaiting review', 'text-amber-700 dark:text-amber-300'],
            ['Approved', $approvedCount, 'Scheduled', 'text-blue-700 dark:text-blue-300'],
            ['Active', $activeCount, 'In progress', 'text-emerald-700 dark:text-emerald-300'],
            ['Completed', $completedCount, 'Finished', 'text-gray-900 dark:text-white'],
        ] as [$cardLabel, $cardValue, $cardHint, $cardColor])
            <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">{{ $cardLabel }}</p>
                <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                    <h3 class="text-2xl font-semibold sm:text-3xl {{ $cardColor }}">{{ $cardValue }}</h3>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $cardHint }}</span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Staff list --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-col gap-4 px-4 py-4 border-b border-gray-200 lg:flex-row lg:items-start lg:justify-between sm:px-6 dark:border-gray-700">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Staff Members</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Open a staff member to view or manage their deployment.</p>
            </div>
            <x-table-filters :action="route('deployment.index')" :shown="$staff->count()" :total="$allStaffCount ?? $staff->count()"
                             :active="$activeFilters ?? 0" noun="staff member"
                             :search="$filters['q'] ?? ''" search-placeholder="Name or email">
                <x-table-filters.select name="role" label="Role" all="All roles" :options="$roleOptions ?? []" :value="$filters['role'] ?? null" width="sm:w-40" />
                <x-table-filters.select name="deployment" label="Latest deployment" all="Any" :options="$deploymentOptions ?? []" :value="$filters['deployment'] ?? null" width="sm:w-44" />
            </x-table-filters>
        </div>
        <div class="md:overflow-x-auto">
            {{-- .rt collapses this table into stacked cards below md (design language §8). --}}
            <table role="table" class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Staff Member</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Role</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Current Branch</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Latest Deployment</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Staff Response</th>
                        <th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Actions</th>
                    </tr>
                </thead>
                <tbody role="rowgroup" class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($staff as $member)
                    @php
                        $latestStatus = $member->deployments->first()?->status;
                        $role = $member->user?->getRoleNames()->first();
                        $openDeploy = $member->deployments->whereIn('status', ['pending', 'approved'])->first();
                        $staffResponse = $openDeploy?->staff_response;
                        $needsReview = $member->deployments
                            ->whereIn('status', ['pending', 'approved'])
                            ->where('staff_response', 'declined')
                            ->isNotEmpty();
                        $memberName = trim(($member->user->first_name ?? '') . ' ' . ($member->user->middle_name ? $member->user->middle_name . ' ' : '') . ($member->user->last_name ?? ''));
                    @endphp
                    <tr role="row" id="staff-row-{{ $member->id }}" data-staff-row
                        class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                        <td role="cell" data-label="Staff Member" class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-10 h-10 text-sm font-semibold text-white rounded-full bg-[#8B7355] shrink-0" aria-hidden="true">
                                    {{ strtoupper(substr($member->user->first_name ?? 'S', 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="flex items-center gap-1.5 font-medium text-gray-900 dark:text-white">
                                        {{ $memberName }}
                                        @if($needsReview)
                                            <i class="text-xs text-red-600 fa-solid fa-triangle-exclamation dark:text-red-400" aria-hidden="true"></i>
                                            <span class="sr-only">Staff declined, needs review</span>
                                        @endif
                                    </p>
                                    <p class="text-sm text-gray-500 break-all dark:text-gray-400">{{ $member->user->email ?? 'No email' }}</p>
                                </div>
                            </div>
                        </td>
                        <td role="cell" data-label="Role" class="px-6 py-4">
                            @if($role)
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $roleColors[$role] ?? $roleColorFallback }}">{{ $roleLabels[$role] ?? \App\Services\Payroll\PayrollPermissions::roleLabel($role) }}</span>
                            @else
                                <span class="text-sm text-gray-500 dark:text-gray-400">—</span>
                            @endif
                        </td>
                        <td role="cell" data-label="Current Branch" class="px-6 py-4">
                            @if($member->branch)
                                <p class="text-sm text-gray-900 dark:text-white">{{ $member->branch->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $member->branch->location }}</p>
                            @else
                                <span class="text-sm text-gray-500 dark:text-gray-400">No branch</span>
                            @endif
                        </td>
                        <td role="cell" data-label="Latest Deployment" class="px-6 py-4">
                            @if(isset($deployStatus[$latestStatus]))
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $deployStatus[$latestStatus][1] }}">{{ $deployStatus[$latestStatus][0] }}</span>
                            @else
                                <span class="text-sm text-gray-500 dark:text-gray-400">No records</span>
                            @endif
                        </td>
                        <td role="cell" data-label="Staff Response" class="px-6 py-4">
                            @if(isset($responseStatus[$staffResponse]))
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $responseStatus[$staffResponse][1] }}">{{ $responseStatus[$staffResponse][0] }}</span>
                            @else
                                <span class="text-sm text-gray-500 dark:text-gray-400">—</span>
                            @endif
                        </td>
                        <td role="cell" class="px-6 py-4 rt-actions">
                            <button type="button" class="{{ $btn['edit'] }}" onclick="selectStaff({{ $member->id }})"
                                    aria-controls="detailPanel" aria-label="Manage deployment for {{ $memberName }}">
                                <i class="text-xs fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i>
                                Manage
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr role="row">
                        <td role="cell" colspan="6" class="px-6 py-12 text-center text-gray-500 rt-empty dark:text-gray-400">
                            <div class="flex flex-col items-center justify-center">
                                <i class="mb-3 text-4xl text-gray-400 fa-solid fa-users" aria-hidden="true"></i>
                                <p class="font-medium">{{ ($activeFilters ?? 0) > 0 ? 'No staff member matches these filters.' : 'No staff members found in this branch.' }}</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Shown before a staff member is selected --}}
    <div id="detailHint" class="p-4 bg-white border border-gray-200 border-dashed sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-600">
        <div class="flex flex-col items-center gap-2 py-8 text-center text-gray-500 dark:text-gray-400">
            <i class="text-4xl text-gray-400 fa-solid fa-hand-pointer" aria-hidden="true"></i>
            <p class="font-medium">Select a staff member above</p>
            <p class="text-sm">Use Manage to see deployment details and actions.</p>
        </div>
    </div>

    {{-- Detail panel, filled by JavaScript --}}
    <div id="detailPanel" class="hidden space-y-6" tabindex="-1" aria-live="polite"></div>
</div>

@if($canCreateDeployments)
{{-- New deployment request --}}
<div id="newRequestModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="newRequestTitle" aria-describedby="newRequestSubtitle"
             class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <form id="newRequestForm" action="{{ route('branch-deployments.store') }}" method="POST" novalidate>
                @csrf
                <input type="hidden" name="staff_id" id="newRequestStaffId">

                <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div>
                        <h2 id="newRequestTitle" class="text-lg font-semibold text-gray-900 dark:text-white">New Deployment Request</h2>
                        <p id="newRequestSubtitle" class="mt-1 text-sm text-gray-500 dark:text-gray-400"></p>
                    </div>
                    <button type="button" onclick="closeNewRequestModal()" aria-label="Close dialog" class="{{ $closeBtn }}">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="px-4 py-6 space-y-4 sm:px-6">
                    <div>
                        <label for="newRequestBranchSelect" class="{{ $labelClass }}">Target branch <x-required-mark /></label>
                        <select name="to_branch_id" id="newRequestBranchSelect" required class="{{ $inputClass }}">
                            <option value="">Select target branch</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }} — {{ $branch->location }}</option>
                            @endforeach
                        </select>
                        @if($branches->isEmpty())
                            <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                                <i class="mr-1 fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                No other branches found. Create another branch first.
                            </p>
                        @endif
                    </div>

                    <div>
                        <label for="newRequestStartDate" class="{{ $labelClass }}">Start date <x-required-mark /></label>
                        <input type="date" name="start_date" id="newRequestStartDate" required min="{{ now()->toDateString() }}" class="{{ $inputClass }}">
                    </div>

                    <div class="flex items-start gap-3 p-4 rounded-2xl bg-gray-50 dark:bg-gray-900/40">
                        <input type="checkbox" id="isPermanentToggle" name="is_permanent" value="1"
                               class="w-4 h-4 mt-0.5 rounded accent-[#8B7355]" onchange="togglePermanent(this.checked)">
                        <div>
                            <label for="isPermanentToggle" class="text-sm font-medium text-gray-900 cursor-pointer dark:text-white">Permanent transfer</label>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Staff stays indefinitely, with no return to the original branch.</p>
                        </div>
                    </div>

                    <div id="endDateWrapper">
                        <label for="newRequestEndDate" class="{{ $labelClass }}">End date <span class="font-normal text-gray-500 dark:text-gray-400">(optional)</span></label>
                        <input type="date" name="end_date" id="newRequestEndDate" class="{{ $inputClass }}">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">If left blank, staff stays at the new branch until recalled or a new request is filed.</p>
                    </div>

                    <div>
                        <label for="newRequestNotes" class="{{ $labelClass }}">Notes <span class="font-normal text-gray-500 dark:text-gray-400">(optional)</span></label>
                        <textarea name="notes" id="newRequestNotes" rows="3" maxlength="1000" placeholder="Reason for deployment, special instructions, etc." class="{{ $inputClass }}"></textarea>
                    </div>
                </div>

                <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeNewRequestModal()" class="w-full {{ $btn['neutral'] }} sm:w-auto">Cancel</button>
                        <button type="submit" class="w-full {{ $btn['primary'] }} sm:w-auto">
                            <i class="text-xs fa-solid fa-paper-plane" aria-hidden="true"></i>
                            Submit Request
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if($canApproveDeployments)
{{-- Reject / revoke deployment (status change) --}}
<div id="rejectModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="rejectModalTitle"
             class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <form id="rejectForm" method="POST" novalidate>
                @csrf
                <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div>
                        <h2 id="rejectModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Reject Deployment</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Give a reason so HR can address the concern.</p>
                    </div>
                    <button type="button" onclick="closeRejectModal()" aria-label="Close dialog" class="{{ $closeBtn }}">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="px-4 py-6 sm:px-6">
                    <label for="rejectReason" class="{{ $labelClass }}">Reason <x-required-mark /></label>
                    <textarea name="rejection_reason" id="rejectReason" required rows="4" maxlength="1000"
                              placeholder="Explain why this deployment is being rejected" class="{{ $inputClass }}"></textarea>
                </div>
                <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeRejectModal()" class="w-full {{ $btn['neutral'] }} sm:w-auto">Keep It</button>
                        <button type="submit" class="w-full {{ $btn['process'] }} sm:w-auto">
                            <i class="text-xs fa-solid fa-ban" aria-hidden="true"></i>
                            Confirm Rejection
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Confirm approve / cancel (replaces the browser's confirm() box) --}}
<div id="confirmActionModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="alertdialog" aria-modal="true" aria-labelledby="confirmActionTitle" aria-describedby="confirmActionText"
             class="w-full max-w-md p-6 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <h2 id="confirmActionTitle" class="text-lg font-semibold text-gray-900 dark:text-white"></h2>
            <p id="confirmActionText" class="mt-2 text-sm text-gray-500 dark:text-gray-400"></p>
            <form id="confirmActionForm" method="POST" class="flex flex-col-reverse gap-2 mt-6 sm:flex-row sm:justify-end">
                @csrf
                <button type="button" id="confirmActionDismiss" onclick="closeConfirmAction()" class="w-full {{ $btn['neutral'] }} sm:w-auto"></button>
                <button type="submit" id="confirmActionSubmit" class="w-full sm:w-auto"></button>
            </form>
        </div>
    </div>
</div>

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

// Chip colours and button tokens come from the @php block (one source with the table).
const STATUS_COLORS = @json(collect($deployStatus)->map(fn ($v) => $v[1]));
const ROLE_COLORS   = @json($roleColors);
const ROLE_LABELS   = @json($roleLabels);
const BTN           = @json($btn);

// ── Staff response label / colour maps ─────────────────────────────────────────

const RESPONSE_LABELS = {
    pending:  'Awaiting Response',
    accepted: 'Accepted',
    declined: 'Declined',
};

const RESPONSE_COLORS = @json(collect($responseStatus)->map(fn ($v) => $v[1]));

// ── Helpers ───────────────────────────────────────────────────────────────────

function statusBadge(status) {
    const color = STATUS_COLORS[status] || 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
    const label = STATUS_LABELS[status] || status;
    return `<span class="px-2 py-1 text-xs font-semibold rounded-full ${color}">${esc(label)}</span>`;
}

function responseBadge(response) {
    if (!response) return '<span class="text-sm text-gray-500 dark:text-gray-400">—</span>';
    const color = RESPONSE_COLORS[response] || 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
    const label = RESPONSE_LABELS[response] || response;
    return `<span class="px-2 py-1 text-xs font-semibold rounded-full ${color}">${esc(label)}</span>`;
}

function esc(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// ── Staff row selection ───────────────────────────────────────────────────────

function selectStaff(staffId) {
    // Mark the selected row (state on an attribute, design language §8).
    document.querySelectorAll('[data-staff-row]').forEach(r => r.removeAttribute('aria-current'));
    document.getElementById('staff-row-' + staffId)?.setAttribute('aria-current', 'true');

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
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    panel.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
    panel.focus({ preventScroll: true });
}

// ── Main detail panel renderer ────────────────────────────────────────────────

function renderDetail(data, panel) {
    const roleColor = ROLE_COLORS[data.role] || 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
    const initials  = data.name ? data.name.charAt(0).toUpperCase() : 'S';
    const roleCap   = data.role ? (ROLE_LABELS[data.role] || data.role) : '';

    // Find open/relevant deployments
    const pending  = data.deployments.find(d => d.status === 'pending');
    const approved = data.deployments.find(d => d.status === 'approved');
    const active   = data.deployments.find(d => d.status === 'active');

    // ── Helper: build the staff-response block shown inside pending/approved cards ──
    function staffResponseBlock(deploy) {
        if (deploy.staff_response === 'declined') {
            return `
                <div class="p-4 mt-3 border border-red-200 rounded-2xl bg-red-50 dark:bg-red-900/10 dark:border-red-800">
                    <p class="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-red-700 dark:text-red-300">
                        <i class="fa-solid fa-user-xmark" aria-hidden="true"></i> Staff Declined
                        ${deploy.staff_responded_at_fmt ? `<span class="font-normal text-red-600 dark:text-red-400">&bull; ${esc(deploy.staff_responded_at_fmt)}</span>` : ''}
                    </p>
                    ${deploy.staff_decline_reason
                        ? `<p class="mt-1.5 text-sm text-red-900 dark:text-red-200">"${esc(deploy.staff_decline_reason)}"</p>`
                        : `<p class="mt-1.5 text-xs italic text-red-500 dark:text-red-400">No reason provided.</p>`}
                    <p class="mt-2 text-xs text-red-700 dark:text-red-400">
                        This request was automatically cancelled. File a new deployment request if reassignment is still needed.
                    </p>
                </div>`;
        }
        if (deploy.staff_response === 'accepted') {
            return `
                <div class="flex flex-wrap items-center gap-1.5 p-3 mt-3 text-sm font-medium border rounded-2xl text-emerald-700 border-emerald-200 bg-emerald-50 dark:bg-emerald-900/10 dark:border-emerald-800 dark:text-emerald-300">
                    <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                    Staff accepted. This deployment can no longer be revoked.
                    ${deploy.staff_responded_at_fmt ? `<span class="font-normal">&bull; ${esc(deploy.staff_responded_at_fmt)}</span>` : ''}
                </div>`;
        }
        return `
            <div class="flex items-center gap-1.5 p-3 mt-3 text-sm font-medium text-gray-700 border border-gray-200 rounded-2xl bg-gray-50 dark:bg-gray-900/40 dark:border-gray-700 dark:text-gray-300">
                <i class="fa-regular fa-clock" aria-hidden="true"></i>
                Awaiting staff response
            </div>`;
    }

    // ── Action card based on current state ───────────────────────────────────

    let actionCard = '';

    if (pending) {
        let ownerBtns = '';
        let cancelBtn = '';

        if (pending.staff_response === 'accepted') {
            // Staff already committed — Owner/HR reviews and either approves
            // or rejects (with a reason) in one step.
            if (canApprove) {
                ownerBtns = `
                    <div class="flex flex-wrap gap-2 mt-4">
                        <button type="button" onclick="doApprove(${pending.id})" class="${BTN.primary}">
                            <i class="text-xs fa-solid fa-check" aria-hidden="true"></i>Approve
                        </button>
                        <button type="button" onclick="openRejectModal(${pending.id}, 'reject')" class="${BTN.cancel}">
                            <i class="text-xs fa-solid fa-ban" aria-hidden="true"></i>Reject
                        </button>
                    </div>`;
            }
        } else {
            // Still awaiting the staff's response — nothing to approve or
            // reject yet. HR can still withdraw a mistaken request while
            // waiting; once staff accepts, this option disappears.
            if (canDelete) {
                cancelBtn = `
                    <div class="flex flex-wrap gap-2 mt-4">
                        <button type="button" onclick="doCancel(${pending.id})" class="${BTN.cancel}">
                            <i class="text-xs fa-solid fa-ban" aria-hidden="true"></i>Cancel Request
                        </button>
                    </div>`;
            }
        }

        const notesHtml = pending.notes
            ? `<p class="mt-3 text-sm text-amber-800 dark:text-amber-300"><i class="mr-1 fa-solid fa-note-sticky" aria-hidden="true"></i>${esc(pending.notes)}</p>`
            : '';
        actionCard = `
            <div class="p-4 border sm:p-5 border-amber-200 rounded-2xl bg-amber-50 dark:bg-amber-900/10 dark:border-amber-800">
                <p class="mb-3 text-xs font-semibold tracking-wide uppercase text-amber-800 dark:text-amber-300">
                    <i class="mr-1 fa-solid fa-clock" aria-hidden="true"></i>On Review — Pending Approval
                </p>
                <div class="grid grid-cols-1 text-sm sm:grid-cols-2 text-amber-900 gap-x-6 gap-y-2 dark:text-amber-200">
                    <div><span class="font-medium">From:</span> ${esc(pending.from_branch.name)}</div>
                    <div><span class="font-medium">To:</span> ${esc(pending.to_branch.name)}</div>
                    <div><span class="font-medium">Start:</span> ${esc(pending.start_date_fmt)}</div>
                    <div><span class="font-medium">End:</span> ${esc(pending.end_date_fmt)}</div>
                </div>
                ${notesHtml}
                <p class="mt-3 text-xs text-amber-800 dark:text-amber-300">
                    Requested by: <strong>${esc(pending.requested_by)}</strong> &bull; ${esc(pending.created_at_fmt)}
                </p>
                ${staffResponseBlock(pending)}
                ${ownerBtns}
                ${cancelBtn}
            </div>`;

    } else if (approved) {
        // ── APPROVED: Scheduled — Owner can still revoke ──────────────────────
        let revokeBtns = '';
        if (canApprove && approved.staff_response !== 'accepted') {
            revokeBtns = `
                <div class="flex flex-wrap gap-2 mt-4">
                    <button type="button" onclick="openRejectModal(${approved.id}, 'revoke')" class="${BTN.cancel}">
                        <i class="text-xs fa-solid fa-rotate-left" aria-hidden="true"></i>Revoke Approval
                    </button>
                </div>`;
        }
        actionCard = `
            <div class="p-4 border border-blue-200 sm:p-5 rounded-2xl bg-blue-50 dark:bg-blue-900/10 dark:border-blue-800">
                <p class="mb-3 text-xs font-semibold tracking-wide text-blue-800 uppercase dark:text-blue-300">
                    <i class="mr-1 fa-solid fa-calendar-check" aria-hidden="true"></i>Approved — Activates on Start Date
                </p>
                <div class="grid grid-cols-1 text-sm text-blue-900 sm:grid-cols-2 gap-x-6 gap-y-2 dark:text-blue-200">
                    <div><span class="font-medium">From:</span> ${esc(approved.from_branch.name)}</div>
                    <div><span class="font-medium">To:</span> ${esc(approved.to_branch.name)}</div>
                    <div><span class="font-medium">Start:</span> ${esc(approved.start_date_fmt)}</div>
                    <div><span class="font-medium">End:</span> ${esc(approved.end_date_fmt)}</div>
                </div>
                <p class="mt-3 text-xs text-blue-700 dark:text-blue-400">
                    Approved by: <strong>${esc(approved.reviewed_by || '—')}</strong>
                </p>
                ${approved.is_self_requested ? `
                <span class="inline-flex items-center gap-1 px-2 py-1 mt-2 text-xs font-semibold text-blue-700 bg-blue-100 rounded-full dark:bg-blue-900/40 dark:text-blue-300">
                    <i class="fa-solid fa-user" aria-hidden="true"></i> Requested by staff
                </span>` : ''}
                ${staffResponseBlock(approved)}
                ${approved.staff_response !== 'accepted' ? `
                    <p class="mt-2 text-xs text-blue-700 dark:text-blue-300">
                        <i class="mr-1 fa-solid fa-circle-info" aria-hidden="true"></i>Will not auto-activate on the start date until the staff member accepts.
                    </p>` : ''}
                ${revokeBtns}
            </div>`;

    } else if (active) {
        // ── ACTIVE: Currently deployed ────────────────────────────────────────
        const permanentNote = active.is_permanent
            ? `<p class="mt-3 text-xs text-emerald-700 dark:text-emerald-300"><i class="mr-1 fa-solid fa-infinity" aria-hidden="true"></i>Permanent transfer — will not revert automatically.</p>`
            : '';
        actionCard = `
            <div class="p-4 border sm:p-5 border-emerald-200 rounded-2xl bg-emerald-50 dark:bg-emerald-900/10 dark:border-emerald-800">
                <p class="mb-3 text-xs font-semibold tracking-wide uppercase text-emerald-800 dark:text-emerald-300">
                    <i class="mr-1 fa-solid fa-location-dot" aria-hidden="true"></i>Currently Deployed
                </p>
                <div class="grid grid-cols-1 text-sm sm:grid-cols-2 text-emerald-900 gap-x-6 gap-y-2 dark:text-emerald-200">
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
            <div class="flex flex-col gap-4 p-4 border border-gray-200 sm:p-5 sm:flex-row sm:items-center sm:justify-between rounded-2xl bg-gray-50 dark:bg-gray-900/40 dark:border-gray-700">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">No active deployment</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Currently assigned to <strong>${branchName}</strong>.</p>
                </div>
                <button type="button" data-staff-id="${data.id}" data-staff-name="${esc(data.name)}"
                        onclick="openNewRequestModal(this.dataset.staffId, this.dataset.staffName)"
                        class="w-full sm:w-auto ${BTN.primary}">
                    <i class="text-xs fa-solid fa-paper-plane" aria-hidden="true"></i>Deploy Staff
                </button>
            </div>`;
    } else {
        // ── VIEW ONLY: No create permission ──────────────────────────────────
        const branchName = data.branch ? esc(data.branch.name) : 'no branch';
        actionCard = `
            <div class="p-4 border border-gray-200 sm:p-5 rounded-2xl bg-gray-50 dark:bg-gray-900/40 dark:border-gray-700">
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
                ? `<span class="block mt-1 text-xs text-red-700 dark:text-red-300">${esc(d.rejection_reason)}</span>`
                : '';
            const declineNote = d.staff_response === 'declined' && d.staff_decline_reason
                ? `<span class="block mt-1 text-xs text-red-700 dark:text-red-300">"${esc(d.staff_decline_reason)}"</span>`
                : '';
            return `
                <tr role="row" class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                    <td role="cell" data-label="From" class="px-6 py-4 text-sm text-gray-900 dark:text-white">${esc(d.from_branch.name)}</td>
                    <td role="cell" data-label="To" class="px-6 py-4 text-sm text-gray-900 dark:text-white">${esc(d.to_branch.name)}</td>
                    <td role="cell" data-label="Start" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">${esc(d.start_date_fmt)}</td>
                    <td role="cell" data-label="End" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">${esc(d.end_date_fmt)}</td>
                    <td role="cell" data-label="Status" class="px-6 py-4">
                        ${statusBadge(d.status)}
                        ${rejectionNote}
                    </td>
                    <td role="cell" data-label="Staff Response" class="px-6 py-4">
                        ${responseBadge(d.staff_response)}
                        ${declineNote}
                    </td>
                    <td role="cell" data-label="Requested By" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                        ${esc(d.requested_by)}
                        ${d.is_self_requested ? '<span class="block text-xs text-gray-500 dark:text-gray-400">(self-requested)</span>' : ''}
                    </td>
                    <td role="cell" data-label="Date Filed" class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">${esc(d.created_at_fmt)}</td>
                </tr>`;
        }).join('');

        historySection = `
            <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Deployment History</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">All deployment records for this staff member.</p>
                </div>
                <div class="md:overflow-x-auto">
                    <table role="table" class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700">
                        <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                            <tr role="row">
                                ${['From', 'To', 'Start', 'End', 'Status', 'Staff Response', 'Requested By', 'Date Filed'].map(h =>
                                    `<th role="columnheader" class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">${h}</th>`).join('')}
                            </tr>
                        </thead>
                        <tbody role="rowgroup" class="divide-y divide-gray-200 dark:divide-gray-700">
                            ${rows}
                        </tbody>
                    </table>
                </div>
            </div>`;
    }

    // ── Assemble and inject ───────────────────────────────────────────────────

    panel.innerHTML = `
        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-col gap-3 px-4 py-4 border-b border-gray-200 sm:flex-row sm:items-center sm:px-6 dark:border-gray-700">
                <div class="flex items-center flex-1 min-w-0 gap-3">
                    <div class="flex items-center justify-center w-12 h-12 text-lg font-semibold text-white rounded-full bg-[#8B7355] shrink-0" aria-hidden="true">
                        ${esc(initials)}
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">${esc(data.name)}</h2>
                        <p class="text-sm text-gray-500 break-all dark:text-gray-400">${esc(data.email)}</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 sm:flex-col sm:items-end">
                    ${roleCap ? `<span class="px-2 py-1 text-xs font-semibold rounded-full ${roleColor}">${esc(roleCap)}</span>` : ''}
                    ${data.branch ? `<span class="text-sm text-gray-500 dark:text-gray-400"><i class="mr-1 fa-solid fa-location-dot" aria-hidden="true"></i>${esc(data.branch.name)}</span>` : ''}
                </div>
            </div>
            <div class="p-4 sm:p-5">
                ${actionCard}
            </div>
        </div>
        ${historySection}
    `;
}

// ── Shared modal behaviour (same as staff/index.blade.php) ───────────────────
const MODAL_IDS = ['newRequestModal', 'rejectModal', 'confirmActionModal'];
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
let lastFocused = null;

function topmostOpenModal() {
    for (let i = MODAL_IDS.length - 1; i >= 0; i--) {
        const el = document.getElementById(MODAL_IDS[i]);
        if (el && !el.classList.contains('hidden')) return el;
    }
    return null;
}
function openModal(id, focusSelector) {
    const el = document.getElementById(id);
    if (!el) return;
    lastFocused = document.activeElement;
    el.classList.remove('hidden');
    const target = (focusSelector && el.querySelector(focusSelector)) || el.querySelector(FOCUSABLE);
    if (target) target.focus();
}
function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('hidden');
    if (lastFocused && document.contains(lastFocused)) lastFocused.focus();
    lastFocused = null;
}
document.addEventListener('keydown', function (e) {
    const modal = topmostOpenModal();
    if (!modal) return;
    if (e.key === 'Escape') { e.preventDefault(); closeModal(modal.id); return; }
    if (e.key !== 'Tab') return;
    const items = Array.from(modal.querySelectorAll(FOCUSABLE)).filter(el => el.offsetParent !== null);
    if (!items.length) return;
    const first = items[0], last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
});
// Backdrop click closes the confirm box only; the request and reject forms keep typed input (§8).
document.getElementById('confirmActionModal')?.addEventListener('click', function (e) {
    if (e.target === this || e.target === this.firstElementChild) closeModal(this.id);
});

function openNewRequestModal(staffId, staffName) {
    const form = document.getElementById('newRequestForm');
    form.reset();
    document.getElementById('newRequestStaffId').value = staffId;
    document.getElementById('newRequestSubtitle').textContent = 'For: ' + staffName;
    document.getElementById('endDateWrapper').classList.remove('hidden');
    openModal('newRequestModal', '#newRequestBranchSelect');
}
function closeNewRequestModal() { closeModal('newRequestModal'); }

function openRejectModal(deploymentId, mode) {
    document.getElementById('rejectForm').action = `${deployBaseUrl}/${deploymentId}/reject`;
    document.getElementById('rejectReason').value = '';
    document.getElementById('rejectModalTitle').textContent = mode === 'revoke' ? 'Revoke Approval' : 'Reject Deployment';
    openModal('rejectModal', '#rejectReason');
}
function closeRejectModal() { closeModal('rejectModal'); }

// Approve / cancel go through an in-page confirmation (was the browser's confirm()).
function openConfirmAction({ title, text, action, confirmLabel, confirmClass, dismissLabel }) {
    document.getElementById('confirmActionTitle').textContent = title;
    document.getElementById('confirmActionText').textContent = text;
    document.getElementById('confirmActionForm').action = action;
    const submit = document.getElementById('confirmActionSubmit');
    submit.className = 'w-full sm:w-auto ' + confirmClass;
    submit.textContent = confirmLabel;
    document.getElementById('confirmActionDismiss').textContent = dismissLabel;
    openModal('confirmActionModal', '#confirmActionDismiss');
}
function closeConfirmAction() { closeModal('confirmActionModal'); }

function doApprove(deploymentId) {
    openConfirmAction({
        title: 'Approve Deployment',
        text: 'The staff member moves to the new branch on the start date.',
        action: `${deployBaseUrl}/${deploymentId}/approve`,
        confirmLabel: 'Yes, Approve',
        confirmClass: BTN.primary,
        dismissLabel: 'Not Yet',
    });
}

function doCancel(deploymentId) {
    openConfirmAction({
        title: 'Cancel Deployment Request',
        text: 'This withdraws the request. It stays in the history as Cancelled and cannot be reopened.',
        action: `${deployBaseUrl}/${deploymentId}/cancel`,
        confirmLabel: 'Yes, Cancel Request',
        confirmClass: BTN.process,
        dismissLabel: 'Keep Request',
    });
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

<style>
/* Selected staff row (state on aria-current, design language §8). */
tr[data-staff-row][aria-current="true"] { background-color: rgb(255 251 235); box-shadow: inset 4px 0 0 #8B7355; }
@media (prefers-color-scheme: dark) {
    tr[data-staff-row][aria-current="true"] { background-color: rgb(120 53 15 / 0.1); }
}

@media (max-width: 767px) {
    .rt,
    .rt tbody,
    .rt tr,
    .rt td { display: block; width: 100%; }

    .rt thead { display: none; }

    .rt tr { padding: 0.75rem 1rem; }

    .rt td {
        padding: 0.375rem 0 !important;
        text-align: left !important;
    }

    .rt td[data-label]::before {
        content: attr(data-label);
        display: block;
        margin-bottom: 0.125rem;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .rt td.rt-actions { padding-top: 0.75rem !important; }

    .rt td.rt-empty {
        padding: 2rem 0 !important;
        text-align: center !important;
    }
}

@media (max-width: 767px) and (prefers-color-scheme: dark) {
    .rt td[data-label]::before { color: #9ca3af; }
}
</style>
@endsection
