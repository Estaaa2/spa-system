@props([
    'show',
    'maxWidth' => 'lg',
    'role' => 'dialog',
    'labelledby' => null,
    'describedby' => null,
])

@php
    $maxWidthClass = match ($maxWidth) {
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        default => 'max-w-lg',
    };
@endphp

<template x-teleport="body">
    <div
        x-show="{{ $show }}"
        x-cloak
        x-transition.opacity.duration.150ms
        class="fixed inset-0 z-[100] overflow-y-auto overscroll-contain bg-black/50"
        role="{{ $role }}"
        aria-modal="true"
        @if($labelledby)
            aria-labelledby="{{ $labelledby }}"
        @endif
        @if($describedby)
            aria-describedby="{{ $describedby }}"
        @endif
        @keydown.escape.window="{{ $show }} = false"
    >
        <div class="flex items-start justify-center min-h-full p-4 sm:items-center sm:p-6">
            <div
                x-show="{{ $show }}"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="w-full {{ $maxWidthClass }} overflow-hidden bg-white border border-gray-200 shadow-xl rounded-2xl dark:bg-gray-800 dark:border-gray-700"
            >
                {{ $slot }}
            </div>
        </div>
    </div>
</template>
