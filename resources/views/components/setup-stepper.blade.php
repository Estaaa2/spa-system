{{--
    Setup wizard progress. Usage: <x-setup-stepper :current="2" />

    Below md the labels collapse to circles plus a "Step X of 3" line, so the
    row never overflows (the old justify-center + overflow-x-auto row clipped
    its first step off-screen on phones). Labels stay readable to screen
    readers at every size via sr-only / md:not-sr-only.
--}}
@props(['current' => 1])

@php
    $steps = [
        1 => ['label' => 'Business Info', 'route' => 'setup.index'],
        2 => ['label' => 'Main Branch', 'route' => 'setup.branches'],
        3 => ['label' => 'Documents', 'route' => 'setup.documents'],
    ];

    $current = (int) $current;
    $total = count($steps);
@endphp

<nav aria-label="Setup progress" {{ $attributes->merge(['class' => 'mb-6 sm:mb-8']) }}>
    <ol class="flex items-center justify-center">
        @foreach ($steps as $number => $step)
            @php
                $state = $number < $current
                    ? 'done'
                    : ($number === $current ? 'current' : 'upcoming');

                $circleClass = $state === 'upcoming'
                    ? 'text-gray-600 bg-gray-200 dark:bg-gray-700 dark:text-gray-300'
                    : 'text-white bg-[#8B7355]';

                $labelClass = match ($state) {
                    'current' => 'font-semibold text-gray-800 dark:text-gray-100',
                    'done' => 'font-medium text-gray-700 dark:text-gray-300',
                    default => 'font-medium text-gray-500 dark:text-gray-400',
                };

                $tag = $state === 'done' ? 'a' : 'span';
            @endphp

            <li class="flex items-center" @if ($state === 'current') aria-current="step" @endif>
                @unless ($loop->first)
                    <span
                        aria-hidden="true"
                        class="w-8 h-1 mx-2 rounded md:w-16 md:mx-4 lg:w-24 {{ $state === 'upcoming' ? 'bg-gray-200 dark:bg-gray-700' : 'bg-[#8B7355]/30' }}"
                    ></span>
                @endunless

                <{{ $tag }}
                    @if ($state === 'done') href="{{ route($step['route']) }}" @endif
                    class="flex items-center justify-center min-w-[44px] min-h-[44px] rounded-full {{ $state === 'done' ? 'hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355]/40' : '' }}"
                >
                    <span class="flex items-center justify-center w-8 h-8 text-sm rounded-full shrink-0 md:w-10 md:h-10 {{ $circleClass }}">
                        @if ($state === 'done')
                            <i class="text-xs fa-solid fa-check md:text-sm" aria-hidden="true"></i>
                            <span class="sr-only">Completed:</span>
                        @else
                            {{ $number }}
                        @endif
                    </span>

                    <span class="text-sm sr-only md:not-sr-only md:ml-3 {{ $labelClass }}">
                        {{ $step['label'] }}
                    </span>
                </{{ $tag }}>
            </li>
        @endforeach
    </ol>

    <p aria-hidden="true" class="mt-2 text-sm text-center text-gray-600 md:hidden dark:text-gray-400">
        Step {{ $current }} of {{ $total }} ·
        <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $steps[$current]['label'] ?? '' }}</span>
    </p>
</nav>