{{--
    The Terms shown inside a window (customer registration, business
    registration, booking), rendered from App\Support\Legal\Terms so the
    window and /terms-and-conditions never disagree (landing footer Phase 2, D8).

    $audiences: Terms audiences to show, e.g. ['everyone', 'customers', 'bookings'].
    The links open in a new tab so a half-filled form isn't lost.
--}}
@php($termsSections = \App\Support\Legal\Terms::sections(null, $audiences))

<div class="space-y-5 text-sm leading-6 text-gray-600 dark:text-gray-300">
    @foreach ($termsSections as $i => $section)
        <section>
            <h3 class="font-semibold text-gray-900 dark:text-white">
                {{ $i + 1 }}. {{ $section['title'] }}
            </h3>
            <div class="mt-1 space-y-2">
                @include('legal.partials.blocks', ['blocks' => $section['blocks']])
            </div>
        </section>
    @endforeach

    <p class="pt-4 border-t border-gray-200 dark:border-gray-700">
        This is the part of our Terms that applies here.
        <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener" class="legal-link">Read the full Terms and Conditions</a>
        and our
        <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener" class="legal-link">Privacy Policy</a>
        (opens in a new tab).
    </p>
</div>
