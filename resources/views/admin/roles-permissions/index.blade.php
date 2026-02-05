@extends('layouts.app')

@section('content')
<div class="p-6">
    <x-page-header
    title="Roles & Permissions"
    subtitle="Edit what each role can access."
/>

    @if(session('success'))
        <div class="p-3 mb-4 text-sm text-green-800 bg-green-100 border border-green-200 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <!-- CARD -->
    <div class="bg-white border shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
        <!-- Card Header -->
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                System Roles
            </h2>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/30">
                    <tr class="text-left text-gray-600 dark:text-gray-300">
                        <th class="px-6 py-3">Role</th>
                        <th class="px-6 py-3">Users</th>
                        <th class="px-6 py-3">Permissions</th>
                        <th class="px-6 py-3">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($roles as $role)
                        <tr class="border-t dark:border-gray-700">
                            <td class="px-6 py-3 font-medium text-gray-800 dark:text-gray-100">
                                {{ $role->name }}
                            </td>

                            <td class="px-6 py-3 text-gray-600 dark:text-gray-300">
                                {{ $role->users_count }}
                            </td>

                            <td class="px-6 py-3">
                                <span class="px-2 py-1 text-xs bg-gray-100 rounded dark:bg-gray-700 dark:text-gray-200">
                                    {{ $role->permissions->count() }} assigned
                                </span>
                            </td>

                            <td class="px-4 py-2 text-left">
                                <a href="{{ route('roles-permissions.edit', $role) }}"
                                   class="px-3 py-1 text-sm text-white bg-yellow-500 rounded hover:bg-yellow-600">
                                    Edit
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                No roles found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Card Footer -->
        <div class="px-6 py-4 text-xs text-gray-500 border-t dark:border-gray-700 dark:text-gray-400">
            Click <strong>Edit</strong> to manage permissions for a role.
        </div>
    </div>
</div>
@endsection
