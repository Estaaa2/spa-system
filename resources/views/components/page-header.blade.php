@props([
    'title' => '',
    'subtitle' => null,
    'showClock' => true,
])

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">
            {{ $title }}
        </h1>

        @if($subtitle)
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $subtitle }}
            </p>
        @endif
    </div>

    <div class="flex items-center gap-3">
        {{-- Optional slot if you want buttons/search on the right later --}}
        {{ $right ?? '' }}

        @if($showClock)
            <div
                x-data="{
                    now: new Date(),
                    init() {
                        this.now = new Date();
                        setInterval(() => this.now = new Date(), 1000);
                    }
                }"
                class="flex items-center gap-3 px-4 py-2 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500 dark:text-gray-400">Today</span>
                    <span class="text-sm font-medium text-gray-800 dark:text-white"
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
</div>
