@props([
    'active' => false,
    // Sidebar & RBAC rework Phase 2: optional extras, used by the business sidebar.
    'chip' => null,         // short scope note, e.g. "All branches" on a spa-wide page
    'badge' => null,        // Phase 4: count of items needing action (null or 0 = none)
    'badgeLabel' => null,   // Phase 4: full sentence for screen readers, e.g. "3 need action"
])

@php
    $base = 'group relative flex items-center w-full min-h-[44px] px-4 py-2.5 rounded-lg transition-colors duration-200';

    $state = $active
        ? 'font-medium text-[#6F5430] bg-[#8B7355]/[0.07] dark:text-[#C4A97D] dark:bg-[#C4A97D]/10'
        : 'text-gray-600 hover:text-[#6F5430] hover:bg-gray-50 dark:text-gray-300 dark:hover:text-[#C4A97D] dark:hover:bg-gray-700/40';

    $underline = $active
        ? 'bg-[#8B7355] dark:bg-[#C4A97D] scale-x-100'
        : 'bg-gray-300 dark:bg-gray-600 scale-x-0 group-hover:scale-x-100';

    $badgeCount = (int) ($badge ?? 0);
@endphp

<a {{ $attributes->merge(['class' => $base . ' ' . $state]) }}
   @if ($active) aria-current="page" @endif>

    {{-- inline-block so the underline hugs the label, not the whole row --}}
    <span class="relative inline-block pb-1 min-w-0">

        {{ $slot }}

        {{-- underline --}}
        <span aria-hidden="true"
              class="absolute left-0 -bottom-1 h-[2px] w-full
                     origin-left transition-transform duration-300 ease-out motion-reduce:transition-none
                     {{ $underline }}"></span>

    </span>

    @if ($chip || $badgeCount > 0)
        <span class="flex items-center gap-1.5 ml-auto pl-2 shrink-0">
            @if ($chip)
                <span class="px-1.5 py-0.5 text-[11px] font-medium leading-none rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $chip }}</span>
            @endif
            @if ($badgeCount > 0)
                {{-- Same amber pill as the admin sidebar's pending counts. --}}
                <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-semibold leading-none rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                    <span aria-hidden="true">{{ $badgeCount > 99 ? '99+' : $badgeCount }}</span>
                    <span class="sr-only">{{ $badgeLabel ?? ($badgeCount . ' need action') }}</span>
                </span>
            @endif
        </span>
    @endif
</a>
