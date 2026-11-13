{{--
    DSS revamp (D5): "Needs your decision" card + once-a-day pop-up on the Dashboard.
    Same gate as the Decision Support page (F7): `view decision support` + plan feature
    `insights`, never a hardcoded role. Spa-level items (R6) only for the owner.
    The pop-up lists the top 3 open items by priority (titles only; the numbers live on the
    page). It shows at most once per user per day, only when a high-priority item is open;
    it is marked as shown when rendered (server-side, cache), so "Not now" just closes it
    until tomorrow.
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
        @php $dssBranchName = \App\Models\Branch::withoutGlobalScopes()->whereKey($dssBranchId)->value('name'); @endphp
        {{-- Same contract as x-app-modal (teleport, dialog role, aria-modal, Escape), but a
             bottom sheet below 640 px and a centred dialog from sm up. --}}
        <template x-teleport="body">
            <div x-show="dssPopupOpen" x-cloak
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 class="fixed inset-0 z-[100] overflow-y-auto overscroll-contain bg-black/50"
                 role="dialog" aria-modal="true" aria-labelledby="dssPopupTitle" aria-describedby="dssPopupBody"
                 @keydown.escape.window="dssPopupOpen = false">
                <div class="flex items-end justify-center min-h-full sm:items-center sm:p-6" @click.self="dssPopupOpen = false">
                    <div x-show="dssPopupOpen"
                         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full sm:translate-y-0 sm:opacity-0 sm:scale-95" x-transition:enter-end="translate-y-0 sm:opacity-100 sm:scale-100"
                         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0 sm:opacity-100 sm:scale-100" x-transition:leave-end="translate-y-full sm:translate-y-0 sm:opacity-0 sm:scale-95"
                         class="w-full sm:max-w-lg overflow-hidden bg-white border border-gray-200 shadow-xl rounded-t-2xl sm:rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                        <div class="flex justify-center pt-2 sm:hidden" aria-hidden="true"><span class="w-10 h-1 bg-gray-300 rounded-full dark:bg-gray-600"></span></div>
                        <div class="px-4 pt-4 pb-3 sm:px-6 sm:pt-5 sm:pb-4">
                            <div class="flex items-start gap-3">
                                <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 rounded-xl bg-[#8B7355]/10 dark:bg-[#C4A97D]/10">
                                    <i class="fa-solid fa-lightbulb text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                        Decision Support{{ $dssBranchName ? ' · '.$dssBranchName : '' }}
                                    </p>
                                    <h2 id="dssPopupTitle" class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ $dssTotal }} {{ $dssTotal === 1 ? 'decision is' : 'decisions are' }} waiting for you
                                    </h2>
                                    <p id="dssPopupBody" class="hidden sm:block mt-0.5 text-sm text-gray-500 dark:text-gray-400">Found from your schedules and bookings.</p>
                                </div>
                            </div>

                            <ul class="mt-4 space-y-2">
                                @foreach ($dssItems as $item)
                                    <li class="flex items-start gap-3 px-3 py-2.5 border border-gray-200 rounded-xl dark:border-gray-700">
                                        <span class="mt-0.5 px-2 py-0.5 text-[11px] font-semibold rounded-full shrink-0 {{ $dssPrio[$item->priority] ?? $dssPrio['medium'] }}">{{ ucfirst($item->priority) }}</span>
                                        <span class="min-w-0 text-sm font-medium text-gray-900 dark:text-white">{{ $item->title }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            @if ($dssTotal > $dssItems->count())
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">and {{ $dssTotal - $dssItems->count() }} more</p>
                            @endif
                        </div>
                        <div class="flex flex-col-reverse gap-2 px-4 pt-1 pb-[max(1rem,env(safe-area-inset-bottom))] sm:pt-4 sm:pb-4 sm:border-t sm:border-gray-200 sm:flex-row sm:justify-end sm:px-6 dark:sm:border-gray-700">
                            <button type="button" @click="dssPopupOpen = false"
                                    class="inline-flex items-center justify-center px-4 py-2.5 sm:py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355]">
                                Not now
                            </button>
                            <a href="{{ route('decision-support.index') }}"
                               class="inline-flex items-center justify-center gap-2 px-4 py-2.5 sm:py-2 text-sm font-semibold text-white rounded-xl bg-gradient-to-r from-[#8B7355] to-[#6F5430] shadow-sm hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B7355] dark:focus-visible:ring-offset-gray-800">
                                Open Decision Support
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    @endif
</div>
@endif
