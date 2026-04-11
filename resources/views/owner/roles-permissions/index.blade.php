@extends('layouts.app')

@section('content')
<div class="p-6 mx-auto space-y-6 max-w-7xl">
    <x-page-header
        title="Roles & Permissions"
        subtitle="Manage branch-specific access for your staff roles."
    />

    {{-- Branch Context Banner --}}
    <div class="flex flex-col gap-2 px-4 py-3 text-sm text-blue-700 border border-blue-200 rounded-xl bg-blue-50 dark:bg-blue-900/20 dark:border-blue-700 dark:text-blue-300 md:flex-row md:items-center">
        <div class="flex items-center gap-3">
            <i class="fa-solid fa-code-branch"></i>
            <span>
                You are editing permissions for branch:
                <strong>{{ $branch->name }}</strong>
                @if($branch->location)
                    — {{ $branch->location }}
                @endif
            </span>
        </div>

        <span class="text-xs text-blue-500 md:ml-auto dark:text-blue-400">
            Switch branches from the top navigation to edit another branch.
        </span>
    </div>

    {{-- Helper Note --}}
    <div class="grid gap-4 md:grid-cols-3">
        <div class="p-4 bg-white border rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Scope</p>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-200">
                Changes here affect only <strong>{{ $branch->name }}</strong>, not the universal default roles.
            </p>
        </div>

        <div class="p-4 bg-white border rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Workforce Suite</p>
            <p class="mt-2 text-sm {{ $workforceEnabled ? 'text-green-600 dark:text-green-400' : 'text-gray-600 dark:text-gray-300' }}">
                {{ $workforceEnabled ? 'Enabled' : 'Locked' }}
            </p>
        </div>

        <div class="p-4 bg-white border rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Finance Suite</p>
            <p class="mt-2 text-sm {{ $financeEnabled ? 'text-green-600 dark:text-green-400' : 'text-gray-600 dark:text-gray-300' }}">
                {{ $financeEnabled ? 'Enabled' : 'Locked' }}
            </p>
        </div>
    </div>

    {{-- Editable Roles --}}
    <div class="bg-white border shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                Branch Roles
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Choose a role below to customize what it can access inside this branch.
            </p>
        </div>

        <div class="grid gap-4 p-6 md:grid-cols-2 xl:grid-cols-3">
            @foreach($roles as $role)
                <div class="border rounded-2xl p-5 bg-gray-50/70 dark:bg-gray-900/20 dark:border-gray-700">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="inline-flex items-center gap-2 text-gray-800 dark:text-gray-100">
                                <i class="{{ $role->ui_icon }}"></i>
                                <h3 class="text-base font-semibold">{{ $role->ui_title }}</h3>
                            </div>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                {{ $role->ui_description }}
                            </p>
                        </div>

                        <span class="px-2.5 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded-full dark:bg-blue-900/30 dark:text-blue-300">
                            Active
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mt-5">
                        <div class="p-3 bg-white border rounded-xl dark:bg-gray-800 dark:border-gray-700">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Users in this branch</p>
                            <p class="mt-1 text-lg font-semibold text-gray-800 dark:text-gray-100">
                                {{ $role->branch_users_count }}
                            </p>
                        </div>

                        <div class="p-3 bg-white border rounded-xl dark:bg-gray-800 dark:border-gray-700">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Effective permissions</p>
                            <p class="mt-1 text-lg font-semibold text-gray-800 dark:text-gray-100">
                                {{ $role->effective_permission_count }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mt-4 text-xs text-gray-500 dark:text-gray-400">
                        <span>{{ $role->override_count }} branch override{{ $role->override_count === 1 ? '' : 's' }}</span>
                        <a href="{{ route('owner.roles-permissions.edit', $role) }}"
                           class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-lg bg-[#8B7355] hover:opacity-90">
                            <i class="fa-solid fa-pen-to-square"></i>
                            Edit
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Locked Roles --}}
    @if(count($lockedRoles))
        <div class="bg-white border shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="px-6 py-4 border-b dark:border-gray-700">
                <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                    Locked Roles
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    These roles are unavailable until their matching suite is enabled for this spa.
                </p>
            </div>

            <div class="grid gap-4 p-6 md:grid-cols-2">
                @foreach($lockedRoles as $lockedRole)
                    <div class="border border-dashed rounded-2xl p-5 bg-gray-50 dark:bg-gray-900/20 dark:border-gray-700">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="inline-flex items-center gap-2 text-gray-500 dark:text-gray-300">
                                    <i class="fa-solid fa-lock"></i>
                                    <h3 class="text-base font-semibold">{{ $lockedRole['title'] }}</h3>
                                </div>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $lockedRole['reason'] }}
                                </p>
                            </div>

                            <span class="px-2.5 py-1 text-xs font-medium text-gray-500 bg-gray-200 rounded-full dark:bg-gray-700 dark:text-gray-300">
                                Locked
                            </span>
                        </div>

                        <div class="mt-5">
                            <a href="{{ route('owner.subscription.index') }}"
                               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-lg bg-[#8B7355] hover:opacity-90">
                                <i class="fa-solid fa-crown text-yellow-300"></i>
                                Upgrade
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection