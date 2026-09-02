@props(['active' => false])

@php
    $base = 'group relative flex items-center w-full min-h-[44px] px-4 py-2.5 rounded-lg transition-colors duration-200';

    $state = $active
        ? 'font-medium text-[#6F5430] bg-[#8B7355]/[0.07] dark:text-[#C4A97D] dark:bg-[#C4A97D]/10'
        : 'text-gray-600 hover:text-[#6F5430] hover:bg-gray-50 dark:text-gray-300 dark:hover:text-[#C4A97D] dark:hover:bg-gray-700/40';

    $underline = $active
        ? 'bg-[#8B7355] dark:bg-[#C4A97D] scale-x-100'
        : 'bg-gray-300 dark:bg-gray-600 scale-x-0 group-hover:scale-x-100';
@endphp

<a {{ $attributes->merge(['class' => $base . ' ' . $state]) }}
   @if ($active) aria-current="page" @endif>

    {{-- inline-block so the underline hugs the label, not the whole row --}}
    <span class="relative inline-block pb-1">

        {{ $slot }}

        {{-- underline --}}
        <span aria-hidden="true"
              class="absolute left-0 -bottom-1 h-[2px] w-full
                     origin-left transition-transform duration-300 ease-out
                     {{ $underline }}"></span>

    </span>
</a>