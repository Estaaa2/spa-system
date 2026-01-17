@props(['active'])

@php
$classes = $active
    ? 'group relative flex items-center w-full px-4 py-3
       text-[#6F5430] font-medium
       transition-all duration-200'
    : 'group relative flex items-center w-full px-4 py-3
       text-gray-600 dark:text-gray-400
       hover:text-[#6F5430]
       transition-all duration-200';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    <span class="relative inline-block pb-1">

        {{ $slot }}

        {{-- underline --}}
        <span
            class="absolute left-0 -bottom-1 h-[2px] w-full
                   bg-[#8B7355]
                   scale-x-0 origin-left
                   transition-transform duration-300 ease-out
                   group-hover:scale-x-100
                   {{ $active ? 'scale-x-100' : '' }}">
        </span>

    </span>
</a>
