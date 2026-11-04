{{-- "Extend trial" button for one spa. Expects $spa and $extended (days already added). --}}
@php
    $extendLeft = max(0, (int) config('subscription.trial_extension_max_total_days', 60) - (int) $extended);
    $extendBlocked = $spa->hasEverPaid()
        ? 'This spa already has paid time.'
        : ($extendLeft === 0 ? 'The extension limit for this spa is used up.' : null);
@endphp

<button type="button"
        onclick="openExtendTrial(this)"
        data-spa-id="{{ $spa->id }}"
        data-spa-name="{{ $spa->name }}"
        data-trial-ends="{{ $spa->trial_ends_at?->format('M d, Y') ?? '—' }}"
        data-left="{{ $extendLeft }}"
        @if ($extendBlocked) disabled title="{{ $extendBlocked }}" @endif
        class="inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-50 whitespace-nowrap">
    <i class="text-xs fa-solid fa-calendar-plus" aria-hidden="true"></i>
    Extend trial
</button>

@if ($extendBlocked)
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $extendBlocked }}</p>
@endif
