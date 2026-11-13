@extends('layouts.app')

{{--
    Help & Guides (help guides Phase 1).

    Content: App\Support\Guides\Guides. Which guides and in what order:
    App\Support\Guides\GuideCatalog::sections() — only pages this user's sidebar
    shows (D1), laid out like the sidebar (Q2 → A).

    Expand/collapse: <details>/<summary>, like the public FAQ (keyboard and
    screen-reader support built in). Each guide's id is its sidebar key, so
    /help#appointments opens and scrolls to the Appointments guide; the page
    header's Help button links there.

    Search runs in the browser (Alpine) over each guide's title, summary,
    tasks, steps and tips (data-search, already lower-cased on the server).
--}}

@section('title', 'Help & Guides')

@section('content')
@php
    $card = 'overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700';
    $sectionLabel = 'px-1 mb-2 text-[11px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400';
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl"
     x-data="{
         q: '',
         words() { return this.q.trim().toLowerCase().split(/\s+/).filter(Boolean); },
         match(el) { const w = this.words(); return w.length === 0 || w.every(x => el.dataset.search.includes(x)); },
         anyIn(el) { return [...el.querySelectorAll('[data-search]')].some(d => this.match(d)); },
     }">

    <x-page-header title="Help & Guides" subtitle="Step-by-step guides for the pages you can open." />

    {{-- Search --}}
    <div class="{{ $card }} p-4 sm:p-5">
        <label for="helpSearch" class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">Search the guides</label>
        <div class="relative">
            <i class="absolute text-sm text-gray-500 -translate-y-1/2 pointer-events-none fa-solid fa-magnifying-glass left-3 top-1/2 dark:text-gray-400" aria-hidden="true"></i>
            <input id="helpSearch" type="search" x-model.debounce.150ms="q" autocomplete="off"
                   placeholder='For example "clock in" or "check out"'
                   class="w-full min-h-[44px] py-2 pl-9 pr-3 text-sm text-gray-900 bg-white border border-gray-300 rounded-xl dark:bg-gray-700 dark:text-white dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-[#8B7355] focus:border-[#8B7355]">
        </div>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            You see guides only for the pages your role can open.
        </p>
    </div>

    {{-- Guides, laid out like the sidebar --}}
    @foreach ($sections as $section)
        <section x-show="anyIn($el)" @if ($section['label']) aria-label="{{ $section['label'] }}" @endif>
            @if ($section['label'])
                <h2 class="{{ $sectionLabel }}">{{ $section['label'] }}</h2>
            @elseif (! $loop->first)
                <hr class="mb-6 border-gray-200 dark:border-gray-700">
            @endif

            <div class="space-y-4">
                @foreach ($section['groups'] as $group)
                    <div x-show="anyIn($el)">
                        @if ($group['label'])
                            <h3 class="px-1 mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $group['label'] }}</h3>
                        @endif

                        <div class="{{ $card }} divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($group['guides'] as $guide)
                                <details id="{{ $guide['key'] }}" class="group scroll-mt-20 md:scroll-mt-6"
                                         data-search="{{ $guide['search'] }}"
                                         x-show="match($el)"
                                         x-effect="if (words().length) $el.open = match($el)">
                                    <summary class="flex items-center justify-between gap-4 px-4 py-4 cursor-pointer list-none sm:px-6 min-h-[44px] hover:bg-gray-50 dark:hover:bg-gray-700/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#8B7355] [&::-webkit-details-marker]:hidden">
                                        <span class="min-w-0">
                                            <span class="block text-base font-semibold text-gray-900 dark:text-white">{{ $guide['title'] }}</span>
                                            <span class="block mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $guide['summary'] }}</span>
                                        </span>
                                        <i class="text-xs transition-transform fa-solid fa-chevron-down shrink-0 text-[#8B7355] dark:text-[#C4A97D] group-open:rotate-180 motion-reduce:transition-none" aria-hidden="true"></i>
                                    </summary>

                                    <div class="px-4 pb-5 space-y-5 sm:px-6">
                                        @foreach ($guide['tasks'] as $task)
                                            <div class="max-w-3xl">
                                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $task['title'] }}</h4>
                                                @foreach ($task['images'] as $image)
                                                    {{-- Numbered marks on the screenshot match the steps below it. --}}
                                                    <figure class="mt-2">
                                                        <img src="{{ asset($image['src']) }}" alt="{{ $image['alt'] }}" loading="lazy"
                                                             class="max-w-full h-auto bg-white border border-gray-200 rounded-xl dark:border-gray-700">
                                                        <figcaption class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">The numbers match the steps below.</figcaption>
                                                    </figure>
                                                @endforeach
                                                <ol class="mt-2 space-y-1.5 text-sm leading-6 text-gray-600 list-decimal pl-5 dark:text-gray-300">
                                                    @foreach ($task['steps'] as $step)
                                                        <li>{{ $step }}</li>
                                                    @endforeach
                                                </ol>
                                            </div>
                                        @endforeach

                                        @if ($guide['tips'])
                                            <div class="max-w-3xl p-3 border border-blue-200 rounded-xl bg-blue-50 dark:border-blue-800 dark:bg-blue-900/10">
                                                <p class="text-xs font-semibold tracking-wide text-blue-800 uppercase dark:text-blue-300">Good to know</p>
                                                <ul class="mt-1.5 space-y-1 text-sm leading-6 text-blue-900 list-disc pl-5 dark:text-blue-100">
                                                    @foreach ($guide['tips'] as $tip)
                                                        <li>{{ $tip }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        @if ($guide['url'])
                                            <a href="{{ $guide['url'] }}"
                                               class="inline-flex items-center gap-2 min-h-[44px] -my-3 px-1 text-sm font-medium text-[#7A6348] hover:underline dark:text-[#C4A97D] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] rounded-lg">
                                                Go to {{ $guide['title'] }}
                                                <i class="text-xs fa-solid fa-arrow-right" aria-hidden="true"></i>
                                            </a>
                                        @endif
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach

    {{-- No search results --}}
    <div x-show="words().length && !anyIn($root)" x-cloak class="{{ $card }} p-6 text-center">
        <p class="text-sm text-gray-600 dark:text-gray-300">No guides match "<span x-text="q.trim()"></span>".</p>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try another word, or ask the owner or your manager.</p>
    </div>
</div>

<script>
    // /help#appointments: open that guide and bring it into view (also when the hash changes).
    (function () {
        function openFromHash() {
            const id = decodeURIComponent(location.hash.slice(1));
            const guide = id ? document.getElementById(id) : null;
            if (!guide || guide.tagName !== 'DETAILS') return;
            guide.open = true;
            guide.scrollIntoView({ block: 'start' });
            guide.querySelector('summary')?.focus({ preventScroll: true });
        }
        document.addEventListener('DOMContentLoaded', openFromHash);
        window.addEventListener('hashchange', openFromHash);
    })();
</script>
@endsection
