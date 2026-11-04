@extends('layouts.app')

@section('title', 'Edit ' . $roleMeta['title'] . ' Role')
@section('content')
@php
    // Button tokens, same shape as appointments.blade.php's $btn map.
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'neutral' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
    ];

    $cardClass = 'bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700';
    $iconTile  = 'flex items-center justify-center flex-shrink-0 w-10 h-10 rounded-xl bg-[#8B7355]/10 text-[#8B7355] dark:bg-[#C4A97D]/10 dark:text-[#C4A97D]';
    $statLabel = 'text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400';
@endphp
<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">
    <x-page-header
        title="Edit Default Role — {{ $roleMeta['title'] }}"
        subtitle="Update the platform-wide permission template for this business role."
    />

    <div class="grid gap-4 md:grid-cols-4">
        <div class="flex items-start gap-3 p-4 sm:p-5 md:col-span-2 {{ $cardClass }}">
            <div class="{{ $iconTile }}"><i class="{{ $roleMeta['icon'] }}" aria-hidden="true"></i></div>
            <div>
                <p class="{{ $statLabel }}">Role Template</p>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ $roleMeta['title'] }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $roleMeta['description'] }}</p>
            </div>
        </div>

        <div class="p-4 sm:p-5 {{ $cardClass }}">
            <p class="{{ $statLabel }}">Selected</p>
            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                <span id="overall-selected">{{ $summary['selected'] }}</span><span class="text-sm font-normal text-gray-500 dark:text-gray-400"> of {{ $summary['available'] }}</span>
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Default permissions</p>
        </div>

        <div class="p-4 sm:p-5 {{ $cardClass }}">
            <p class="{{ $statLabel }}">Branches on default</p>
            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                {{ $defaultBranchesCount }}<span class="text-sm font-normal text-gray-500 dark:text-gray-400"> of {{ $totalBranches }}</span>
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $customizedBranchesCount }} customized by their owner</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.roles-permissions.update', $role) }}" id="permission-form"
          class="overflow-hidden {{ $cardClass }}">
        @csrf
        @method('PUT')

        <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Permissions</h2>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                Grouped by feature. Saving applies to every branch that still follows the default for this role.
            </p>
        </div>

        <div class="p-4 space-y-3 sm:p-6">
            @foreach ($sections as $section)
                <details class="overflow-hidden border border-gray-200 rounded-2xl group dark:border-gray-700">
                    <summary class="flex items-center justify-between gap-4 px-4 py-4 cursor-pointer select-none sm:px-5 hover:bg-gray-50 dark:hover:bg-gray-900">
                        <div class="flex items-start min-w-0 gap-3">
                            <div class="{{ $iconTile }}"><i class="{{ $section['icon'] }}" aria-hidden="true"></i></div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $section['title'] }}</h3>
                                    <span class="px-2.5 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-300">
                                        <span data-section-selected="{{ $section['key'] }}">{{ $section['selected_count'] }}</span>/{{ $section['total_count'] }} selected
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $section['description'] }}</p>
                            </div>
                        </div>
                        <i class="text-xs text-gray-400 transition-transform duration-200 fa-solid fa-chevron-down group-open:rotate-180" aria-hidden="true"></i>
                    </summary>

                    <div class="p-4 border-t border-gray-200 sm:p-5 dark:border-gray-700">
                        <div class="flex flex-wrap gap-2 mb-4">
                            <button type="button" data-set-section="{{ $section['key'] }}" data-checked="1" class="{{ $btn['neutral'] }}">Select all</button>
                            <button type="button" data-set-section="{{ $section['key'] }}" data-checked="0" class="{{ $btn['neutral'] }}">Clear all</button>
                        </div>

                        <div class="grid gap-3 lg:grid-cols-2">
                            @foreach ($section['permissions'] as $permission)
                                <label class="flex items-start gap-3 p-4 transition-colors border border-gray-200 cursor-pointer rounded-xl hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-900">
                                    <input type="checkbox" name="permissions[]" value="{{ $permission['name'] }}"
                                           data-section="{{ $section['key'] }}" @checked($permission['checked'])
                                           class="mt-1 border-gray-300 rounded text-[#8B7355] focus:ring-[#8B7355] dark:border-gray-600 dark:bg-gray-700">
                                    <span class="flex-1 min-w-0">
                                        <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $permission['label'] }}</span>
                                        <span class="block mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $permission['description'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </details>
            @endforeach
        </div>

        <div class="flex flex-col-reverse gap-2 px-4 py-4 border-t border-gray-200 bg-gray-50 sm:flex-row sm:justify-end sm:px-6 dark:bg-gray-900 dark:border-gray-700">
            <a href="{{ route('admin.roles-permissions.index') }}" class="w-full {{ $btn['neutral'] }} sm:w-auto">Cancel</a>
            <button type="submit" class="w-full {{ $btn['primary'] }} sm:w-auto">Save Changes</button>
        </div>
    </form>
</div>

<script>
(function () {
    'use strict';

    const form    = document.getElementById('permission-form');
    const boxes   = key => Array.from(form.querySelectorAll(key ? `input[data-section="${key}"]` : 'input[data-section]'));
    const checked = list => list.filter(box => box.checked).length;

    function updateCounts() {
        document.getElementById('overall-selected').textContent = checked(boxes());
        form.querySelectorAll('[data-section-selected]').forEach(counter => {
            counter.textContent = checked(boxes(counter.dataset.sectionSelected));
        });
    }

    form.addEventListener('change', updateCounts);

    // Select all / Clear all for one section.
    form.addEventListener('click', function (e) {
        const button = e.target.closest('[data-set-section]');
        if (!button) return;
        boxes(button.dataset.setSection).forEach(box => { box.checked = button.dataset.checked === '1'; });
        updateCounts();
    });
}());
</script>
@endsection
