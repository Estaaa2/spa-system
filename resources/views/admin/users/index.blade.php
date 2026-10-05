@extends('layouts.app')

@section('title', 'Registered Users')
@section('content')
@php
    // Button tokens, same shape as appointments.blade.php's $btn map.
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'edit'    => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        'neutral' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
        'remove'  => $btnBase . ' bg-red-700 text-white hover:bg-red-800',
    ];

    $inputClass = 'block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 '
                . 'dark:placeholder-gray-400 dark:text-white';

    $tabBase     = 'flex items-center justify-center flex-1 min-h-[44px] px-3 text-sm font-medium transition rounded-xl';
    $tabActive   = $tabBase . ' text-white shadow-sm bg-gradient-to-r from-[#7A6348] to-[#6F5430]';
    $tabInactive = $tabBase . ' text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700';

    $canEdit   = auth()->user()->can('edit registered users');
    $canDelete = auth()->user()->can('delete registered users');
    $showActions = $showDeleted ? $canDelete : ($canEdit || $canDelete);
@endphp
<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">
    <x-page-header
        title="Registered Users"
        subtitle="Review accounts, change roles, and remove or restore users."
    />

    <nav aria-label="User lists"
         class="flex gap-1 p-1.5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <a href="{{ route('admin.users.index') }}"
           @if (! $showDeleted) aria-current="page" @endif
           class="{{ $showDeleted ? $tabInactive : $tabActive }}">Active</a>
        <a href="{{ route('admin.users.index', ['view' => 'deleted']) }}"
           @if ($showDeleted) aria-current="page" @endif
           class="{{ $showDeleted ? $tabActive : $tabInactive }}">Removed</a>
    </nav>

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-col gap-3 px-4 py-4 border-b border-gray-200 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                {{ $showDeleted ? 'Removed Users' : 'Active Users' }}
            </h2>

            <form method="GET" class="flex gap-2">
                @if ($showDeleted)
                    <input type="hidden" name="view" value="deleted">
                @endif
                <input type="search" name="q" value="{{ $q }}" aria-label="Search name or email"
                       placeholder="Search name or email" class="{{ $inputClass }} sm:w-64">
                <button type="submit" class="{{ $btn['primary'] }}">Search</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Name</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Email</th>
                        <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Role</th>
                        @if ($showDeleted)
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Removed</th>
                        @endif
                        @if ($showActions)
                            <th class="px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    @forelse($users as $user)
                        @php
                            $currentRole = $user->roles->first()?->name;
                            $displayName = $user->name !== '' ? $user->name : $user->email;
                        @endphp
                        <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ $displayName }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-300">
                                    {{ $currentRole ? ucfirst($currentRole) : 'No role' }}
                                </span>
                            </td>
                            @if ($showDeleted)
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $user->deleted_at->format('M d, Y') }}
                                </td>
                            @endif
                            @if ($showActions)
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        @if ($showDeleted)
                                            <form method="POST" action="{{ route('admin.users.restore', $user->id) }}">
                                                @csrf
                                                <button type="submit" class="{{ $btn['edit'] }}">
                                                    <i class="text-xs fa-solid fa-rotate-left" aria-hidden="true"></i>
                                                    <span>Restore</span>
                                                </button>
                                            </form>
                                        @else
                                            @if ($canEdit)
                                                <button type="button" class="{{ $btn['edit'] }}"
                                                        onclick='openRoleModal({{ $user->id }}, @json($displayName), @json($currentRole))'>
                                                    <i class="text-xs fa-solid fa-pen" aria-hidden="true"></i>
                                                    <span>Change Role</span>
                                                </button>
                                            @endif
                                            @if ($canDelete)
                                                <button type="button" class="{{ $btn['remove'] }}"
                                                        onclick='openDeleteModal({{ $user->id }}, @json($displayName))'>
                                                    <i class="text-xs fa-solid fa-trash" aria-hidden="true"></i>
                                                    <span>Remove</span>
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <i class="mb-3 text-4xl text-gray-400 fa-solid fa-users" aria-hidden="true"></i>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $showDeleted ? 'No removed users.' : 'No users found.' }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="px-4 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>

@if ($canEdit && ! $showDeleted)
<div id="roleModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="roleModalTitle"
             class="w-full max-w-md bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <form id="roleForm" method="POST">
                @csrf
                @method('PUT')
                <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div>
                        <h2 id="roleModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Change User Role</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Updating the role for <span id="roleUserName" class="font-medium text-gray-900 dark:text-white"></span>.
                        </p>
                    </div>
                    <button type="button" onclick="closeModal('roleModal')" aria-label="Close dialog"
                            class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="px-4 py-6 sm:px-6">
                    <label for="roleSelect" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Role</label>
                    <select name="role" id="roleSelect" required class="{{ $inputClass }}">
                        {{-- Shown when the user's current role is not assignable here, so Save cannot silently pick the first option. --}}
                        <option value="" disabled>Select a role</option>
                        @foreach($roles as $role)
                            @continue(in_array($role->name, ['admin', 'customer'], true))
                            <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeModal('roleModal')" class="w-full {{ $btn['neutral'] }} sm:w-auto">Cancel</button>
                        <button type="submit" class="w-full {{ $btn['primary'] }} sm:w-auto">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if ($canDelete && ! $showDeleted)
<div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="alertdialog" aria-modal="true" aria-labelledby="deleteModalTitle" aria-describedby="deleteModalDesc"
             class="w-full max-w-md p-6 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <h2 id="deleteModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Remove User</h2>
            <p id="deleteModalDesc" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                This removes <span id="deleteUserName" class="font-medium text-gray-900 dark:text-white"></span>
                and blocks their sign-in. You can restore the account from the Removed tab.
            </p>
            <div class="flex flex-col-reverse gap-2 mt-6 sm:flex-row sm:justify-end">
                <button type="button" onclick="closeModal('deleteModal')" class="w-full {{ $btn['neutral'] }} sm:w-auto">Keep User</button>
                <form id="deleteForm" method="POST" class="sm:w-auto">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full {{ $btn['remove'] }} sm:w-auto">Yes, Remove</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

@if ($showActions && ! $showDeleted)
<script>
(function () {
    'use strict';

    const ROUTE_ROLE    = @json(route('admin.users.updateRole', '__ID__'));
    const ROUTE_DESTROY = @json(route('admin.users.destroy', '__ID__'));
    const MODAL_IDS = ['roleModal', 'deleteModal'];
    const FOCUSABLE = 'a[href], button:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

    let lastFocused = null;   // element to restore focus to on close

    function openModal(id, focusSelector) {
        const el = document.getElementById(id);
        lastFocused = document.activeElement;
        el.classList.remove('hidden');
        el.querySelector(focusSelector).focus();
    }

    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
        if (lastFocused && document.contains(lastFocused)) lastFocused.focus();
        lastFocused = null;
    }

    // Escape closes the open modal; Tab is kept inside it.
    document.addEventListener('keydown', function (e) {
        const modal = MODAL_IDS.map(id => document.getElementById(id))
            .find(el => el && !el.classList.contains('hidden'));
        if (!modal) return;

        if (e.key === 'Escape') {
            e.preventDefault();
            closeModal(modal.id);
            return;
        }
        if (e.key !== 'Tab') return;

        const items = Array.from(modal.querySelectorAll(FOCUSABLE));
        const first = items[0];
        const last  = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault(); last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault(); first.focus();
        }
    });

    // Backdrop click closes the delete confirmation only, so a stray tap
    // cannot discard a role the admin has just chosen.
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('click', e => { if (e.target === deleteModal) closeModal('deleteModal'); });
    }

    window.closeModal = closeModal;

    window.openRoleModal = function (id, name, role) {
        const select = document.getElementById('roleSelect');
        document.getElementById('roleUserName').textContent = name;
        document.getElementById('roleForm').action = ROUTE_ROLE.replace('__ID__', id);
        select.value = role ?? '';
        if (select.selectedIndex < 0) select.value = '';
        openModal('roleModal', 'select');
    };

    window.openDeleteModal = function (id, name) {
        document.getElementById('deleteUserName').textContent = name;
        document.getElementById('deleteForm').action = ROUTE_DESTROY.replace('__ID__', id);
        openModal('deleteModal', 'button[type="button"]');
    };
}());
</script>
@endif
@endsection
