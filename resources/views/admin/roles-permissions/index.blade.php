@extends('layouts.app')

@section('title', 'Roles & Permissions')
@section('content')
@php
    $canEdit = auth()->user()->can('edit system roles');
@endphp
<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">
    <x-page-header
        title="Default Roles & Permissions"
        subtitle="Platform-wide role templates for business roles."
    />

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Role Templates</h2>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                A branch follows these defaults until its owner customizes that role. Admin-only permissions are not part of these templates.
            </p>
        </div>

        <div class="grid gap-4 p-4 sm:p-6 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($roles as $role)
                <div class="flex flex-col p-5 border border-gray-200 rounded-2xl dark:border-gray-700">
                    <div class="flex items-start gap-3">
                        <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 rounded-xl bg-[#8B7355]/10 text-[#8B7355] dark:bg-[#C4A97D]/10 dark:text-[#C4A97D]">
                            <i class="{{ $role->ui_icon }}" aria-hidden="true"></i>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $role->ui_title }}</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $role->ui_description }}</p>
                        </div>
                    </div>

                    <dl class="grid grid-cols-3 gap-3 pt-4 mt-4 border-t border-gray-200 dark:border-gray-700">
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Users</dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $role->users_count }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Permissions</dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $role->default_permission_count }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Customized</dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                                {{ $role->customized_branches_count }}<span class="text-xs font-normal text-gray-500 dark:text-gray-400"> of {{ $totalBranches }} {{ Str::plural('branch', $totalBranches) }}</span>
                            </dd>
                        </div>
                    </dl>

                    @if ($canEdit)
                        <a href="{{ route('admin.roles-permissions.edit', $role) }}"
                           class="inline-flex items-center justify-center gap-1.5 min-h-[44px] px-4 py-2 mt-4 text-sm font-medium text-gray-700 transition-colors bg-white border border-gray-300 rounded-xl hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 dark:focus-visible:ring-offset-gray-800">
                            <i class="text-xs fa-solid fa-pen" aria-hidden="true"></i>
                            <span>Edit Permissions</span>
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
