@extends('layouts.public')

{{--
    Terms and Conditions, Privacy Policy and Refund Policy (landing footer
    Phase 2). The words come from App\Support\Legal\*; this view only lays
    them out, in the landing page's look: Playfair heading with gradient
    rules, rounded-3xl translucent cards, brown palette.
    $doc: title, updated, intro, sections. $related: route => label.
--}}

@section('title', $doc['title'])

@section('content')
    @php
        $hasGroups = collect($doc['sections'])->contains(fn ($s) => ! empty($s['group']));
        $tocGroups = collect($doc['sections'])
            ->values()
            ->map(fn ($s, $i) => $s + ['number' => $i + 1])
            ->groupBy(fn ($s) => $s['group'] ?? '');
    @endphp

    <div class="px-4 py-12 sm:px-6 sm:py-16">
        <div class="mx-auto max-w-6xl">

            {{-- Heading --}}
            <header class="text-center">
                <div class="flex items-center justify-center gap-4 sm:gap-6">
                    <span class="hidden sm:block h-px w-16 sm:w-24 bg-gradient-to-r from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                    <h1 class="text-3xl sm:text-4xl font-['Playfair_Display'] text-[#3C2F23] dark:text-white font-semibold">
                        {{ $doc['title'] }}
                    </h1>
                    <span class="hidden sm:block h-px w-16 sm:w-24 bg-gradient-to-l from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                </div>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    Last updated <time datetime="{{ $doc['version'] }}">{{ $doc['updated'] }}</time>
                </p>
            </header>

            <div class="mt-10 lg:grid lg:grid-cols-[16rem_minmax(0,1fr)] lg:gap-8 lg:items-start">

                {{-- Contents: collapsible on phones, sticky beside the text on large screens --}}
                <nav aria-label="On this page" class="mb-6 lg:mb-0 lg:sticky lg:top-28">
                    <details class="group lg:hidden p-5 shadow-sm bg-white/70 dark:bg-gray-900/40 rounded-3xl ring-1 ring-black/5 dark:ring-white/10">
                        <summary class="flex items-center justify-between cursor-pointer list-none text-sm font-semibold text-[#3C2F23] dark:text-white">
                            On this page
                            <i class="fa-solid fa-chevron-down text-xs text-[#8B7355] dark:text-[#C4A97D] transition-transform group-open:rotate-180" aria-hidden="true"></i>
                        </summary>
                        @include('legal.partials.toc', ['tocGroups' => $tocGroups])
                    </details>

                    <div class="hidden lg:block p-5 shadow-sm bg-white/70 dark:bg-gray-900/40 rounded-3xl ring-1 ring-black/5 dark:ring-white/10">
                        <p class="text-sm font-semibold text-[#3C2F23] dark:text-white">On this page</p>
                        @include('legal.partials.toc', ['tocGroups' => $tocGroups])
                    </div>
                </nav>

                {{-- Text --}}
                <article class="p-6 sm:p-10 shadow-sm bg-white/70 dark:bg-gray-900/40 rounded-3xl ring-1 ring-black/5 dark:ring-white/10">
                    <div class="space-y-4 text-[15px] leading-7 text-gray-700 dark:text-gray-300">
                        @foreach ($doc['intro'] as $paragraph)
                            <p>{!! $paragraph !!}</p>
                        @endforeach
                    </div>

                    @php($currentGroup = null)
                    @foreach ($doc['sections'] as $i => $section)
                        @if ($hasGroups && ($section['group'] ?? null) !== $currentGroup)
                            @php($currentGroup = $section['group'] ?? null)
                            <h2 class="mt-12 pb-3 text-xl sm:text-2xl font-['Playfair_Display'] font-semibold text-[#3C2F23] dark:text-white border-b border-[#E8DDD0] dark:border-white/10">
                                {{ $currentGroup }}
                            </h2>
                        @endif

                        <section id="{{ $section['id'] }}" class="mt-8">
                            @if ($hasGroups)
                                <h3 class="text-base sm:text-lg font-semibold text-[#3C2F23] dark:text-white">
                                    <span class="text-[#8B7355] dark:text-[#C4A97D]">{{ $i + 1 }}.</span> {{ $section['title'] }}
                                </h3>
                            @else
                                <h2 class="text-base sm:text-lg font-semibold text-[#3C2F23] dark:text-white">
                                    <span class="text-[#8B7355] dark:text-[#C4A97D]">{{ $i + 1 }}.</span> {{ $section['title'] }}
                                </h2>
                            @endif

                            <div class="mt-3 space-y-3 text-[15px] leading-7 text-gray-700 dark:text-gray-300">
                                @include('legal.partials.blocks', ['blocks' => $section['blocks']])
                            </div>
                        </section>
                    @endforeach

                    {{-- Related policies --}}
                    <div class="pt-6 mt-12 border-t border-[#E8DDD0] dark:border-white/10">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Related:
                            @foreach ($related as $route => $label)
                                <a href="{{ route($route) }}" class="legal-link">{{ $label }}</a>@if (! $loop->last)<span class="mx-1.5 text-gray-400" aria-hidden="true">·</span>@endif
                            @endforeach
                        </p>
                    </div>
                </article>
            </div>
        </div>
    </div>
@endsection
