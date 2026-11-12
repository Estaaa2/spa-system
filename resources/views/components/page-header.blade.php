@props([
    'title' => '',
    'subtitle' => null,
    'showClock' => true,
])

@php
    // Trim to avoid false positives from whitespace-only content, 
    // which can happen when a slot is used but nothing is passed into it.
    $hasRight = trim((string) ($right ?? '')) !== '';

    // Help button (help guides Phase 1, D2): the current page's guide, found from the
    // sidebar's active link. Null when the page has no guide, or for admins and customers.
    $helpUrl = auth()->check() ? \App\Support\Guides\GuideCatalog::currentUrl(auth()->user()) : null;
@endphp

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

    {{-- min-w-0 lets the title block shrink instead of pushing the row wider than the viewport --}}
    <div class="min-w-0">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
            {{ $title }}
        </h1>

        @if($subtitle)
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $subtitle }}
            </p>
        @endif
    </div>

    @if($hasRight || $showClock || $helpUrl)
        {{-- Shown on phones when there is a button to show (Q3 → A: Help counts). --}}
        <div class="flex-wrap items-center gap-3 shrink-0 {{ ($hasRight || $helpUrl) ? 'flex' : 'hidden md:flex' }}">
            {{ $right ?? '' }}

            @if($helpUrl)
                {{-- Q4 → A: icon + "Help", after the page's own buttons. Q5 → B: new tab, so unsaved input stays. --}}
                <a href="{{ $helpUrl }}" target="_blank" rel="noopener"
                   title="Open this page's guide in a new tab"
                   class="print:hidden inline-flex items-center justify-center gap-2 min-h-[44px] px-4 py-2 text-sm font-medium text-gray-700 transition-colors bg-white border border-gray-300 rounded-xl hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <i class="fa-regular fa-circle-question text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                    Help<span class="sr-only"> (opens in a new tab)</span>
                </a>
            @endif

            @if($showClock)
                <div
                    x-data="{
                        now: new Date(),
                        timeout: null,
                        interval: null,
                        init() {
                            // Only hh:mm is displayed, so a 1s tick was doing 60x more work
                            // than needed. Align the first tick to the next minute boundary,
                            // then tick once a minute so the display never lags.
                            const msToNextMinute = 60000 - (Date.now() % 60000);

                            this.timeout = setTimeout(() => {
                                this.now = new Date();
                                this.interval = setInterval(() => this.now = new Date(), 60000);
                            }, msToNextMinute);
                        },
                        destroy() {
                            clearTimeout(this.timeout);
                            clearInterval(this.interval);
                        }
                    }"
                    class="hidden md:flex items-center gap-3 px-4 py-2 bg-white border border-gray-200 rounded-2xl shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-500 dark:text-gray-400">Today</span>

                        <span class="text-sm font-medium text-gray-800 dark:text-white xl:hidden"
                              x-text="now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })">
                        </span>
                        <span class="hidden xl:inline text-sm font-medium text-gray-800 dark:text-white"
                              x-text="now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })">
                        </span>
                    </div>

                    <div class="h-6 border-l border-gray-200 dark:border-gray-700"></div>

                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-500 dark:text-gray-400">Time</span>
                        <span class="text-sm font-medium text-gray-800 dark:text-white"
                              x-text="now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })">
                        </span>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>