@extends('layouts.app')

@section('content')
<div class="p-6">
    <x-page-header
        title="Roles & Permissions"
        subtitle="Manage what your staff roles can access."
    />

    {{-- Branch Context Banner --}}
    <div class="flex items-center gap-3 px-4 py-3 mb-5 text-sm text-blue-700 border border-blue-200 rounded-lg bg-blue-50 dark:bg-blue-900/20 dark:border-blue-700 dark:text-blue-300">
        <i class="fa-solid fa-code-branch"></i>
        <span>
            You are editing permissions for branch:
            <strong>{{ $branch->name }}</strong> — {{ $branch->location }}
        </span>
        <span class="ml-auto text-xs text-blue-500 dark:text-blue-400">Switch branches from the top navigation to edit another branch.</span>
    </div>

    <!-- CARD -->
    <div class="bg-white border shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
        <!-- Card Header -->
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                Staff Roles
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
                            <td class="px-6 py-3 font-medium text-gray-800 capitalize dark:text-gray-100">
                                {{ $role->name }}
                            </td>

                            <td class="px-6 py-3 text-gray-600 dark:text-gray-300">
                                {{ $role->users_count }}
                            </td>

                            <td class="px-6 py-3">
                                <span class="px-2 py-1 text-xs bg-gray-100 rounded dark:bg-gray-700 dark:text-gray-200">
                                    {{ $role->effective_permission_count }} assigned
                                </span>
                            </td>

                            <td class="px-4 py-2 text-left">
                                <a href="{{ route('owner.roles-permissions.edit', $role) }}"
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

                    {{-- HR & Finance locked rows for non-Professional owners --}}
                    @if(!$spa->isProfessional())
                        @foreach(['hr', 'finance'] as $lockedRole)
                            <tr class="border-t bg-gray-50/50 dark:border-gray-700 dark:bg-gray-900/20">
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <i class="text-xs text-gray-400 fa-solid fa-lock"></i>
                                        <span class="font-medium text-gray-400 capitalize">{{ $lockedRole }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-gray-400">—</td>
                                <td class="px-6 py-3">
                                    <span class="px-2 py-1 text-xs text-gray-400 bg-gray-100 rounded dark:bg-gray-700">
                                        Locked
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('owner.subscription.index') }}"
                                       class="inline-flex items-center gap-1 px-3 py-1 text-xs font-medium text-white bg-[#8B7355] rounded hover:opacity-90">
                                        <i class="fa-solid fa-crown text-yellow-300 text-[10px]"></i>
                                        Upgrade
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>

        <!-- Card Footer -->
        <div class="px-6 py-4 text-xs text-gray-500 border-t dark:border-gray-700 dark:text-gray-400">
            @if(!$spa->isProfessional())
                <span class="inline-flex items-center gap-1 text-[#8B7355]">
                    <i class="text-yellow-500 fa-solid fa-crown"></i>
                    <strong>HR</strong> and <strong>Finance</strong> roles are available on the
                    <a href="{{ route('owner.subscription.index') }}" class="underline hover:text-[#6F5430]">Professional Plan</a>.
                </span>
            @else
                Click <strong>Edit</strong> to manage permissions for a role.
            @endif
        </div>
    </div>
</div>
@endsection
