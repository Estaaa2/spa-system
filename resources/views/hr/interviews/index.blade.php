@extends('layouts.app')

@section('content')
@php
    $user = auth()->user();

    $canViewInterviews   = $user?->hasBranchPermission('view interviews') ?? false;
    $canCreateInterviews = $user?->hasBranchPermission('create interviews') ?? false;
    $canEditInterviews   = $user?->hasBranchPermission('edit interviews') ?? false;
    $canDeleteInterviews = $user?->hasBranchPermission('delete interviews') ?? false;

    $canCreateStaff = $user?->hasBranchPermission('create staff') ?? false;

    $canReviewInterview = $canEditInterviews;
    $canCreateStaffFromInterview = $canEditInterviews && $canCreateStaff;

    $statusClasses = [
        'pending'  => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
        'approved' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    ];

    $positionLabel = function ($interview) {
        $applicant = $interview->applicant;

        if (!$applicant) {
            return 'N/A';
        }

        if (!empty($applicant->position_applied)) {
            return ucwords(str_replace('_', ' ', $applicant->position_applied));
        }

        if (!empty($applicant->role)) {
            return ucwords(str_replace('_', ' ', $applicant->role));
        }

        if (!empty($applicant->jobPosting?->title)) {
            return $applicant->jobPosting->title;
        }

        return 'N/A';
    };

    $summary = [
        'total'    => $interviews->count(),
        'pending'  => $interviews->where('status', 'pending')->count(),
        'approved' => $interviews->where('status', 'approved')->count(),
        'rejected' => $interviews->where('status', 'rejected')->count(),
    ];
@endphp

<div class="p-6 mx-auto space-y-6 max-w-7xl">
    <x-page-header
        title="Interviews"
        subtitle="Review scheduled interviews, record outcomes, and create staff accounts for approved applicants."
    />

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Total Interviews</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-gray-900 dark:text-white">{{ $summary['total'] }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">All records</span>
            </div>
        </div>

        <div class="p-5 border shadow-sm bg-amber-50 border-amber-200 rounded-2xl dark:bg-amber-900/10 dark:border-amber-800">
            <p class="text-xs font-semibold tracking-wide uppercase text-amber-700 dark:text-amber-300">Pending Review</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-amber-900 dark:text-amber-200">{{ $summary['pending'] }}</h3>
                <span class="text-sm text-amber-700 dark:text-amber-300">Awaiting decision</span>
            </div>
        </div>

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Approved</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-gray-900 dark:text-white">{{ $summary['approved'] }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">Passed interview</span>
            </div>
        </div>

        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Rejected</p>
            <div class="flex items-end justify-between mt-3">
                <h3 class="text-3xl font-semibold text-gray-900 dark:text-white">{{ $summary['rejected'] }}</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">Not selected</span>
            </div>
        </div>
    </div>

    {{-- Filter Tabs --}}
    <div class="flex flex-wrap gap-2">
        @foreach(['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $val => $label)
            <button
                type="button"
                onclick="filterStatus('{{ $val }}')"
                id="filter-{{ $val }}"
                class="px-3 py-1 text-xs font-semibold rounded-full border transition
                {{ $val === 'all' ? 'bg-[#8B7355] text-white border-[#8B7355]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#8B7355] hover:text-[#8B7355] dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600' }}">
                {{ $label }}
                @if($val !== 'all')
                    <span class="ml-1">({{ $interviews->where('status', $val)->count() }})</span>
                @else
                    <span class="ml-1">({{ $interviews->count() }})</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Interviews Table --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Interview Records</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Review applicant interviews, approve or reject them, and create staff accounts for approved candidates.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Applicant</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Applied Position</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Schedule</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700" id="interviewsList">
                    @forelse($interviews as $interview)
                        @php
                            $dateLabel = $interview->interview_date
                                ? \Carbon\Carbon::parse($interview->interview_date)->format('M d, Y')
                                : 'N/A';

                            $timeLabel = $interview->interview_time
                                ? \Carbon\Carbon::parse($interview->interview_time)->format('h:i A')
                                : 'N/A';
                        @endphp

                        <tr class="transition interview-row hover:bg-gray-50 dark:hover:bg-gray-900/40"
                            data-status="{{ $interview->status }}">
                            <td class="px-6 py-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-800 dark:text-white">
                                        {{ $interview->applicant->full_name ?? 'Unknown Applicant' }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ $interview->applicant->email ?? 'No email' }}
                                    </p>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-[#F6EFE6] text-[#6F5430]">
                                    {{ $positionLabel($interview) }}
                                </span>
                            </td>

                            <td class="px-6 py-4">
                                <p class="text-sm text-gray-800 dark:text-white">{{ $dateLabel }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $timeLabel }}</p>
                            </td>

                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $statusClasses[$interview->status] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                                    {{ ucfirst($interview->status) }}
                                </span>
                            </td>

                            <td class="px-6 py-4">
                                @if($interview->status === 'pending')
                                    @if($canReviewInterview)
                                        <div class="flex flex-wrap gap-2">
                                            <form action="{{ route('interviews.approve', $interview) }}" method="POST">
                                                @csrf
                                                <button type="submit"
                                                    class="px-3 py-1.5 text-xs font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 transition">
                                                    Approve
                                                </button>
                                            </form>

                                            <form action="{{ route('interviews.reject', $interview) }}" method="POST">
                                                @csrf
                                                <button type="submit"
                                                    class="px-3 py-1.5 text-xs font-semibold text-white bg-red-500 rounded-lg hover:bg-red-600 transition">
                                                    Reject
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">Pending review</span>
                                    @endif

                                @elseif($interview->status === 'approved' && !$interview->staff_account_created)
                                    @if($canCreateStaffFromInterview)
                                        <button type="button"
                                            onclick="openCreateStaffModal({{ $interview->id }}, '{{ addslashes($interview->applicant->full_name ?? '') }}', '{{ addslashes($interview->applicant->email ?? '') }}', '{{ $interview->applicant->position_applied ?? $interview->applicant->role ?? '' }}')"
                                            class="px-3 py-1.5 text-xs font-semibold text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348] transition">
                                            <i class="mr-1 fa-solid fa-user-plus"></i> Create Account
                                        </button>
                                    @else
                                        <span class="text-xs font-medium text-green-600">
                                            <i class="mr-1 fa-solid fa-check-circle"></i> Approved
                                        </span>
                                    @endif

                                @elseif($interview->staff_account_created)
                                    <span class="text-xs font-semibold text-green-600">
                                        <i class="mr-1 fa-solid fa-check"></i> Account Created
                                    </span>
                                @else
                                    <span class="text-xs text-red-400">
                                        <i class="mr-1 fa-solid fa-times-circle"></i> Rejected
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-sm text-center text-gray-400 dark:text-gray-500">
                                No interviews scheduled.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Create Staff Account Modal --}}
@if($canCreateStaffFromInterview)
<div id="createStaffModal" class="fixed inset-0 z-50 hidden p-4 bg-black/50">
    <div class="w-full max-w-md p-6 mx-auto mt-24 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Create Staff Account</h2>
            <button type="button" onclick="closeCreateStaffModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="createStaffForm" method="POST" class="space-y-4">
            @csrf

            <div>
                <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">Full Name *</label>
                <input type="text" name="name" id="csName" required
                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"/>
            </div>

            <div>
                <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">Email *</label>
                <input type="email" name="email" id="csEmail" required
                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"/>
            </div>

            <div>
                <label class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-400">Role *</label>
                <select name="roles" id="csRole" required
                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">Select role</option>
                    <option value="therapist">Therapist</option>
                    <option value="receptionist">Receptionist</option>
                    <option value="manager">Manager</option>
                    <option value="hr">HR</option>
                    <option value="finance">Finance</option>
                </select>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeCreateStaffModal()"
                    class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                    Cancel
                </button>
                <button type="submit"
                    class="px-4 py-2 text-sm font-semibold text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348]">
                    Create Account
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
function filterStatus(status) {
    document.querySelectorAll('.interview-row').forEach(row => {
        row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
    });

    document.querySelectorAll('[id^="filter-"]').forEach(btn => {
        btn.classList.remove('bg-[#8B7355]', 'text-white', 'border-[#8B7355]');
        btn.classList.add('bg-white', 'text-gray-600', 'border-gray-200');

        if (btn.classList.contains('dark:bg-gray-800') === false) {
            btn.classList.add('dark:bg-gray-800', 'dark:text-gray-300', 'dark:border-gray-600');
        }
    });

    const active = document.getElementById('filter-' + status);
    if (active) {
        active.classList.add('bg-[#8B7355]', 'text-white', 'border-[#8B7355]');
        active.classList.remove('bg-white', 'text-gray-600', 'border-gray-200');
    }
}

@if($canCreateStaffFromInterview)
const baseInterviewUrl = @json(url('/interviews'));

function openCreateStaffModal(interviewId, name, email, role = '') {
    document.getElementById('csName').value = name || '';
    document.getElementById('csEmail').value = email || '';
    document.getElementById('csRole').value = role || '';
    document.getElementById('createStaffForm').action = `${baseInterviewUrl}/${interviewId}/create-staff`;
    document.getElementById('createStaffModal').classList.remove('hidden');
}

function closeCreateStaffModal() {
    document.getElementById('createStaffModal').classList.add('hidden');
}
@endif
</script>
@endsection