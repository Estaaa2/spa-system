@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $canViewSpas = auth()->user()->can('view registered spas');

    $paidPlanCount = $basicCount + $premiumCount;

    $adoptionRate = $totalSpas > 0
        ? round(($paidPlanCount / $totalSpas) * 100)
        : 0;

    $verificationClasses = [
        'verified' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    ];

    $verificationDefault = 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300';

    $cardClass = 'overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800';

    $kpiClass = 'rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5 dark:border-gray-700 dark:bg-gray-800';

    $kpiLabel = 'min-w-0 truncate text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';

    $kpiIconBox = 'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#8B7355]/10 dark:bg-[#C4A97D]/10';

    $kpiIcon = 'text-sm text-[#8B7355] dark:text-[#C4A97D] fa-solid';

    $linkClass = 'text-xs font-semibold text-[#8B7355] transition-colors hover:text-[#6F5430] dark:text-[#C4A97D] dark:hover:text-[#D8C29B]';

    $thClass = 'whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-6 dark:text-gray-400';

    $badgeClass = 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold';
@endphp

<div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">

    <x-page-header
        title="Admin Dashboard"
        subtitle="Platform-wide overview of spas, users, and subscriptions."
    />

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">

        <div class="{{ $kpiClass }}">
            <div class="flex items-center justify-between gap-3">
                <p class="{{ $kpiLabel }}">Spas</p>

                <div class="{{ $kpiIconBox }}">
                    <i class="{{ $kpiIcon }} fa-spa" aria-hidden="true"></i>
                </div>
            </div>

            <p class="mt-4 text-3xl font-bold text-gray-900 dark:text-white">
                {{ number_format($totalSpas) }}
            </p>

            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Registered businesses
            </p>
        </div>

        <div class="{{ $kpiClass }}">
            <div class="flex items-center justify-between gap-3">
                <p class="{{ $kpiLabel }}">Users</p>

                <div class="{{ $kpiIconBox }}">
                    <i class="{{ $kpiIcon }} fa-users" aria-hidden="true"></i>
                </div>
            </div>

            <p class="mt-4 text-3xl font-bold text-gray-900 dark:text-white">
                {{ number_format($totalUsers) }}
            </p>

            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Owners, staff &amp; customers
            </p>
        </div>

        @if ($pendingCount > 0)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm sm:p-5 dark:border-amber-800 dark:bg-amber-900/10">
                <div class="flex items-center justify-between gap-3">
                    <p class="min-w-0 truncate text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400">
                        Pending
                    </p>

                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/30">
                        <i class="text-sm text-amber-600 fa-solid fa-circle-exclamation dark:text-amber-400" aria-hidden="true"></i>
                    </div>
                </div>

                <p class="mt-4 text-3xl font-bold text-amber-900 dark:text-amber-200">
                    {{ number_format($pendingCount) }}
                </p>

                <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">
                    Awaiting verification
                </p>
            </div>
        @else
            <div class="{{ $kpiClass }}">
                <div class="flex items-center justify-between gap-3">
                    <p class="{{ $kpiLabel }}">Pending</p>

                    <div class="{{ $kpiIconBox }}">
                        <i class="{{ $kpiIcon }} fa-circle-check" aria-hidden="true"></i>
                    </div>
                </div>

                <p class="mt-4 text-3xl font-bold text-gray-900 dark:text-white">
                    0
                </p>

                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Awaiting verification
                </p>
            </div>
        @endif

        <div class="{{ $kpiClass }}">
            <div class="flex items-center justify-between gap-3">
                <p class="{{ $kpiLabel }}">Collected</p>

                <div class="{{ $kpiIconBox }}">
                    <i class="{{ $kpiIcon }} fa-peso-sign" aria-hidden="true"></i>
                </div>
            </div>

            <p class="mt-4 text-3xl font-bold text-gray-900 dark:text-white">
                ₱{{ number_format($subscriptionRevenue, 0) }}
            </p>

            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Subscription revenue to date
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">

        {{-- Pending verification --}}
        <div class="lg:col-span-3 {{ $cardClass }}">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-4 py-4 sm:px-6 dark:border-gray-700">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                        Pending Verification
                    </h2>

                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Spas that submitted documents and are waiting for review
                    </p>
                </div>

                @if ($canViewSpas && $pendingCount > 0)
                    <a
                        href="{{ route('admin.registered-spas.index', ['status' => 'pending']) }}"
                        class="{{ $linkClass }} shrink-0"
                    >
                        Review
                        <span aria-hidden="true">→</span>
                    </a>
                @endif
            </div>

            @forelse ($pendingSpas as $spa)
                <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-4 py-4 last:border-b-0 sm:px-6 dark:border-gray-700">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $spa->name }}
                        </p>

                        <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
                            {{ $spa->owner?->email ?? 'No owner on record' }}
                        </p>
                    </div>

                    <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                        {{ $spa->updated_at?->diffForHumans() ?? 'Recently' }}
                    </span>
                </div>
            @empty
                <div class="px-6 py-12 text-center">
                    <i class="mb-3 text-4xl text-gray-400 fa-solid fa-circle-check" aria-hidden="true"></i>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        No spas are waiting for review.
                    </p>
                </div>
            @endforelse

            @if ($pendingCount > $pendingSpas->count())
                <p class="border-t border-gray-200 px-4 py-3 text-xs text-gray-500 sm:px-6 dark:border-gray-700 dark:text-gray-400">
                    Showing {{ $pendingSpas->count() }} of {{ $pendingCount }} pending spas.
                </p>
            @endif
        </div>

        {{-- Plan distribution --}}
        <div class="lg:col-span-2 {{ $cardClass }}">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-4 py-4 sm:px-6 dark:border-gray-700">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                        Plan Distribution
                    </h2>

                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        What each spa is on right now
                    </p>
                </div>

                @if ($canViewSpas)
                    <a
                        href="{{ route('admin.subscriptions.index') }}"
                        class="{{ $linkClass }} shrink-0"
                    >
                        Subscriptions
                        <span aria-hidden="true">→</span>
                    </a>
                @endif
            </div>

            <div class="space-y-6 p-4 sm:p-6">

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:grid-cols-1">
                    <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-900">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Basic
                            </span>

                            <span class="h-2.5 w-2.5 rounded-full bg-gray-400"></span>
                        </div>

                        <p class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ number_format($basicCount) }}
                        </p>

                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Paid, running now
                        </p>
                    </div>

                    <div class="rounded-xl bg-blue-50 p-4 dark:bg-blue-900/20">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">
                                Premium
                            </span>

                            <span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>
                        </div>

                        <p class="mt-3 text-2xl font-bold text-blue-700 dark:text-blue-300">
                            {{ number_format($premiumCount) }}
                        </p>

                        <p class="mt-1 text-xs text-blue-700/80 dark:text-blue-300/80">
                            Paid, running now
                        </p>
                    </div>

                    <div class="rounded-xl bg-[#8B7355]/10 p-4 dark:bg-[#C4A97D]/10">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs font-semibold uppercase tracking-wide text-[#6F5430] dark:text-[#C4A97D]">
                                Free Trial
                            </span>

                            <span class="h-2.5 w-2.5 rounded-full bg-[#8B7355] dark:bg-[#C4A97D]"></span>
                        </div>

                        <p class="mt-3 text-2xl font-bold text-[#6F5430] dark:text-[#C4A97D]">
                            {{ number_format($trialCount) }}
                        </p>

                        <p class="mt-1 text-xs text-[#6F5430]/80 dark:text-[#C4A97D]/80">
                            Full access, not paid yet
                        </p>
                    </div>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between gap-3 text-xs">
                        <span class="font-medium text-gray-700 dark:text-gray-300">
                            Paying spas
                        </span>

                        <span class="font-semibold text-[#6F5430] dark:text-[#C4A97D]">
                            {{ $adoptionRate }}%
                        </span>
                    </div>

                    <div
                        class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
                        role="progressbar"
                        aria-label="Share of spas on a paid plan"
                        aria-valuenow="{{ $adoptionRate }}"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    >
                        <div
                            class="h-full rounded-full bg-[#8B7355] transition-all dark:bg-[#C4A97D]"
                            style="width: {{ $adoptionRate }}%"
                        ></div>
                    </div>

                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        {{ number_format($paidPlanCount) }} of {{ number_format($totalSpas) }} registered spas are on a paid plan right now.
                    </p>
                </div>

                <dl class="space-y-2 border-t border-gray-200 pt-4 text-sm dark:border-gray-700">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">In grace period</dt>
                        <dd class="font-semibold {{ $graceCount > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">
                            {{ number_format($graceCount) }}
                        </dd>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">No plan or locked</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white">
                            {{ number_format($noPlanCount) }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    {{-- Recently registered spas --}}
    <div class="{{ $cardClass }}">
        <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-4 py-4 sm:px-6 dark:border-gray-700">
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    Recently Registered Spas
                </h2>

                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Latest {{ $recentSpas->count() }} registrations
                </p>
            </div>

            @if ($canViewSpas)
                <a
                    href="{{ route('admin.registered-spas.index') }}"
                    class="{{ $linkClass }} shrink-0"
                >
                    View all
                    <span aria-hidden="true">→</span>
                </a>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="{{ $thClass }}">Spa</th>
                        <th class="{{ $thClass }}">Owner</th>
                        <th class="{{ $thClass }}">Plan &amp; Billing</th>
                        <th class="{{ $thClass }}">Verification</th>
                        <th class="{{ $thClass }}">Registered</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                    @forelse ($recentSpas as $spa)
                        @php
                            $verificationStatus = $spa->verification_status ?: 'unverified';
                        @endphp

                        <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="whitespace-nowrap px-4 py-4 sm:px-6">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $spa->name }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ number_format($spa->branches_count) }}
                                    {{ Str::plural('branch', $spa->branches_count) }}
                                </p>
                            </td>

                            <td class="px-4 py-4 sm:px-6">
                                <p class="whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    {{ $spa->owner?->name ?: '—' }}
                                </p>

                                <p class="mt-1 max-w-xs truncate text-xs text-gray-500 dark:text-gray-400">
                                    {{ $spa->owner?->email ?: 'No email' }}
                                </p>
                            </td>

                            <td class="whitespace-nowrap px-4 py-4 sm:px-6">
                                @include('admin.partials.billing-status', ['spa' => $spa])
                            </td>

                            <td class="whitespace-nowrap px-4 py-4 sm:px-6">
                                <span class="{{ $badgeClass }} {{ $verificationClasses[$verificationStatus] ?? $verificationDefault }}">
                                    {{ ucfirst($verificationStatus) }}
                                </span>
                            </td>

                            <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-500 sm:px-6 dark:text-gray-400">
                                @if ($spa->created_at)
                                    <p>{{ $spa->created_at->format('M d, Y') }}</p>
                                    <p class="mt-1 text-xs">
                                        {{ $spa->created_at->diffForHumans() }}
                                    </p>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <i class="mb-3 text-4xl text-gray-400 fa-solid fa-spa" aria-hidden="true"></i>

                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    No spas registered yet.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
