@extends('layouts.app')

@section('content')
<div class="p-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">Roles & Permissions</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Edit what each role can access.
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="p-3 mb-4 text-sm text-green-800 bg-green-100 border border-green-200 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="overflow-hidden bg-white border rounded-lg dark:bg-gray-800 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/30">
                <tr class="text-left text-gray-600 dark:text-gray-300">
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Users</th>
                    <th class="px-4 py-3">Permissions</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>

            <tbody>
                @forelse($roles as $role)
                    <tr class="border-t dark:border-gray-700">
                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-100">
                            {{ $role->name }}
                        </td>

                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                            {{ $role->users_count }}
                        </td>

                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                            <span class="px-2 py-1 text-xs bg-gray-100 rounded dark:bg-gray-700 dark:text-gray-200">
                                {{ $role->permissions->count() }} assigned
                            </span>
                        </td>

                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('roles-permissions.edit', $role) }}"
                               class="inline-flex items-center px-3 py-2 text-xs font-medium text-white bg-[#8B7355] rounded-lg hover:opacity-90">
                                Edit
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                            No roles found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
