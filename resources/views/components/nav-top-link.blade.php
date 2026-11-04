{{--
    A top-level sidebar link (Dashboard, pinned links, and groups with a single
    link — decision D2). Uses the section-header box, not x-nav-link (styled for
    nested sub-links), so icons and labels line up with the group headers.
    $link is one entry from App\Services\Navigation\Sidebar.
--}}
@props(['link'])

@php
    $active = (bool) ($link['active'] ?? false);
    $badgeCount = (int) ($link['badge'] ?? 0);
    $classes = 'flex items-center w-full min-h-[44px] gap-2 px-4 py-3 font-medium text-left transition-colors rounded-lg '
        . ($active
            ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white'
            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700');
@endphp

<div class="mb-1">
    <a href="{{ $link['url'] }}" @if ($active) aria-current="page" @endif class="{{ $classes }}">
        <i class="fa-solid {{ $link['icon'] }} w-4 shrink-0 text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
        <span class="min-w-0">{{ $link['label'] }}</span>

        <span class="flex items-center gap-1.5 ml-auto pl-2 shrink-0">
            @if (!empty($link['chip']))
                <span class="px-1.5 py-0.5 text-[11px] font-medium leading-none rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $link['chip'] }}</span>
            @endif
            <x-nav-badge :key="$link['key']" :count="$badgeCount" :label="$link['badgeLabel'] ?? null" :urgent="$link['badgeUrgent'] ?? false" />
        </span>
    </a>
</div>
