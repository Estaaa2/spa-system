@extends('layouts.app')
@section('content')
<div class="p-6 mx-auto max-w-7xl">
    <x-page-header title="Interviews" subtitle="Review and approve interview results."/>

    <div class="mt-6 overflow-hidden bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Applicant</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Position</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Schedule</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($interviews as $interview)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-900">
                    <td class="px-6 py-4">
                        <p class="text-sm font-medium text-gray-800 dark:text-white">
                            {{ $interview->applicant->full_name }}
                        </p>
                        <p class="text-xs text-gray-500">{{ $interview->applicant->email }}</p>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                        {{ $interview->applicant->jobPosting->title ?? 'N/A' }}
                    </td>
                    <td class="px-6 py-4">
                        <p class="text-sm text-gray-800 dark:text-white">
                            {{ $interview->interview_date->format('M d, Y') }}
                        </p>
                        <p class="text-xs text-gray-500">{{ $interview->interview_time }}</p>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full
                            {{ $interview->status === 'pending'  ? 'bg-yellow-100 text-yellow-700' :
                              ($interview->status === 'approved' ? 'bg-green-100 text-green-700'  :
                               'bg-red-100 text-red-700') }}">
                            {{ ucfirst($interview->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        @can('manage interviews')
                        @if($interview->status === 'pending')
                            <div class="flex gap-2">
                                <form action="{{ route('hr.interviews.approve', $interview) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="px-3 py-1 text-xs text-white bg-green-600 rounded hover:bg-green-700">
                                        Approve
                                    </button>
                                </form>
                                <form action="{{ route('hr.interviews.reject', $interview) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="px-3 py-1 text-xs text-white bg-red-500 rounded hover:bg-red-600">
                                        Reject
                                    </button>
                                </form>
                            </div>
                        @elseif($interview->status === 'approved' && !$interview->staff_account_created)
                            <button onclick="openCreateStaffModal({{ $interview->id }}, '{{ $interview->applicant->full_name }}', '{{ $interview->applicant->email }}')"
                                class="px-3 py-1 text-xs font-semibold text-white bg-[#8B7355] rounded hover:bg-[#7A6348]">
                                <i class="mr-1 fa-solid fa-user-plus"></i> Create Account
                            </button>
                        @elseif($interview->staff_account_created)
                            <span class="text-xs font-semibold text-green-600">
                                <i class="mr-1 fa-solid fa-check"></i> Account Created
                            </span>
                        @else
                            <span class="text-xs text-gray-400">Rejected</span>
                        @endif
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-10 text-sm text-center text-gray-400">No interviews scheduled.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Create Staff Account Modal --}}
<div id="createStaffModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50">
    <div class="w-full max-w-md p-6 mx-auto mt-24 bg-white rounded-xl dark:bg-gray-800">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Create Staff Account</h2>
            <button onclick="closeCreateStaffModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="createStaffForm" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block mb-1 text-xs font-semibold text-gray-600">Full Name *</label>
                <input type="text" name="name" id="csName" required
                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:outline-none"/>
            </div>
            <div>
                <label class="block mb-1 text-xs font-semibold text-gray-600">Email *</label>
                <input type="email" name="email" id="csEmail" required
                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:outline-none"/>
            </div>
            <div>
                <label class="block mb-1 text-xs font-semibold text-gray-600">Role *</label>
                <select name="roles" required
                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:border-[#8B7355] focus:outline-none">
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
                    class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
                <button type="submit"
                    class="px-4 py-2 text-sm font-semibold text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348]">
                    Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const baseInterviewUrl = @json(url('/hr/interviews'));

function openCreateStaffModal(interviewId, name, email) {
    document.getElementById('csName').value  = name;
    document.getElementById('csEmail').value = email;
    document.getElementById('createStaffForm').action = `${baseInterviewUrl}/${interviewId}/create-staff`;
    document.getElementById('createStaffModal').classList.remove('hidden');
}
function closeCreateStaffModal() {
    document.getElementById('createStaffModal').classList.add('hidden');
}
</script>
@endsection
