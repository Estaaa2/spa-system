{{--
    Shared table filter bar (table filters workstream, Q1 A). The Pay Runs pattern made reusable:
    labelled fields, a search box where the table has names, Apply, Clear filters, and
    "Showing X of Y". Batch filtering on the server (GET), so filters stay in the URL and
    work with paged tables.

    <x-table-filters :action="route('applications.index')" :shown="$shown" :total="$total"
                     :active="$activeFilters" noun="applicant"
                     search-placeholder="Name or email" :search="$filters['q']">
        <x-table-filters.select name="status" label="Status" all="All statuses" :options="[...]" :value="$filters['status']" />
    </x-table-filters>

    Omit :search to hide the search box. Put hidden inputs in the slot to keep other query values.
--}}
@props([
    'action',
    'shown' => null,
    'total' => null,
    'active' => 0,
    'noun' => 'record',
    'search' => false,           // false = no search box; string|null = its current value
    'searchName' => 'q',
    'searchLabel' => 'Search',
    'searchPlaceholder' => 'Search',
    'align' => 'end',            // 'end' beside a card title, 'start' when the bar has its own card
])

@php
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';
    $neutral = $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
             . 'dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600';
    $inputClass = 'w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl bg-white text-gray-900 '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:border-gray-600 dark:bg-gray-700 dark:text-white';
    $labelClass = 'block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300';
    $hasSearch = $search !== false;
    $formId = 'tf-' . substr(md5($action . $noun), 0, 8);
@endphp

<div {{ $attributes->merge(['class' => $align === 'start' ? 'w-full' : 'w-full lg:w-auto']) }}>
    <form id="{{ $formId }}" method="GET" action="{{ $action }}" data-table-filters
          class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-end {{ $align === 'start' ? '' : 'lg:justify-end' }}" role="search" aria-label="Filter {{ \Illuminate\Support\Str::plural($noun) }}">
        @if ($hasSearch)
            <div class="col-span-2 sm:w-56">
                <label for="{{ $formId }}-q" class="{{ $labelClass }}">{{ $searchLabel }}</label>
                <input id="{{ $formId }}-q" type="search" name="{{ $searchName }}" value="{{ $search }}"
                       maxlength="100" placeholder="{{ $searchPlaceholder }}" class="{{ $inputClass }}">
            </div>
        @endif

        {{ $slot }}

        <div class="flex items-end col-span-2 gap-2 sm:col-span-1">
            <button type="submit" class="flex-1 sm:flex-none {{ $neutral }}" data-filter-apply>
                <i class="text-xs fa-solid fa-filter" aria-hidden="true"></i>
                Apply
            </button>
            @if ($active > 0)
                <a href="{{ $action }}" class="flex-1 sm:flex-none {{ $neutral }}">
                    <i class="text-xs fa-solid fa-xmark" aria-hidden="true"></i>
                    Clear
                </a>
            @endif
        </div>
    </form>

    @if ($shown !== null && $total !== null)
        <p class="mt-2 text-xs text-gray-500 {{ $align === 'start' ? '' : 'lg:text-right' }} dark:text-gray-400" aria-live="polite">
            @if ($active > 0)
                Showing {{ $shown }} of {{ $total }} {{ \Illuminate\Support\Str::plural($noun, $total) }}
                · {{ $active }} {{ \Illuminate\Support\Str::plural('filter', $active) }} on
            @else
                {{ $total }} {{ \Illuminate\Support\Str::plural($noun, $total) }}
            @endif
        </p>
    @endif
</div>

@once
    <script>
        // An edited but not yet applied filter highlights Apply, so it isn't forgotten
        // (batch filtering: "the user has to remember to apply the filters").
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('form[data-table-filters]').forEach(form => {
                const btn = form.querySelector('[data-filter-apply]');
                const mark = () => {
                    btn?.classList.add('bg-[#8B7355]', 'text-white', 'border-[#8B7355]', 'hover:bg-[#7A6348]');
                    btn?.classList.remove('bg-white', 'text-gray-700', 'hover:bg-gray-50', 'dark:bg-gray-700', 'dark:text-gray-200');
                };
                form.addEventListener('change', mark);
                form.addEventListener('input', mark);
            });
        });
    </script>
@endonce
