{{--
    Shared date-range filter for the Reports and Finance pages (reports & finance revamp).
    Same field look as <x-table-filters>: labelled 44 px fields, Apply, and a summary line.
    Picking a period fills the dates and applies at once; editing a date switches the
    period to "Custom range" and highlights Apply. GET, so the range stays in the URL.

    <x-date-range-filter :from="$from" :to="$to" :compare-days="$periodDays">
        <input type="hidden" name="expense_status" value="...">   (keeps other query values)
    </x-date-range-filter>
--}}
@props([
    'from',                 // Y-m-d string or Carbon
    'to',
    'action' => null,       // defaults to the current URL without its query
    'compareDays' => null,  // shows "Compared with the N days before." when set
])

@php
    $fromC = \Illuminate\Support\Carbon::parse($from)->startOfDay();
    $toC   = \Illuminate\Support\Carbon::parse($to)->startOfDay();
    $fromS = $fromC->toDateString();
    $toS   = $toC->toDateString();
    $today = now()->toDateString();

    $presets = [
        'today'     => ['Today',         $today,                                        $today],
        '7d'        => ['Past 7 days',   now()->subDays(6)->toDateString(),             $today],
        '30d'       => ['Past 30 days',  now()->subDays(29)->toDateString(),            $today],
        'month'     => ['This month',    now()->startOfMonth()->toDateString(),         $today],
        'lastmonth' => ['Last month',    now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                                         now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
        '3mo'       => ['Past 3 months', now()->subMonths(3)->toDateString(),           $today],
        'year'      => ['This year',     now()->startOfYear()->toDateString(),          $today],
    ];

    $selected = 'custom';
    foreach ($presets as $key => [, $pFrom, $pTo]) {
        if ($pFrom === $fromS && $pTo === $toS) { $selected = $key; break; }
    }
    // Revenue and Billing open on the whole current month; show that as "This month" too.
    if ($selected === 'custom' && $fromS === now()->startOfMonth()->toDateString()
        && $toS === now()->endOfMonth()->toDateString()) {
        $selected = 'month';
    }

    $days = $fromC->diffInDays($toC) + 1;
    $rangeLabel = $fromS === $toS
        ? $fromC->format('M j, Y')
        : ($fromC->year === $toC->year
            ? $fromC->format('M j') . ' – ' . $toC->format('M j, Y')
            : $fromC->format('M j, Y') . ' – ' . $toC->format('M j, Y'));

    $id = 'drf-' . substr(md5($fromS . $toS . ($action ?? '')), 0, 6);
    $inputClass = 'w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl bg-white text-gray-900 '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:border-gray-600 dark:bg-gray-700 dark:text-white';
    $labelClass = 'block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300';
    $applyClass = 'inline-flex items-center justify-center w-full gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm font-medium '
                . 'rounded-xl transition-colors border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                . 'dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 sm:w-auto '
                . 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 '
                . 'dark:focus-visible:ring-offset-gray-800';
@endphp

<form method="GET" action="{{ $action ?? url()->current() }}" data-date-range
      role="search" aria-label="Date range"
      {{ $attributes->merge(['class' => 'p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700 print:hidden']) }}>
    {{ $slot }}

    <div class="grid grid-cols-2 gap-3 sm:flex sm:flex-wrap sm:items-end">
        <div class="col-span-2 sm:w-48">
            <label for="{{ $id }}-preset" class="{{ $labelClass }}">Period</label>
            <select id="{{ $id }}-preset" data-preset class="{{ $inputClass }}">
                @foreach ($presets as $key => [$label, $pFrom, $pTo])
                    <option value="{{ $key }}" data-from="{{ $pFrom }}" data-to="{{ $pTo }}" @selected($selected === $key)>{{ $label }}</option>
                @endforeach
                <option value="custom" @selected($selected === 'custom')>Custom range</option>
            </select>
        </div>

        <div class="sm:w-40">
            <label for="{{ $id }}-from" class="{{ $labelClass }}">From</label>
            <input id="{{ $id }}-from" type="date" name="from" value="{{ $fromS }}" required data-range-from class="{{ $inputClass }}">
        </div>

        <div class="sm:w-40">
            <label for="{{ $id }}-to" class="{{ $labelClass }}">To</label>
            <input id="{{ $id }}-to" type="date" name="to" value="{{ $toS }}" min="{{ $fromS }}" required data-range-to class="{{ $inputClass }}">
        </div>

        <div class="col-span-2 sm:col-span-1">
            <button type="submit" data-apply class="{{ $applyClass }}">
                <i class="text-xs fa-solid fa-filter" aria-hidden="true"></i>
                Apply
            </button>
        </div>
    </div>

    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400" aria-live="polite">
        <i class="mr-1 fa-regular fa-calendar text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $rangeLabel }}</span>
        · {{ $days }} {{ \Illuminate\Support\Str::plural('day', $days) }}
        @if ($compareDays)
            · Compared with the {{ $compareDays }} {{ \Illuminate\Support\Str::plural('day', $compareDays) }} before
        @endif
    </p>
</form>

@once
    <script>
        // Period picker: a preset fills both dates and applies; a typed date means "Custom range".
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('form[data-date-range]').forEach(form => {
                const preset = form.querySelector('[data-preset]');
                const from   = form.querySelector('[data-range-from]');
                const to     = form.querySelector('[data-range-to]');
                const apply  = form.querySelector('[data-apply]');

                const markDirty = () => {
                    apply.classList.add('bg-[#8B7355]', 'text-white', 'border-[#8B7355]', 'hover:bg-[#7A6348]');
                    apply.classList.remove('bg-white', 'text-gray-700', 'hover:bg-gray-50', 'dark:bg-gray-700', 'dark:text-gray-200', 'dark:hover:bg-gray-600');
                };

                preset.addEventListener('change', () => {
                    const opt = preset.selectedOptions[0];
                    if (!opt || preset.value === 'custom') { from.focus(); return; }
                    from.value = opt.dataset.from;
                    to.value   = opt.dataset.to;
                    to.min     = from.value;
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                });

                [from, to].forEach(input => input.addEventListener('change', () => {
                    preset.value = 'custom';
                    to.min = from.value;
                    markDirty();
                }));
            });
        });
    </script>
@endonce
