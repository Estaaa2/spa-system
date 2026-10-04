@extends('layouts.app')

@section('title', 'Dashboard')
@section('content')
@php
    $canViewSpas  = auth()->user()->can('view registered spas');
    $adoptionRate = $totalSpas > 0 ? round(($professionalCount / $totalSpas) * 100) : 0;

    // Same verification tones as owner/spa-profile/edit.blade.php.
    $verificationClasses = [
        'verified' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'pending'  => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    ];
    $verificationDefault = 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300';

    $tierClasses = [
        'professional' => 'bg-[#8B7355]/10 text-[#6F5430] dark:bg-[#C4A97D]/10 dark:text-[#C4A97D]',
        'basic'        => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    ];

    $cardClass  = 'overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700';
    $kpiClass   = 'p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700';
    $kpiLabel   = 'min-w-0 text-xs font-semibold tracking-wide text-gray-500 uppercase truncate dark:text-gray-400';
    $kpiIconBox = 'flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-[#8B7355]/10 dark:bg-[#C4A97D]/10';
    $kpiIcon    = 'text-sm text-[#8B7355] dark:text-[#C4A97D] fa-solid';
    $linkClass  = 'text-xs font-medium text-[#8B7355] dark:text-[#C4A97D] hover:text-[#6F5430] dark:hover:text-[#D8C29B] transition-colors';
    $thClass    = 'px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400';
    $badgeClass = 'px-2.5 py-1 text-xs font-medium rounded-full';
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">

    <x-page-header title="Admin Dashboard" subtitle="Platform-wide overview of spas, users, and subscriptions." />

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <div class="{{ $kpiClass }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Spas</p>
                <div class="{{ $kpiIconBox }}"><i class="{{ $kpiIcon }} fa-spa" aria-hidden="true"></i></div>
            </div>
            <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ $totalSpas }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Registered businesses</p>
        </div>

        <div class="{{ $kpiClass }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Users</p>
                <div class="{{ $kpiIconBox }}"><i class="{{ $kpiIcon }} fa-users" aria-hidden="true"></i></div>
            </div>
            <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ $totalUsers }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Owners, staff &amp; customers</p>
        </div>

        @if ($pendingCount > 0)
            <div class="p-4 border shadow-sm sm:p-5 bg-amber-50 border-amber-200 rounded-2xl dark:bg-amber-900/10 dark:border-amber-800">
                <div class="flex items-center justify-between gap-2">
                    <p class="min-w-0 text-xs font-semibold tracking-wide uppercase truncate text-amber-700 dark:text-amber-400">Pending</p>
                    <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-900/30">
                        <i class="text-sm fa-solid fa-circle-exclamation text-amber-600 dark:text-amber-400" aria-hidden="true"></i>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-bold text-amber-900 dark:text-amber-200">{{ $pendingCount }}</p>
                <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">Awaiting verification</p>
            </div>
        @else
            <div class="{{ $kpiClass }}">
                <div class="flex items-center justify-between gap-2">
                    <p class="{{ $kpiLabel }}">Pending</p>
                    <div class="{{ $kpiIconBox }}"><i class="{{ $kpiIcon }} fa-circle-check" aria-hidden="true"></i></div>
                </div>
                <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">0</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Awaiting verification</p>
            </div>
        @endif

        <div class="{{ $kpiClass }}">
            <div class="flex items-center justify-between gap-2">
                <p class="{{ $kpiLabel }}">Collected</p>
                <div class="{{ $kpiIconBox }}"><i class="{{ $kpiIcon }} fa-peso-sign" aria-hidden="true"></i></div>
            </div>
            <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">₱{{ number_format($subscriptionRevenue, 0) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Subscription revenue to date</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">

        {{-- Pending verification --}}
        <div class="lg:col-span-3 {{ $cardClass }}">
            <div class="flex items-center justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Pending Verification</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Spas that submitted documents and are waiting for review</p>
                </div>
                @if ($canViewSpas && $pendingCount > 0)
                    <a href="{{ route('admin.registered-spas.index', ['status' => 'pending']) }}" class="shrink-0 {{ $linkClass }}">Review →</a>
                @endif
            </div>

            @forelse ($pendingSpas as $spa)
                <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-200 last:border-b-0 sm:px-6 dark:border-gray-700">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate dark:text-white">{{ $spa->name }}</p>
                        <p class="text-xs text-gray-500 truncate dark:text-gray-400">{{ $spa->owner?->email ?? 'No owner on record' }}</p>
                    </div>
                    <span class="text-xs text-gray-500 shrink-0 dark:text-gray-400">{{ $spa->updated_at->diffForHumans() }}</span>
                </div>
            @empty
                <div class="px-6 py-12 text-center">
                    <i class="mb-3 text-4xl text-gray-400 fa-solid fa-circle-check" aria-hidden="true"></i>
                    <p class="text-sm text-gray-500 dark:text-gray-400">No spas are waiting for review.</p>
                </div>
            @endforelse

            @if ($pendingCount > $pendingSpas->count())
                <p class="px-4 py-3 text-xs text-gray-500 border-t border-gray-200 sm:px-6 dark:text-gray-400 dark:border-gray-700">
                    Showing {{ $pendingSpas->count() }} of {{ $pendingCount }}.
                </p>
            @endif
        </div>

        {{-- Plans --}}
        <div class="lg:col-span-2 {{ $cardClass }}">
            <div class="flex items-center justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Plans</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">How registered spas are split across tiers</p>
                </div>
                @if ($canViewSpas)
                    <a href="{{ route('admin.subscriptions.index') }}" class="shrink-0 {{ $linkClass }}">Payments →</a>
                @endif
            </div>

            <div class="p-4 space-y-4 sm:p-6">
                <div class="grid grid-cols-2 gap-3">
                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $professionalCount }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Professional</p>
                    </div>
                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $basicCount }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Basic</p>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2 text-xs">
                        <span class="font-medium text-gray-700 dark:text-gray-300">Professional adoption</span>
                        <span class="font-semibold text-[#6F5430] dark:text-[#C4A97D]">{{ $adoptionRate }}%</span>
                    </div>
                    <div class="w-full h-2 bg-gray-200 rounded-full dark:bg-gray-700"
                         role="progressbar" aria-label="Professional adoption"
                         aria-valuenow="{{ $adoptionRate }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="h-2 rounded-full bg-[#8B7355] dark:bg-[#C4A97D]" style="width: {{ $adoptionRate }}%"></div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4 text-sm border-t border-gray-200 dark:border-gray-700">
                    <span class="text-gray-500 dark:text-gray-400">Active paid subscriptions</span>
                    <span class="font-semibold text-gray-900 dark:text-white">{{ $activeSubscriptions }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Recently registered spas --}}
    <div class="{{ $cardClass }}">
        <div class="flex items-center justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Recently Registered Spas</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Latest {{ $recentSpas->count() }} registrations</p>
            </div>
            @if ($canViewSpas)
                <a href="{{ route('admin.registered-spas.index') }}" class="shrink-0 {{ $linkClass }}">View all →</a>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="{{ $thClass }}">Spa</th>
                        <th class="{{ $thClass }}">Owner</th>
                        <th class="{{ $thClass }}">Tier</th>
                        <th class="{{ $thClass }}">Verification</th>
                        <th class="{{ $thClass }}">Subscription</th>
                        <th class="{{ $thClass }}">Registered</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    @forelse ($recentSpas as $spa)
                        @php
                            $sub    = $spa->subscriptions->first();
                            $status = $spa->verification_status ?: 'unverified';
                        @endphp
                        <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $spa->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $spa->branches_count }} {{ Str::plural('branch', $spa->branches_count) }}
                                </p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $spa->owner?->name ?: '—' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $spa->owner?->email }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="{{ $badgeClass }} {{ $tierClasses[$spa->business_tier] ?? $tierClasses['basic'] }}">
                                    {{ ucfirst($spa->business_tier) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="{{ $badgeClass }} {{ $verificationClasses[$status] ?? $verificationDefault }}">
                                    {{ ucfirst($status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                @if ($sub)
                                    <p class="font-medium text-emerald-700 dark:text-emerald-400">Active</p>
                                    <p class="text-xs">Expires {{ $sub->expires_at->format('M d, Y') }}</p>
                                @else
                                    None
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <p>{{ $spa->created_at->format('M d, Y') }}</p>
                                <p class="text-xs">{{ $spa->created_at->diffForHumans() }}</p>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <i class="mb-3 text-4xl text-gray-400 fa-solid fa-spa" aria-hidden="true"></i>
                                <p class="text-sm text-gray-500 dark:text-gray-400">No spas registered yet.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
