{{-- Red asterisk after a required field's label. aria-hidden: the input's own
     `required` attribute is what screen readers announce. --}}
<span {{ $attributes->merge(['class' => 'text-red-600 dark:text-red-400']) }} aria-hidden="true">*</span>
