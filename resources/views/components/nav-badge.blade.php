{{--
    Sidebar notification badge (sidebar & RBAC rework Phase 4, decision D5).
    Always rendered (hidden at 0) so the 60-second refresh in
    layouts/partials/sidebar-script only flips text, `hidden` and the class set —
    it never builds markup, so it can't drift from this file. Both class sets live
    here as literal strings, where Tailwind's scanner can see them.

    Amber: items need action. Red with "!": urgent (an unassigned booking that
    starts today, answer P14). The visible number is aria-hidden; the full
    sentence is read instead.
--}}
@props(['key', 'count' => 0, 'label' => null, 'urgent' => false])

@php
    $count = (int) $count;
    $normalClasses = 'inline-flex items-center gap-0.5 px-2 py-0.5 text-[11px] font-semibold leading-none rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
    $urgentClasses = 'inline-flex items-center gap-0.5 px-2 py-0.5 text-[11px] font-semibold leading-none rounded-full bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
@endphp

<span data-nav-badge="{{ $key }}" data-count="{{ $count }}"
    data-class-normal="{{ $normalClasses }}" data-class-urgent="{{ $urgentClasses }}"
    class="{{ $urgent ? $urgentClasses : $normalClasses }}" @if ($count <= 0) hidden @endif>
    <span aria-hidden="true" data-nav-badge-urgent @if (! $urgent) hidden @endif>!</span>
    <span aria-hidden="true" data-nav-badge-count>{{ $count > 99 ? '99+' : $count }}</span>
    <span class="sr-only" data-nav-badge-label>{{ $count > 0 ? ', ' . $label : '' }}</span>
</span>
