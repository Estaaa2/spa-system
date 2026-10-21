@extends('layouts.app')

@section('title', 'Subscriptions')
@section('content')
@php
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800 '
             . 'disabled:cursor-not-allowed disabled:opacity-50';

    $btnPrimary = $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]';
    $btnEdit = $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
             . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700';
    $btnNeutral = $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                . 'dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600';

    $inputClass = 'block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 '
                . 'dark:placeholder-gray-400 dark:text-white';
    $labelClass = 'block mb-2 text-sm font-medium text-gray-900 dark:text-white';

    $tabBase     = 'flex items-center justify-center flex-1 gap-2 min-h-[44px] px-3 text-sm font-medium whitespace-nowrap transition rounded-xl';
    $tabActive   = $tabBase . ' text-white shadow-sm bg-gradient-to-r from-[#7A6348] to-[#6F5430]';
    $tabInactive = $tabBase . ' text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700';

    $stateClasses = [
        'active'    => ['Active', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
        'scheduled' => ['Scheduled', 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'],
        'ended'     => ['Ended', 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300'],
        'credited'  => ['Credited to upgrade', 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'],
        'pending'   => ['Awaiting payment', 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'],
        'failed'    => ['Not completed', 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'],
        'expired'   => ['Abandoned', 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'],
    ];

    $planClasses = [
        'basic'   => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        'premium' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    ];

    $kindLabels = [
        'new' => 'New plan',
        'renewal' => 'Renewal',
        'upgrade' => 'Upgrade',
        'downgrade' => 'Plan change',
    ];

    $kpiClass   = 'p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700';
    $kpiWarn    = 'p-4 border shadow-sm sm:p-5 bg-amber-50 border-amber-200 rounded-2xl dark:bg-amber-900/10 dark:border-amber-800';
    $kpiLabel   = 'min-w-0 text-xs font-semibold tracking-wide text-gray-500 uppercase truncate dark:text-gray-400';
    $kpiLabelWarn = 'min-w-0 text-xs font-semibold tracking-wide uppercase truncate text-amber-700 dark:text-amber-400';
    $kpiIconBox = 'flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-[#8B7355]/10 dark:bg-[#C4A97D]/10';
    $kpiIconBoxWarn = 'flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-900/30';
    $kpiIcon    = 'text-sm text-[#8B7355] dark:text-[#C4A97D] fa-solid';
    $kpiIconWarn = 'text-sm text-amber-600 dark:text-amber-400 fa-solid';
    $thClass    = 'px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400';

    $tabLabels = [
        '' => 'All',
        'active' => 'Active',
        'scheduled' => 'Scheduled',
        'ended' => 'Ended',
        'unpaid' => 'Unpaid',
        'trialing' => 'Trialing',
        'lapsed' => 'Grace & Locked',
    ];

    $tabTitles = [
        '' => 'All Payments',
        'active' => 'Active Periods',
        'scheduled' => 'Scheduled Periods',
        'ended' => 'Ended Periods',
        'unpaid' => 'Unpaid Checkouts',
        'trialing' => 'Spas on Free Trial',
        'lapsed' => 'Spas in Grace or Locked',
    ];

    $isSpaTab = in_array($state, $spaTabs, true);
    $canExtend = auth()->user()->can('edit registered spas');
    $warnLapsed = ($kpis['inGrace'] + $kpis['locked']) > 0;
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">
    <x-page-header
        title="Subscriptions"
        subtitle="Free trials, paid periods and PayMongo payments of every spa."
    />

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
        <div class="{{ $kpiClass }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Active Trials</p>
                <div class="{{ $kpiIconBox }}"><i class="{{ $kpiIcon }} fa-hourglass-start" aria-hidden="true"></i></div>
            </div>
            <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($kpis['trialsActive']) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ number_format($kpis['trialsEndingSoon']) }} ending within 7 days
            </p>
        </div>

        <div class="{{ $kpiClass }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Trial Conversion</p>
                <div class="{{ $kpiIconBox }}"><i class="{{ $kpiIcon }} fa-arrow-trend-up" aria-hidden="true"></i></div>
            </div>
            <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">
                {{ $kpis['conversionRate'] === null ? '—' : $kpis['conversionRate'] . '%' }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                @if ($kpis['trialsEnded'] > 0)
                    {{ $kpis['trialsConverted'] }} of {{ $kpis['trialsEnded'] }} trials ended in 90 days paid
                @else
                    No trials ended in the last 90 days
                @endif
            </p>
        </div>

        <div class="{{ $kpiClass }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Collected</p>
                <div class="{{ $kpiIconBox }}"><i class="{{ $kpiIcon }} fa-peso-sign" aria-hidden="true"></i></div>
            </div>
            <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">₱{{ number_format($kpis['revenueTotal'], 0) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                ₱{{ number_format($kpis['revenueMonth'], 0) }} paid since {{ now()->startOfMonth()->format('M d') }}
            </p>
        </div>

        <div class="{{ $kpiClass }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Paid &amp; Active</p>
                <div class="{{ $kpiIconBox }}"><i class="{{ $kpiIcon }} fa-circle-check" aria-hidden="true"></i></div>
            </div>
            <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($counts['active']) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Periods running now · {{ number_format($counts['scheduled']) }} scheduled
            </p>
        </div>

        <div class="{{ $kpis['expiringSoon'] > 0 ? $kpiWarn : $kpiClass }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpis['expiringSoon'] > 0 ? $kpiLabelWarn : $kpiLabel }}">Expiring</p>
                <div class="{{ $kpis['expiringSoon'] > 0 ? $kpiIconBoxWarn : $kpiIconBox }}">
                    <i class="{{ $kpis['expiringSoon'] > 0 ? $kpiIconWarn : $kpiIcon }} fa-hourglass-half" aria-hidden="true"></i>
                </div>
            </div>
            <p class="mt-3 text-3xl font-bold {{ $kpis['expiringSoon'] > 0 ? 'text-amber-900 dark:text-amber-200' : 'text-gray-900 dark:text-white' }}">
                {{ number_format($kpis['expiringSoon']) }}
            </p>
            <p class="mt-1 text-xs {{ $kpis['expiringSoon'] > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}">
                Spas ending within 7 days, not renewed
            </p>
        </div>

        <div class="{{ $warnLapsed ? $kpiWarn : $kpiClass }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $warnLapsed ? $kpiLabelWarn : $kpiLabel }}">Grace &amp; Locked</p>
                <div class="{{ $warnLapsed ? $kpiIconBoxWarn : $kpiIconBox }}">
                    <i class="{{ $warnLapsed ? $kpiIconWarn : $kpiIcon }} fa-lock" aria-hidden="true"></i>
                </div>
            </div>
            <p class="mt-3 text-3xl font-bold {{ $warnLapsed ? 'text-amber-900 dark:text-amber-200' : 'text-gray-900 dark:text-white' }}">
                {{ number_format($kpis['inGrace'] + $kpis['locked']) }}
            </p>
            <p class="mt-1 text-xs {{ $warnLapsed ? 'text-amber-700 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}">
                {{ number_format($kpis['inGrace']) }} in grace · {{ number_format($kpis['locked']) }} locked
            </p>
        </div>
    </div>

    {{-- Tabs: payment rows, then spa lists --}}
    <nav aria-label="Subscription state"
         class="flex gap-1 p-1.5 overflow-x-auto bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        @foreach ($tabLabels as $value => $label)
            @php $isCurrent = $value === (string) $state; @endphp

            @if ($value === 'trialing')
                <span class="self-stretch w-px my-1.5 bg-gray-200 shrink-0 dark:bg-gray-700" aria-hidden="true"></span>
            @endif

            <a href="{{ route('admin.subscriptions.index', array_filter(['state' => $value, 'q' => $q])) }}"
               @if ($isCurrent) aria-current="page" @endif
               class="{{ $isCurrent ? $tabActive : $tabInactive }}">
                {{ $label }}
                <span class="text-xs opacity-75">{{ $counts[$value] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-col gap-3 px-4 py-4 border-b border-gray-200 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-gray-700">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ $tabTitles[(string) $state] }}</h2>
                @if ($state === 'trialing')
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Trials are free and are not counted as revenue.</p>
                @elseif ($state === 'lapsed')
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Grace lasts {{ config('subscription.grace_days', 3) }} days after paid time ends. Locked spas keep their data.
                    </p>
                @endif
            </div>

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
            @if ($state === 'trialing')
                {{-- ── Trialing spas ───────────────────────────────────── --}}
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="{{ $thClass }}">Spa</th>
                            <th class="{{ $thClass }}">Trial Ends</th>
                            <th class="{{ $thClass }}">Paid Next</th>
                            <th class="{{ $thClass }}">Extended</th>
                            @if ($canExtend)
                                <th class="{{ $thClass }}">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        @forelse ($spas as $spa)
                            @php
                                $next = $spa->scheduledPeriods()->first();
                                $extended = (int) ($spa->extended_days ?? 0);
                                $daysLeft = $spa->trialDaysLeft();
                            @endphp
                            <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td class="px-6 py-4">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $spa->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $spa->owner?->email }}</p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <p class="text-sm text-gray-900 dark:text-white">{{ $spa->trial_ends_at->format('M d, Y') }}</p>
                                    <p class="text-xs {{ $daysLeft <= 3 ? 'font-semibold text-amber-700 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $daysLeft }} day{{ $daysLeft === 1 ? '' : 's' }} left
                                    </p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if ($next)
                                        @php $nextPlan = \App\Models\Spa::normalisePlanKey($next->business_tier); @endphp
                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $planClasses[$nextPlan] ?? $planClasses['basic'] }}">
                                            {{ config("plans.{$nextPlan}.name", ucfirst($nextPlan)) }}
                                        </span>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ ucfirst($next->billing_cycle) }}, from {{ $next->starts_at->format('M d, Y') }}
                                        </p>
                                    @else
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Not yet</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap dark:text-gray-400">
                                    {{ $extended > 0 ? '+' . $extended . ' day' . ($extended === 1 ? '' : 's') : '—' }}
                                </td>
                                @if ($canExtend)
                                    <td class="px-6 py-4">
                                        @include('admin.subscriptions.partials.extend-button', ['spa' => $spa, 'extended' => $extended])
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canExtend ? 5 : 4 }}" class="px-6 py-12 text-center">
                                    <i class="mb-3 text-4xl text-gray-400 fa-solid fa-hourglass-start" aria-hidden="true"></i>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">No spas are on a free trial.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif ($state === 'lapsed')
                {{-- ── Grace and locked spas ───────────────────────────── --}}
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="{{ $thClass }}">Spa</th>
                            <th class="{{ $thClass }}">Status</th>
                            <th class="{{ $thClass }}">Access Ended</th>
                            @if ($canExtend)
                                <th class="{{ $thClass }}">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        @forelse ($spas as $spa)
                            @php
                                $lastPaid = $spa->lastEndedPeriod();
                                $endedAt = $lastPaid?->expires_at ?? $spa->trial_ends_at;
                                $endedWhat = $lastPaid
                                    ? config('plans.' . \App\Models\Spa::normalisePlanKey($lastPaid->business_tier) . '.name', 'Paid') . ' plan'
                                    : 'Free trial';
                            @endphp
                            <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td class="px-6 py-4">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $spa->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $spa->owner?->email }}</p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @include('admin.partials.billing-status', ['spa' => $spa])
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <p class="text-sm text-gray-900 dark:text-white">{{ $endedAt?->format('M d, Y') ?? '—' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $endedWhat }}</p>
                                </td>
                                @if ($canExtend)
                                    <td class="px-6 py-4">
                                        @if ($spa->hasEverPaid())
                                            <span class="text-xs text-gray-500 dark:text-gray-400">Owner renews from Billing</span>
                                        @else
                                            @include('admin.subscriptions.partials.extend-button', ['spa' => $spa, 'extended' => (int) ($spa->extended_days ?? 0)])
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canExtend ? 4 : 3 }}" class="px-6 py-12 text-center">
                                    <i class="mb-3 text-4xl text-gray-400 fa-solid fa-circle-check" aria-hidden="true"></i>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">No spas are in grace or locked.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                {{-- ── Payment rows ────────────────────────────────────── --}}
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="{{ $thClass }}">Spa</th>
                            <th class="{{ $thClass }}">Plan</th>
                            <th class="{{ $thClass }}">Amount</th>
                            <th class="{{ $thClass }}">State</th>
                            <th class="{{ $thClass }}">Period</th>
                            <th class="{{ $thClass }}">Paid On</th>
                            <th class="{{ $thClass }}">PayMongo Reference</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        @forelse ($subscriptions as $subscription)
                            @php
                                $rowPlan = \App\Models\Spa::normalisePlanKey($subscription->business_tier);
                                [$stateLabel, $stateClass] = $stateClasses[$subscription->state()] ?? $stateClasses['failed'];
                                $isPaid = $subscription->isPaid();
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
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $planClasses[$rowPlan] ?? $planClasses['basic'] }}">
                                        {{ config("plans.{$rowPlan}.name", 'Plan') }}
                                    </span>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ ucfirst($subscription->billing_cycle ?? 'monthly') }} · {{ $kindLabels[$subscription->kind] ?? 'Payment' }}
                                    </p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <p class="text-sm text-gray-900 dark:text-white">₱{{ number_format((float) $subscription->amount, 2) }}</p>
                                    @if ((float) $subscription->credit_amount > 0)
                                        <p class="text-xs text-gray-500 dark:text-gray-400">₱{{ number_format((float) $subscription->credit_amount, 2) }} credit</p>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2.5 py-1 text-xs font-medium rounded-full {{ $stateClass }}">{{ $stateLabel }}</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap dark:text-gray-400">
                                    @if ($isPaid && $subscription->starts_at)
                                        {{ $subscription->starts_at->format('M d, Y') }} – {{ $subscription->expires_at?->format('M d, Y') ?? 'No end date' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap dark:text-gray-400">
                                    @if ($isPaid)
                                        {{ ($subscription->paid_at ?? $subscription->starts_at)?->format('M d, Y') ?? '—' }}
                                    @else
                                        <span class="text-xs">Started {{ $subscription->created_at?->format('M d, Y h:i A') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if ($isPaid)
                                        <p class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ $subscription->paymentMethodLabel() }}</p>
                                    @endif
                                    <p class="font-mono text-xs text-gray-500 break-all dark:text-gray-400">
                                        {{ $subscription->paymongo_payment_id ?? $subscription->paymongo_checkout_id ?? '—' }}
                                    </p>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <i class="mb-3 text-4xl text-gray-400 fa-solid fa-receipt" aria-hidden="true"></i>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">No subscriptions found.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        @php $pager = $isSpaTab ? $spas : $subscriptions; @endphp
        @if ($pager->hasPages())
            <div class="px-4 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
                {{ $pager->links() }}
            </div>
        @endif
    </div>

    {{-- ── Trial extension log ───────────────────────────────────────── --}}
    @if ($state === 'trialing')
        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Trial Extension Log</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">The 10 most recent extensions made by admins.</p>
            </div>

            @forelse ($recentExtensions as $extension)
                <div class="flex flex-col gap-1 px-4 py-4 border-b border-gray-200 last:border-b-0 sm:px-6 dark:border-gray-700">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $extension->spa?->name ?? 'Deleted spa' }}
                            <span class="font-normal text-gray-500 dark:text-gray-400">
                                +{{ $extension->days }} day{{ $extension->days === 1 ? '' : 's' }},
                                {{ $extension->previous_ends_at?->format('M d, Y') ?? '—' }} → {{ $extension->new_ends_at->format('M d, Y') }}
                            </span>
                        </p>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $extension->admin?->name ?? 'Removed admin' }} · {{ $extension->created_at?->format('M d, Y h:i A') }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-600 break-words dark:text-gray-300">{{ $extension->reason }}</p>
                </div>
            @empty
                <p class="px-6 py-8 text-sm text-center text-gray-500 dark:text-gray-400">No trials have been extended.</p>
            @endforelse
        </div>
    @endif
</div>

{{-- ── Extend trial modal ────────────────────────────────────────────── --}}
@if ($canExtend && $isSpaTab)
    <div id="extendTrialModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
        <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
            <div role="dialog" aria-modal="true" aria-labelledby="extendTrialTitle"
                 class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">

                <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div>
                        <h2 id="extendTrialTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Extend Free Trial</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            <span id="extendTrialSpa" class="font-medium text-gray-700 dark:text-gray-200"></span>
                            · trial ends <span id="extendTrialEnds"></span>
                        </p>
                    </div>

                    <button type="button" onclick="closeExtendTrial()" aria-label="Close"
                            class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <form id="extendTrialForm" method="POST" action="">
                    @csrf

                    <div class="px-4 py-6 space-y-5 sm:px-6">
                        <div class="flex items-start gap-3 p-3 text-sm border rounded-xl border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-900/10 dark:text-amber-300">
                            <i class="mt-0.5 fa-solid fa-flask" aria-hidden="true"></i>
                            <div class="space-y-1">
                                <p class="font-semibold">It's there as part of a test.</p>
                                <p>
                                    Use it for pilot spas and demo accounts. Only spas that have never paid can be extended,
                                    up to {{ $extensionMaxDays }} days at a time and {{ $extensionMaxTotal }} days in total.
                                    A trial that already ended restarts from today. Every extension is logged with your name and reason.
                                </p>
                            </div>
                        </div>

                        <div>
                            <label for="extendTrialDays" class="{{ $labelClass }}">
                                Days to add <span class="text-red-500">*</span>
                            </label>
                            <input id="extendTrialDays" name="days" type="number" min="1" max="{{ $extensionMaxDays }}" step="1" value="7" required class="{{ $inputClass }}">
                            <p id="extendTrialLeft" class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"></p>
                        </div>

                        <div>
                            <label for="extendTrialReason" class="{{ $labelClass }}">
                                Reason <span class="text-red-500">*</span>
                            </label>
                            <textarea id="extendTrialReason" name="reason" rows="3" minlength="5" maxlength="500" required
                                      class="{{ $inputClass }}" placeholder="e.g. Pilot spa testing for the capstone defense."></textarea>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-2 px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:flex-row sm:justify-end sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                        <button type="button" onclick="closeExtendTrial()" class="w-full {{ $btnNeutral }} sm:w-auto">Cancel</button>
                        <button id="extendTrialSubmit" type="submit" class="w-full {{ $btnPrimary }} sm:w-auto">
                            <i class="text-xs fa-solid fa-calendar-plus" aria-hidden="true"></i>
                            Extend Trial
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function () {
        'use strict';

        const ACTION = @json(route('admin.subscriptions.extend-trial', ['spa' => '__ID__']));
        const modal = document.getElementById('extendTrialModal');
        const form = document.getElementById('extendTrialForm');
        const days = document.getElementById('extendTrialDays');
        const submit = document.getElementById('extendTrialSubmit');
        let opener = null;

        window.openExtendTrial = function (button) {
            const d = button.dataset;
            const left = parseInt(d.left, 10) || 0;

            opener = button;
            form.action = ACTION.replace('__ID__', d.spaId);
            document.getElementById('extendTrialSpa').textContent = d.spaName;
            document.getElementById('extendTrialEnds').textContent = d.trialEnds;
            document.getElementById('extendTrialLeft').textContent =
                left + ' day' + (left === 1 ? '' : 's') + ' left of the ' + @json($extensionMaxTotal) + '-day limit for this spa.';

            days.max = Math.min(left, @json($extensionMaxDays));
            days.value = Math.min(7, days.max);
            document.getElementById('extendTrialReason').value = '';
            submit.disabled = false;

            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            days.focus();
        };

        window.closeExtendTrial = function () {
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            if (opener) opener.focus();
        };

        modal.addEventListener('click', e => { if (e.target === modal || e.target === modal.firstElementChild) closeExtendTrial(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeExtendTrial(); });

        // One submit per click.
        form.addEventListener('submit', () => { submit.disabled = true; });
    })();
    </script>
@endif
@endsection
