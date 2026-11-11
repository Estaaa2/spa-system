{{-- One line at the top of a modal form that has required fields. --}}
<p {{ $attributes->merge(['class' => 'text-xs text-gray-500 dark:text-gray-400']) }}>
    Fields marked <x-required-mark /> are required.
</p>
