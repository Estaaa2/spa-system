@extends('layouts.app')

@section('content')
<div class="p-6">

    <x-page-header
        title="Staff Management"
        subtitle="Add, edit, and manage your spa staff members."
    />

    @php
        $spa = auth()->user()->spa;
        $isProfessional = $spa->isProfessional();
    @endphp

    <div class="mb-6">
        <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">Add New Staff Member</h2>

            @can('create staff')
            <form action="{{ route('staff.store') }}" method="POST" id="addStaffForm">
                @csrf

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Email *</label>
                        <input
                            type="email"
                            name="email"
                            required
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#8B7355] focus:border-[#8B7355] block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                            placeholder="staff@example.com"
                            value="{{ old('email') }}"
                        >
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Full Name *</label>
                        <input
                            type="text"
                            name="name"
                            required
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#8B7355] focus:border-[#8B7355] block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                            placeholder="John Doe"
                            value="{{ old('name') }}"
                        >
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Role *</label>
                        <select
                            name="roles"
                            required
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#8B7355] focus:border-[#8B7355] block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                        >
                            <option value="">Select Role</option>

                            {{-- Always available --}}
                            <optgroup label="Spa Staff">
                                <option value="therapist" {{ old('roles') == 'therapist' ? 'selected' : '' }}>Therapist</option>
                                <option value="receptionist" {{ old('roles') == 'receptionist' ? 'selected' : '' }}>Receptionist</option>
                                <option value="manager" {{ old('roles') == 'manager' ? 'selected' : '' }}>Manager</option>
                            </optgroup>

                            {{-- ✅ Professional only --}}
                            @if($isProfessional)
                            <optgroup label="Professional Roles ✦">
                                <option value="hr" {{ old('roles') == 'hr' ? 'selected' : '' }}>HR</option>
                                <option value="finance" {{ old('roles') == 'finance' ? 'selected' : '' }}>Finance</option>
                            </optgroup>
                            @endif

                        </select>
                        @error('roles')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        {{-- Show upgrade hint for Basic owners --}}
                        @if(!$isProfessional)
                            <p class="mt-1 text-xs text-gray-400">
                                <i class="fa-solid fa-lock text-[#8B7355]"></i>
                                HR & Finance roles require the
                                <a href="{{ route('owner.subscription.index') }}" class="text-[#8B7355] underline font-medium">
                                    Professional plan
                                </a>
                            </p>
                        @endif
                    </div>
                </div>

                <div class="flex justify-end mt-6">
                    <button
                        type="submit"
                        class="px-4 py-2.5 text-sm font-medium text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348] focus:ring-4 focus:outline-none focus:ring-[#8B7355]/50"
                    >
                        Add Staff Member
                    </button>
                </div>
            </form>
            @endcan
        </div>
    </div>

    <div>
        <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Staff Members</h2>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $staff->count() }} staff member(s)
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Staff Member</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Role</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Branch</th>
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        @forelse($staff as $member)
                        <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-[#8B7355] flex items-center justify-center text-white font-semibold text-sm">
                                        {{ strtoupper(substr($member->user->name ?? 'S', 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $member->user->name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $member->user->email ?? 'No email' }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                @php $role = $member->user?->getRoleNames()->first(); @endphp
                                @if($role)
                                    @php
                                        $roleColors = [
                                            'manager'      => 'bg-blue-100 text-blue-800',
                                            'therapist'    => 'bg-green-100 text-green-800',
                                            'receptionist' => 'bg-yellow-100 text-yellow-800',
                                            'hr'           => 'bg-purple-100 text-purple-800',
                                            'finance'      => 'bg-orange-100 text-orange-800',
                                        ];
                                        $colorClass = $roleColors[$role] ?? 'bg-gray-100 text-gray-800';
                                    @endphp
                                    <span class="px-3 py-1 text-xs font-medium rounded-full {{ $colorClass }}">
                                        {{ ucfirst($role) }}
                                        @if(in_array($role, ['hr', 'finance']))
                                            <i class="fa-solid fa-star text-[10px] ml-0.5"></i>
                                        @endif
                                    </span>
                                @else
                                    <span class="px-3 py-1 text-xs font-medium text-gray-500 bg-gray-200 rounded-full">
                                        No role
                                    </span>
                                @endif
                            </td>

                            <td class="px-6 py-4">
                                @if($member->branch)
                                    <div class="text-sm text-gray-800 dark:text-white">
                                        {{ $member->branch->name }}
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $member->branch->location }}
                                        </p>
                                    </div>
                                @else
                                    <span class="text-sm text-gray-400 dark:text-gray-500">No branch assigned</span>
                                @endif
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    @can('edit staff')
                                    <button
                                        type="button"
                                        onclick="editStaff({{ $member->id }}, {{ $isProfessional ? 'true' : 'false' }})"
                                        class="px-3 py-1 text-sm text-white bg-yellow-500 rounded hover:bg-yellow-600"
                                    >
                                        Edit
                                    </button>
                                    @endcan

                                    @can('delete staff')
                                    <button
                                        type="button"
                                        onclick='openDeleteModal({{ $member->id }}, @json($member->user->name))'
                                        class="px-3 py-1 text-sm text-white bg-red-600 rounded hover:bg-red-700"
                                    >
                                        Delete
                                    </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="mb-3 text-4xl text-gray-400 fas fa-users"></i>
                                    <p class="mb-2 text-gray-600 dark:text-gray-400">No staff members found</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-500">Add your first staff member using the form above</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Edit Modal --}}
<div id="editModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div>

        <div class="inline-block w-full max-w-md my-8 overflow-hidden text-left align-middle transition-all transform bg-white rounded-lg shadow-xl dark:bg-gray-800">
            <form id="editStaffForm" method="POST">
                @csrf
                @method('PUT')

                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Edit Staff Member</h3>
                        <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="space-y-4" id="editFormContent"></div>
                </div>

                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900">
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeEditModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-[#8B7355] border border-transparent rounded-md shadow-sm hover:bg-[#7A6348]">
                            Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Delete Modal --}}
@can('delete staff')
<div id="deleteModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50">
    <div class="w-full max-w-md p-6 mx-auto mt-24 bg-white rounded-lg dark:bg-gray-800">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Confirm Delete</h2>
            <button type="button" onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <p class="text-gray-500 dark:text-gray-400">
            Are you sure you want to delete
            <span id="deleteStaffName" class="font-semibold text-gray-800 dark:text-white"></span>?
            This action cannot be undone.
        </p>
        <div class="flex justify-end gap-2 mt-6">
            <button type="button" onclick="closeDeleteModal()"
                class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200">
                Cancel
            </button>
            <form id="deleteStaffForm" method="POST">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 text-sm text-white bg-red-600 rounded hover:bg-red-700">
                    Yes, Delete
                </button>
            </form>
        </div>
    </div>
</div>
@endcan

<script>
const staffBaseUrl = @json(url('/staff'));
const isProfessional = {{ $isProfessional ? 'true' : 'false' }};

function openDeleteModal(id, name) {
    document.getElementById('deleteStaffName').textContent = name ?? '';
    document.getElementById('deleteStaffForm').action = `${staffBaseUrl}/${id}`;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

// ✅ isProfessional passed from Blade to JS
function editStaff(staffId, isPro = false) {
    const professionalOptions = isPro ? `
        <optgroup label="Professional Roles ✦">
            <option value="hr">HR</option>
            <option value="finance">Finance</option>
        </optgroup>
    ` : '';

    const formContent = `
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Full Name</label>
            <input type="text" name="name_display" readonly
                class="bg-gray-100 border border-gray-300 text-gray-700 text-sm rounded-lg block w-full p-2.5 cursor-not-allowed dark:bg-gray-600 dark:border-gray-500 dark:text-gray-200"
                placeholder="Full name">
            <p class="mt-1 text-xs text-gray-500">Full name cannot be edited here.</p>
        </div>
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Role *</label>
            <select name="roles" required
                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-[#8B7355] focus:border-[#8B7355] block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                <optgroup label="Spa Staff">
                    <option value="therapist">Therapist</option>
                    <option value="receptionist">Receptionist</option>
                    <option value="manager">Manager</option>
                </optgroup>
                ${professionalOptions}
            </select>
        </div>
    `;

    document.getElementById('editFormContent').innerHTML = formContent;
    document.getElementById('editStaffForm').action = `${staffBaseUrl}/${staffId}`;
    document.getElementById('editModal').classList.remove('hidden');

    fetch(`${staffBaseUrl}/${staffId}`, {
        headers: { Accept: 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        const nameEl = document.querySelector('[name="name_display"]');
        const rolesEl = document.querySelector('[name="roles"]');
        if (nameEl) nameEl.value = data.name || '';
        if (rolesEl) rolesEl.value = data.roles || '';
    })
    .catch(err => console.error('Error fetching staff:', err));
}
</script>
@endsection
