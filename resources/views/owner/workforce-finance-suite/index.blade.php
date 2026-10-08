@extends('layouts.app')

@section('title', 'Workforce & Finance')

@section('content')
@php
    $currentPlan = strtolower((string) ($spa->currentPlan() ?: 'basic'));

    if ($currentPlan === 'professional') {
        $currentPlan = 'premium';
    }

    if (! in_array($currentPlan, ['basic', 'premium', 'business'], true)) {
        $currentPlan = 'basic';
    }

    $hasSuiteAccess = $spa->hasAccess()
        && $spa->hasFeature('manpower')
        && $spa->hasFeature('finance');

    $planBadgeClasses = [
        'basic' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        'premium' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
        'business' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
    ];

    $planBadgeClass = $planBadgeClasses[$currentPlan];
@endphp

<div class="max-w-5xl p-4 mx-auto space-y-6 sm:p-6">

    <x-page-header
        title="Workforce & Finance"
        subtitle="Manage branch access to advanced workforce and finance tools."
    />

    @if (session('success'))
        <div class="p-4 text-sm border rounded-2xl border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="p-4 text-sm text-red-800 border border-red-200 rounded-2xl bg-red-50 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    @if (! $hasSuiteAccess)
        <section class="p-6 border border-purple-200 shadow-sm rounded-2xl bg-purple-50 dark:border-purple-800 dark:bg-purple-900/10">
            <div class="flex items-start gap-4">
                <div class="flex items-center justify-center w-10 h-10 text-purple-700 bg-purple-100 shrink-0 rounded-xl dark:bg-purple-900/40 dark:text-purple-300">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-lg font-semibold text-purple-950 dark:text-purple-200">
                            Business plan feature
                        </h2>

                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $planBadgeClass }}">
                            {{ ucfirst($currentPlan) }}
                        </span>
                    </div>

                    <p class="mt-2 text-sm leading-6 text-purple-800 dark:text-purple-300">
                        Workforce and Finance access requires an active Business plan.
                        This includes manpower, payroll, finance, and procurement tools.
                    </p>

                    @if (Route::has('owner.subscription.index'))
                        <a
                            href="{{ route('owner.subscription.index') }}"
                            class="mt-5 inline-flex min-h-[44px] items-center gap-2 rounded-xl bg-[#8B7355] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#7A6348]"
                        >
                            <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
                            View Subscription Plans
                        </a>
                    @endif
                </div>
            </div>
        </section>
    @else
        <form
            method="POST"
            action="{{ route('owner.workforce-finance-suite.update') }}"
            class="space-y-6"
        >
            @csrf
            @method('PUT')

            <section class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-800">
                <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Business Features
                            </h2>

                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Your Business plan includes advanced workforce and finance capabilities.
                            </p>
                        </div>

                        <span class="inline-flex w-fit items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold {{ $planBadgeClass }}">
                            <span class="w-2 h-2 bg-purple-500 rounded-full"></span>
                            Business enabled
                        </span>
                    </div>
                </div>

                <div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-6 lg:grid-cols-4">
                    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
                        <div class="flex items-center justify-center text-purple-700 bg-purple-100 h-9 w-9 rounded-xl dark:bg-purple-900/30 dark:text-purple-300">
                            <i class="fa-solid fa-users" aria-hidden="true"></i>
                        </div>

                        <h3 class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">
                            People
                        </h3>

                        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">
                            Staff accounts, hiring, applicants, and interviews.
                        </p>
                    </div>

                    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
                        <div class="flex items-center justify-center text-purple-700 bg-purple-100 h-9 w-9 rounded-xl dark:bg-purple-900/30 dark:text-purple-300">
                            <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                        </div>

                        <h3 class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">
                            Attendance &amp; Leave
                        </h3>

                        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">
                            Attendance tracking and leave workflows.
                        </p>
                    </div>

                    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
                        <div class="flex items-center justify-center text-purple-700 bg-purple-100 h-9 w-9 rounded-xl dark:bg-purple-900/30 dark:text-purple-300">
                            <i class="fa-solid fa-money-bill-transfer" aria-hidden="true"></i>
                        </div>

                        <h3 class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">
                            Finance
                        </h3>

                        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">
                            Payroll, revenue, billing, vendors, and procurement.
                        </p>
                    </div>

                    <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
                        <div class="flex items-center justify-center text-purple-700 bg-purple-100 h-9 w-9 rounded-xl dark:bg-purple-900/30 dark:text-purple-300">
                            <i class="fa-solid fa-code-branch" aria-hidden="true"></i>
                        </div>

                        <h3 class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">
                            Branch access
                        </h3>

                        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">
                            Turn these tools on only for the branches that need them.
                        </p>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-800">
                <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Branch Access
                            </h2>

                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Choose which branches can use Workforce &amp; Finance tools.
                            </p>
                        </div>

                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $branches->count() }}
                            {{ \Illuminate\Support\Str::plural('branch', $branches->count()) }}
                        </span>
                    </div>
                </div>

                <div class="p-4 space-y-4 sm:p-6">
                    @forelse ($branches as $branch)
                        @php
                            $suiteEnabled = (bool) $branch->has_workforce_finance_suite;
                        @endphp

                        <div class="flex flex-col gap-4 p-4 border border-gray-200 rounded-xl dark:border-gray-700 lg:flex-row lg:items-center lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                        {{ $branch->name }}
                                    </h3>

                                    @if ($branch->is_main)
                                        <span class="inline-flex rounded-full bg-[#8B7355]/10 px-2.5 py-1 text-xs font-semibold text-[#6F5430] dark:bg-[#C4A97D]/10 dark:text-[#D2A85B]">
                                            Main Branch
                                        </span>
                                    @endif

                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $suiteEnabled
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                        : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $suiteEnabled ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                                        {{ $suiteEnabled ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </div>

                                @if ($branch->location)
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        <i class="mr-1 text-xs fa-solid fa-location-dot" aria-hidden="true"></i>
                                        {{ $branch->location }}
                                    </p>
                                @endif

                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    Enable advanced workforce and finance modules for this branch.
                                </p>
                            </div>

                            <div class="shrink-0">
                                <input
                                    type="hidden"
                                    name="branches[{{ $branch->id }}]"
                                    value="0"
                                >

                                <label class="inline-flex min-h-[44px] cursor-pointer items-center gap-3">
                                    <input
                                        type="checkbox"
                                        name="branches[{{ $branch->id }}]"
                                        value="1"
                                        @checked(old("branches.{$branch->id}", $suiteEnabled))
                                        class="h-4 w-4 rounded border-gray-300 text-[#8B7355] focus:ring-[#8B7355] dark:border-gray-600 dark:bg-gray-700"
                                    >

                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                        Enable for this branch
                                    </span>
                                </label>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-sm text-center text-gray-500 border border-gray-300 border-dashed rounded-xl dark:border-gray-600 dark:text-gray-400">
                            No branches found for this spa.
                        </div>
                    @endforelse
                </div>

                <div class="flex flex-col-reverse gap-2 px-4 py-4 border-t border-gray-200 bg-gray-50 sm:flex-row sm:justify-end sm:px-6 dark:border-gray-700 dark:bg-gray-900/40">
                    <a
                        href="{{ route('dashboard') }}"
                        class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-[#8B7355] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#7A6348]"
                    >
                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                        Save Branch Access
                    </button>
                </div>
            </section>
        </form>
    @endif

</div>
@endsection
