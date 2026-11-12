{{-- One labelled dropdown in <x-table-filters>. Empty value = "all". --}}
@props([
    'name',
    'label',
    'options' => [],      // value => label
    'value' => null,
    'all' => null,        // label of the "everything" option; null = no such option
    'width' => 'sm:w-40',
])

@php
    $inputClass = 'w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl bg-white text-gray-900 '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:border-gray-600 dark:bg-gray-700 dark:text-white';
    $id = 'tf-' . $name . '-' . substr(md5($label), 0, 4);
@endphp

<div>
    <label for="{{ $id }}" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $name }}" class="{{ $inputClass }} {{ $width }}">
        @if ($all !== null)
            <option value="">{{ $all }}</option>
        @endif
        @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
    </select>
</div>
