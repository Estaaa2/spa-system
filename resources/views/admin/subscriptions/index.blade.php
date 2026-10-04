@extends('layouts.app')

@section('title', 'Subscriptions')
@section('content')
@php
    $btnPrimary = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
                . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
                . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800 '
                . 'bg-[#8B7355] text-white hover:bg-[#7A6348]';

    $inputClass = 'block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 '
                . 'dark:placeholder-gray-400 dark:text-white';

    $tabBase     = 'flex items-center justify-center flex-1 gap-2 min-h-[44px] px-3 text-sm font-medium whitespace-nowrap transition rounded-xl';
    $tabActive   = $tabBase . ' text-white shadow-sm bg-gradient-to-r from-[#7A6348] to-[#6F5430]';
    $tabInactive = $tabBase . ' text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700';

    $stateClasses = [
        'active'  => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'expired' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
        'unpaid'  => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    ];

    $kpiClass   = 'p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700';
    $kpiLabel   = 'min-w-0 text-xs font-semibold tracking-wide text-gray-500 uppercase truncate dark:text-gray-400';
    $kpiIconBox = 'flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-[#8B7355]/10 dark:bg-[#C4A97D]/10';
    $kpiIcon    = 'text-sm text-[#8B7355] dark:text-[#C4A97D] fa-solid';
    $thClass    = 'px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400';

    $tabs = ['' => 'All'] + array_combine($states, array_map('ucfirst', $states));
@endphp
<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">
    <x-page-header
        title="Subscriptions"
        subtitle="Professional plan payments made by spas through PayMongo."
    />

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <div class="{{ $kpiClass }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Collected</p>
                <div class="{{ $kpiIconBox }}"><i class="{{ $kpiIcon }} fa-peso-sign" aria-hidden="true"></i></div>
            </div>
            <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">₱{{ number_format($collectedTotal, 0) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">All paid subscriptions</p>
        </div>

        <div class="{{ $kpiClass }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">This Month</p>
                <div class="{{ $kpiIconBox }}"><i class="{{ $kpiIcon }} fa-calendar-day" aria-hidden="true"></i></div>
            </div>
            <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">₱{{ number_format($collectedMonth, 0) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Paid since {{ now()->startOfMonth()->format('M d') }}</p>
        </div>

        <div class="{{ $kpiClass }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Active</p>
                <div class="{{ $kpiIconBox }}"><i class="{{ $kpiIcon }} fa-circle-check" aria-hidden="true"></i></div>
            </div>
            <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ $counts['active'] }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Paid and not yet expired</p>
        </div>

        @if ($expiringSoonCount > 0)
            <div class="p-4 border shadow-sm sm:p-5 bg-amber-50 border-amber-200 rounded-2xl dark:bg-amber-900/10 dark:border-amber-800">
                <div class="flex items-center justify-between gap-2">
                    <p class="min-w-0 text-xs font-semibold tracking-wide uppercase truncate text-amber-700 dark:text-amber-400">Expiring</p>
                    <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-900/30">
                        <i class="text-sm fa-solid fa-hourglass-half text-amber-600 dark:text-amber-400" aria-hidden="true"></i>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-bold text-amber-900 dark:text-amber-200">{{ $expiringSoonCount }}</p>
                <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">Within the next 7 days</p>
            </div>
        @else
            <div class="{{ $kpiClass }}">
                <div class="flex items-center justify-between gap-2">
                    <p class="{{ $kpiLabel }}">Expiring</p>
                    <div class="{{ $kpiIconBox }}"><i class="{{ $kpiIcon }} fa-hourglass-half" aria-hidden="true"></i></div>
                </div>
                <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">0</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Within the next 7 days</p>
            </div>
        @endif
    </div>

    <nav aria-label="Subscription state"
         class="flex gap-1 p-1.5 overflow-x-auto bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        @foreach ($tabs as $value => $label)
            @php $isCurrent = $value === (string) $state; @endphp
            <a href="{{ route('admin.subscriptions.index', array_filter(['state' => $value, 'q' => $q])) }}"
               @if ($isCurrent) aria-current="page" @endif
               class="{{ $isCurrent ? $tabActive : $tabInactive }}">
                {{ $label }}
                <span class="text-xs opacity-75">{{ $value === '' ? array_sum($counts) : $counts[$value] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-col gap-3 px-4 py-4 border-b border-gray-200 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ $tabs[(string) $state] }} Subscriptions</h2>

            <form method="GET" class="flex gap-2">
                @if ($state)
                    <input type="hidden" name="state" value="{{ $state }}">
                @endif
                <input type="search" name="q" value="{{ $q }}" aria-label="Search spa name"
                       placeholder="Search spa name" class="{{ $inputClass }} sm:w-64">
                <button type="submit" class="{{ $btnPrimary }}">Search</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="{{ $thClass }}">Spa</th>
                        <th class="{{ $thClass }}">Amount</th>
                        <th class="{{ $thClass }}">State</th>
                        <th class="{{ $thClass }}">Paid</th>
                        <th class="{{ $thClass }}">Expires</th>
                        <th class="{{ $thClass }}">PayMongo Reference</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    @forelse ($subscriptions as $subscription)
                        @php
                            $rowState = $subscription->payment_status !== 'paid'
                                ? 'unpaid'
                                : ($subscription->expires_at?->isFuture() ? 'active' : 'expired');
                        @endphp
                        <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $subscription->spa?->name ?? 'Deleted spa' }}
                                    @if ($subscription->spa?->trashed())
                                        <span class="text-xs font-normal text-gray-500 dark:text-gray-400">(removed)</span>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $subscription->spa?->owner?->email }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">₱{{ number_format($subscription->amount, 2) }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 text-xs font-medium rounded-full {{ $stateClasses[$rowState] }}">{{ ucfirst($rowState) }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $rowState === 'unpaid' ? '—' : $subscription->starts_at?->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $rowState === 'unpaid' ? '—' : $subscription->expires_at?->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-gray-500 dark:text-gray-400">
                                {{ $subscription->paymongo_payment_id ?? $subscription->paymongo_checkout_id ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <i class="mb-3 text-4xl text-gray-400 fa-solid fa-receipt" aria-hidden="true"></i>
                                <p class="text-sm text-gray-500 dark:text-gray-400">No subscriptions found.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($subscriptions->hasPages())
            <div class="px-4 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
                {{ $subscriptions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
