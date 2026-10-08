@extends('layouts.app')

@section('title', 'Staff Pay Setup')
@section('content')
@php
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';
    $btn = [
        'edit' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
    ];
    $th        = 'px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400';
    $badgeBase = 'inline-flex items-center gap-1 px-2.5 py-0.5 text-xs font-medium rounded-full';

    // Role colours — same map as staff/index.blade.php.
    $roleColors = [
        'manager'      => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        'therapist'    => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'receptionist' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'hr'           => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
        'finance'      => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
    ];
    $roleColorFallback = 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';

    $filters = [
        'all'         => 'All staff',
        'missing'     => 'Missing setup',
        'no_profile'  => 'No pay profile',
        'missing_ids' => 'Missing IDs',
    ];
    $shown = $groups->sum(fn ($g) => $g->count());

    $spa = auth()->user()?->spa;

    $hasPayrollAccess = (bool) (
        $spa
        && $spa->hasAccess()
        && $spa->hasFeature('payroll')
    );
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">
    <x-page-header
        title="Staff Pay Setup"
        subtitle="Pay profiles, statutory IDs and recurring items for every active staff member (all branches)."
    >
        <x-slot name="right">
            <a href="{{ route('payroll.setup.index') }}" class="{{ $btn['edit'] }}">
                <i class="text-xs fa-solid fa-sliders" aria-hidden="true"></i>
                Payroll Setup
            </a>
        </x-slot>
    </x-page-header>

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Active Staff</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">{{ $counts['total'] }}</h3>
                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">All branches</span>
            </div>
        </div>
        @foreach([['No Pay Profile', $counts['no_profile'], 'Not paid until set'], ['Missing IDs', $counts['missing_ids'], 'Needed for remittance']] as [$label, $n, $hint])
            <div class="p-4 border shadow-sm sm:p-5 rounded-2xl {{ $n > 0 ? 'bg-amber-50 border-amber-200 dark:bg-amber-900/10 dark:border-amber-800' : 'bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-700' }}">
                <p class="text-xs font-semibold tracking-wide uppercase {{ $n > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-gray-500 dark:text-gray-400' }}">{{ $label }}</p>
                <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                    <h3 class="text-2xl font-semibold sm:text-3xl {{ $n > 0 ? 'text-amber-900 dark:text-amber-200' : 'text-gray-900 dark:text-white' }}">{{ $n }}</h3>
                    <span class="text-xs sm:text-sm {{ $n > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-gray-500 dark:text-gray-400' }}">{{ $hint }}</span>
                </div>
            </div>
        @endforeach
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Not in Payroll</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">{{ $counts['excluded'] }}</h3>
                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">Payroll access unavailable</span>
            </div>
        </div>
    </div>

    {{-- Filter — same chip treatment as the billing date presets --}}
    <div class="p-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <nav class="flex flex-wrap items-center gap-2" aria-label="Filter staff">
            @foreach($filters as $key => $label)
                <a href="{{ route('payroll.staff.index', $key === 'all' ? [] : ['filter' => $key]) }}"
                   @if($filter === $key) aria-current="page" @endif
                   class="inline-flex items-center min-h-[44px] px-3 text-xs font-medium rounded-lg transition-colors
                          {{ $filter === $key ? 'bg-[#8B7355] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' }}">
                    {{ $label }}
                </a>
            @endforeach
            <span class="ml-auto text-xs text-gray-500 dark:text-gray-400">{{ $shown }} shown</span>
        </nav>
    </div>

    @forelse($groups as $branchId => $rows)
        @php $branch = $rows->first()['staff']->branch; @endphp
        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-col gap-2 px-4 py-4 border-b border-gray-200 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-gray-700">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ $branch?->name ?? 'No home branch' }}
                        @if($branch?->trashed()) <span class="text-sm font-normal text-gray-500">(deleted)</span> @endif
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $branch?->location ?? 'Assign a home branch so this staff member can be paid.' }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    @if ($hasPayrollAccess && $branch && ! $branch->trashed())
                        <span class="{{ $badgeBase }} bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300">
                            <i class="fa-solid fa-money-check-dollar text-[10px]" aria-hidden="true"></i>
                            Payroll enabled
                        </span>
                    @else
                        <span class="{{ $badgeBase }} bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                            Payroll unavailable
                        </span>
                    @endif
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $rows->count() }} staff</span>
                </div>
            </div>
            <div class="md:overflow-x-auto">
                <table role="table" class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700">
                    <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                        <tr role="row">
                            <th role="columnheader" class="{{ $th }}">Staff Member</th>
                            <th role="columnheader" class="{{ $th }}">Role</th>
                            <th role="columnheader" class="{{ $th }}">Current Pay</th>
                            <th role="columnheader" class="{{ $th }}">Commission</th>
                            <th role="columnheader" class="{{ $th }}">Statutory IDs</th>
                            <th role="columnheader" class="{{ $th }}">Actions</th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup" class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        @foreach($rows as $r)
                            @php
                                $p = $r['profile'];
                                $initial = strtoupper(substr($r['name'], 0, 1));
                            @endphp
                            <tr role="row" class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td role="cell" data-label="Staff Member" class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center w-10 h-10 text-sm font-semibold text-white rounded-full bg-[#8B7355] shrink-0">{{ $initial }}</div>
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">{{ $r['name'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $r['staff']->user?->email ?? 'No email' }}</p>
                                            @unless($r['in_payroll'])
                                                <span class="{{ $badgeBase }} mt-1 bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">Not included in payroll</span>
                                            @endunless
                                        </div>
                                    </div>
                                </td>
                                <td role="cell" data-label="Role" class="px-6 py-4">
                                    <span class="{{ $badgeBase }} {{ $roleColors[$r['role']] ?? $roleColorFallback }}">{{ $r['role'] ? ucfirst($r['role']) : 'No role' }}</span>
                                </td>
                                <td role="cell" data-label="Current Pay" class="px-6 py-4">
                                    @if($p)
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">₱{{ number_format((float) $p->base_rate, 2) }} <span class="font-normal text-gray-500 dark:text-gray-400">/ {{ $p->pay_basis === 'monthly' ? 'month' : 'day' }}</span></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Since {{ $p->effective_from->format('M d, Y') }}</p>
                                    @elseif($r['upcoming'])
                                        <span class="{{ $badgeBase }} bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">Starts {{ $r['upcoming']->effective_from->format('M d, Y') }}</span>
                                    @else
                                        <span class="{{ $badgeBase }} bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300"><i class="fa-solid fa-triangle-exclamation text-[10px]" aria-hidden="true"></i> No pay profile</span>
                                    @endif
                                </td>
                                <td role="cell" data-label="Commission" class="px-6 py-4">
                                    @if($p)
                                        <span class="{{ $badgeBase }} {{ $p->commission_enabled ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">{{ $p->commission_enabled ? 'On' : 'Off' }}</span>
                                    @else
                                        <span class="text-sm text-gray-500 dark:text-gray-400">—</span>
                                    @endif
                                </td>
                                <td role="cell" data-label="Statutory IDs" class="px-6 py-4">
                                    @if($r['missing_ids'] === [])
                                        <span class="{{ $badgeBase }} bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"><i class="fa-solid fa-check text-[10px]" aria-hidden="true"></i> Complete</span>
                                    @else
                                        <span class="{{ $badgeBase }} bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300" title="Missing: {{ implode(', ', $r['missing_ids']) }}">
                                            {{ 4 - count($r['missing_ids']) }}/4 · missing {{ implode(', ', $r['missing_ids']) }}
                                        </span>
                                    @endif
                                </td>
                                <td role="cell" data-label="Actions" class="px-6 py-4 rt-actions">
                                    <a href="{{ route('payroll.staff.show', $r['staff']) }}" class="{{ $btn['edit'] }}" aria-label="Open pay setup for {{ $r['name'] }}">
                                        <i class="text-xs fa-solid fa-arrow-right" aria-hidden="true"></i><span>Open</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="px-6 py-12 text-center bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <i class="mb-3 text-4xl text-gray-400 fa-solid fa-users" aria-hidden="true"></i>
            <p class="mb-2 text-gray-600 dark:text-gray-400">{{ $filter === 'all' ? 'No active staff yet' : 'Nothing missing — every active staff member is set up for this filter.' }}</p>
        </div>
    @endforelse
</div>

<style>
@media (max-width: 767px) {
    .rt, .rt tbody, .rt tr, .rt td { display: block; width: 100%; }
    .rt thead { display: none; }
    .rt tr { padding: 0.75rem 1rem; }
    .rt td { padding: 0.375rem 0 !important; text-align: left !important; }
    .rt td[data-label]::before {
        content: attr(data-label);
        display: block;
        margin-bottom: 0.125rem;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #6b7280;
    }
    .rt td.rt-actions { padding-top: 0.75rem !important; }
}
@media (max-width: 767px) and (prefers-color-scheme: dark) {
    .rt td[data-label]::before { color: #9ca3af; }
}
</style>
@endsection
