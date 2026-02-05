@extends('layouts.app')

@section('content')
<div class="p-6">
    <x-page-header
        title="Users"
        subtitle="Manage user roles"
    >
    </x-page-header>

    <!-- CARD -->
    <div class="bg-white border shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
        <!-- Card Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                Registered Users
            </h2>

            <form method="GET" class="flex gap-2">
                <input
                    name="q"
                    value="{{ $q }}"
                    class="w-64 px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white"
                    placeholder="Search name or email"
                >
                <button
                    class="px-4 py-2 text-sm font-medium text-white bg-[#8B7355] rounded-lg hover:opacity-90">
                    Search
                </button>
            </form>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/30">
                    <tr class="text-left text-gray-600 dark:text-gray-300">
                        <th class="px-6 py-3">Name</th>
                        <th class="px-6 py-3">Email</th>
                        <th class="px-6 py-3">Current Role</th>
                        <th class="px-6 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        @php
                            $currentRole = $user->roles->first()?->name ?? 'none';
                        @endphp
                        <tr class="border-t dark:border-gray-700">
                            <td class="px-6 py-3 text-gray-800 dark:text-gray-100">
                                {{ $user->name }}
                            </td>
                            <td class="px-6 py-3 text-gray-600 dark:text-gray-300">
                                {{ $user->email }}
                            </td>
                            <td class="px-6 py-3">
                                <span class="px-2 py-1 text-xs bg-gray-100 rounded dark:bg-gray-700 dark:text-gray-200">
                                    {{ $currentRole }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-center">
                                <button
                                    onclick="openEditRoleModal(
                                        {{ $user->id }},
                                        '{{ $user->name }}',
                                        '{{ $currentRole }}'
                                    )"
                                    class="px-3 py-1 text-sm text-white bg-yellow-500 rounded hover:bg-yellow-600">
                                    Edit
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                No users found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Card Footer -->
        <div class="px-6 py-4 border-t dark:border-gray-700">
            {{ $users->links() }}
        </div>
    </div>
</div>

<!-- EDIT ROLE MODAL -->
<div id="editRoleModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50">
    <div class="w-full max-w-md p-6 mx-auto mt-24 bg-white rounded-lg dark:bg-gray-800">
        <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">
            Edit User Role
        </h2>

        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
            Update role for <span id="modalUserName" class="font-medium"></span>
        </p>

        <form id="editRoleForm" method="POST">
            @csrf
            @method('PUT')

            <select name="role"
                    id="modalRoleSelect"
                    class="w-full px-3 py-2 mb-4 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white">
                @foreach($roles as $role)
                    @continue($role->name === 'admin')

                    <option value="{{ $role->name }}">
                        {{ $role->name }}
                    </option>
                @endforeach
            </select>


            <div class="flex justify-end gap-2">
                <button type="button"
                        onclick="closeEditRoleModal()"
                        class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded-lg dark:bg-gray-700 dark:text-gray-300">
                    Cancel
                </button>
                <button type="submit"
                        class="px-4 py-2 text-sm font-medium text-white bg-[#8B7355] border border-transparent rounded-md shadow-sm hover:bg-[#7A6348] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#8B7355]">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL SCRIPT -->
<script>
function openEditRoleModal(userId, userName, currentRole) {
    document.getElementById('modalUserName').textContent = userName;
    document.getElementById('modalRoleSelect').value = currentRole;
    document.getElementById('editRoleForm').action = `/users/${userId}/role`;
    document.getElementById('editRoleModal').classList.remove('hidden');
}

function closeEditRoleModal() {
    document.getElementById('editRoleModal').classList.add('hidden');
}
</script>

{{-- SUCCESS TOAST --}}
@if (session('success'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    Toastify({
        text: `
            <div class="flex items-center gap-3">
                <i class="text-green-600 fa-solid fa-check-circle"></i>
                <span class="text-gray-800">{{ session('success') }}</span>
            </div>
        `,
        duration: 3000,
        gravity: "top",
        position: "right",
        close: true,
        escapeMarkup: false,
        backgroundColor: "#ffffff",
        style: {
            border: "1px solid #16a34a",
            borderRadius: "10px",
            minWidth: "300px",
            display: "flex",
            alignItems: "center",
            boxShadow: "0 8px 20px rgba(0,0,0,0.08)"
        }
    }).showToast();
});
</script>
@endif

{{-- ERROR TOAST --}}
@if ($errors->any())
<script>
document.addEventListener('DOMContentLoaded', function () {
    Toastify({
        text: `
            <div class="flex items-center gap-3">
                <i class="text-red-600 fa-solid fa-circle-xmark"></i>
                <span class="text-gray-800">{{ $errors->first() }}</span>
            </div>
        `,
        duration: 4000,
        gravity: "top",
        position: "right",
        close: true,
        escapeMarkup: false,
        backgroundColor: "#ffffff",
        style: {
            border: "1px solid #dc2626",
            borderRadius: "10px",
            minWidth: "300px",
            display: "flex",
            alignItems: "center",
            boxShadow: "0 8px 20px rgba(0,0,0,0.08)"
        }
    }).showToast();
});
</script>
@endif

@endsection
