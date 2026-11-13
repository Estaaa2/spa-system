{{--
    DSS revamp (D5): "Needs your decision" card + once-a-day pop-up on the Dashboard.
    Same gate as the Decision Support page (F7): `view decision support` + plan feature
    `insights`, never a hardcoded role. Spa-level items (R6) only for the owner.
    The pop-up shows at most once per user per day, only when a high-priority item is
    open; it is marked as shown when rendered (server-side, cache), so "Remind me later"
    just closes it until tomorrow.
--}}
@php
    $dssUser = auth()->user();
    $dssBranchId = $dssUser?->currentBranchId();
    $dssOn = $dssUser && $dssBranchId
        && $dssUser->spa?->hasFeature('insights')
        && $dssUser->hasBranchPermission('view decision support');

    $dssItems = collect();
    $dssTotal = 0;
    $dssPopup = null;
    if ($dssOn) {
        $dssBase = fn () => \App\Models\DssRecommendation::query()->visibleTo($dssUser, $dssBranchId)->open();
        $dssTotal = $dssBase()->count();
        $dssItems = $dssBase()->byPriority()->limit(3)->get();

        $dssPopupKey = 'dss-popup-shown:'.$dssUser->id.':'.now()->toDateString();
        $dssHigh = $dssItems->firstWhere('priority', 'high');
        if ($dssHigh && ! \Illuminate\Support\Facades\Cache::has($dssPopupKey)) {
            $dssPopup = $dssHigh;
            \Illuminate\Support\Facades\Cache::put($dssPopupKey, true, now()->endOfDay());
        }
    }
    $dssPrio = [
        'high'   => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
        'medium' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        'low'    => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    ];
@endphp

@if ($dssOn && $dssTotal > 0)
<div x-data="{ dssPopupOpen: {{ $dssPopup ? 'true' : 'false' }} }">
    <section class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700" aria-labelledby="dssCardTitle">
        <div class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <div class="flex items-start gap-3">
                <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-[#8B7355]/10 dark:bg-[#C4A97D]/10">
                    <i class="text-sm fa-solid fa-lightbulb text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                </div>
                <div>
                    <h2 id="dssCardTitle" class="text-base font-semibold text-gray-900 dark:text-white">Needs your decision</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $dssTotal }} {{ \Illuminate\Support\Str::plural('recommendation', $dssTotal) }} from Decision Support</p>
                </div>
            </div>
            <a href="{{ route('decision-support.index') }}"
               class="inline-flex items-center gap-1 text-sm font-medium rounded text-[#8B7355] dark:text-[#C4A97D] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355]">
                Review all <span aria-hidden="true">→</span>
            </a>
        </div>
        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach ($dssItems as $item)
                <li>
                    <a href="{{ route('decision-support.index') }}" class="flex items-start gap-3 px-4 py-3 sm:px-6 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                        <span class="mt-0.5 px-2 py-0.5 text-[11px] font-semibold rounded-full shrink-0 {{ $dssPrio[$item->priority] ?? $dssPrio['medium'] }}">{{ ucfirst($item->priority) }}</span>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $item->title }}</span>
                            <span class="block text-xs text-gray-500 truncate dark:text-gray-400">{{ $item->message }}</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    @if ($dssPopup)
        <x-app-modal show="dssPopupOpen" max-width="md" labelledby="dssPopupTitle" describedby="dssPopupBody">
            <div class="px-5 py-5 sm:px-6">
                <div class="flex items-start gap-3">
                    <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/30">
                        <i class="fa-solid fa-lightbulb text-red-600 dark:text-red-400" aria-hidden="true"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold tracking-wide text-red-600 uppercase dark:text-red-400">Decision Support · high priority</p>
                        <h2 id="dssPopupTitle" class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $dssPopup->title }}</h2>
                        <p id="dssPopupBody" class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $dssPopup->message }}</p>
                        @if ($dssTotal > 1)
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $dssTotal - 1 }} more {{ \Illuminate\Support\Str::plural('recommendation', $dssTotal - 1) }} waiting.</p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex flex-col-reverse gap-2 px-5 py-4 border-t border-gray-200 sm:flex-row sm:justify-end sm:px-6 dark:border-gray-700">
                <button type="button" @click="dssPopupOpen = false"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700">
                    Remind me later
                </button>
                <a href="{{ route('decision-support.index') }}"
                   class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-xl bg-gradient-to-r from-[#8B7355] to-[#6F5430] shadow-sm hover:opacity-90">
                    Review
                </a>
            </div>
        </x-app-modal>
    @endif
</div>
@endif
