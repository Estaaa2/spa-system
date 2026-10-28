{{--
    Profile photo, or the brown initials circle used across the staff side
    (staff list, payroll staff, deployment) when there is no photo.

    <x-avatar :user="$user" />                 32px, nav
    <x-avatar :user="$user" size="md" />       40px, tables and lists
    <x-avatar :user="$user" size="xl" />       128px, profile page

    The photo comes from the authorized route (profile-photos.show), never a
    public URL. Decorative by default (alt=""): the name is always printed
    next to it. Pass :alt="..." when it stands alone.
--}}
@props([
    'user',
    'size' => 'sm',
    'alt' => '',
])

@php
    $sizes = [
        'sm' => 'w-8 h-8 text-xs',
        'md' => 'w-10 h-10 text-sm',
        'lg' => 'w-16 h-16 text-lg',
        'xl' => 'w-32 h-32 text-4xl',
    ];
    $sizeClasses = $sizes[$size] ?? $sizes['sm'];
    $photoUrl = $user?->profilePhotoUrl();
    $initials = $user?->initials() ?? '?';
@endphp

@if ($photoUrl)
    <img src="{{ $photoUrl }}"
         alt="{{ $alt }}"
         loading="lazy"
         {{ $attributes->merge(['class' => "{$sizeClasses} rounded-full object-cover bg-gray-100 dark:bg-gray-700 shrink-0"]) }}>
@else
    <span {{ $attributes->merge(['class' => "{$sizeClasses} inline-flex items-center justify-center font-semibold text-white rounded-full bg-[#8B7355] shrink-0 select-none"]) }}
          @if ($alt !== '') role="img" aria-label="{{ $alt }}" @else aria-hidden="true" @endif>
        {{ $initials }}
    </span>
@endif
