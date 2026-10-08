@extends('layouts.app')

@section('title', 'Subscription & Billing')

@section('content')
@php
    $currentPlan = strtolower((string) ($spa->currentPlan() ?: 'basic'));

    if ($currentPlan === 'professional') {
        $currentPlan = 'premium';
    }

    if (! in_array($currentPlan, ['basic', 'premium', 'business'], true)) {
        $currentPlan = 'basic';
    }

    $hasActiveSubscription = $spa->hasActiveSubscription();
    $isTrialing = $spa->isTrialing();
    $hasAccess = $spa->hasAccess();
    $isLocked = $spa->isLocked();

    $planKeys = ['basic', 'premium', 'business'];

    $planStyles = [
        'basic' => [
            'card' => 'border-gray-200 hover:border-gray-400 dark:border-gray-700 dark:hover:border-gray-500',
            'selected' => 'peer-checked:border-gray-500 peer-checked:bg-gray-50 dark:peer-checked:border-gray-400 dark:peer-checked:bg-gray-900',
            'badge' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
            'dot' => 'bg-gray-400',
        ],
        'premium' => [
            'card' => 'border-blue-200 hover:border-blue-400 dark:border-blue-900 dark:hover:border-blue-700',
            'selected' => 'peer-checked:border-blue-500 peer-checked:bg-blue-50 dark:peer-checked:border-blue-400 dark:peer-checked:bg-blue-900/20',
            'badge' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
            'dot' => 'bg-blue-500',
        ],
        'business' => [
            'card' => 'border-purple-200 hover:border-purple-400 dark:border-purple-900 dark:hover:border-purple-700',
            'selected' => 'peer-checked:border-purple-500 peer-checked:bg-purple-50 dark:peer-checked:border-purple-400 dark:peer-checked:bg-purple-900/20',
            'badge' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
            'dot' => 'bg-purple-500',
        ],
    ];

    $featureDescriptions = [
        'operations' => 'Appointments and daily spa operations.',
        'services' => 'Treatments, packages, promotions, and pricing.',
        'this_branch' => 'Manage the selected branch and its settings.',
        'spa_profile' => 'Manage your spa profile and business documents.',
        'billing_page' => 'View subscription and billing information.',
        'insights' => 'Reports and business performance insights.',
        'inventory_basic' => 'Products, batches, stock, and inventory logs.',
        'inventory_full' => 'Transfers, replenishment, and goods receiving.',
        'all_branches' => 'Manage all spa branches.',
        'branch_public_listing' => 'Show your branch publicly to customers.',
        'online_reservation' => 'Accept customer online reservations.',
        'manpower' => 'Staff, hiring, attendance, and deployment.',
        'payroll' => 'Payroll, payslips, and staff pay profiles.',
        'finance' => 'Revenue, expenses, billing, and vendor records.',
        'procurement' => 'Suppliers, purchase requests, and purchase orders.',
    ];

    $statusLabel = $isTrialing
        ? 'Free Trial'
        : ($hasActiveSubscription
            ? 'Active'
            : ($isLocked ? 'Access Locked' : 'Inactive'));

    $statusClass = $isTrialing
        ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'
        : ($hasActiveSubscription
            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
            : ($isLocked
                ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'));
@endphp

<div class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6">

    <x-page-header
        title="Subscription & Billing"
        subtitle="Manage your plan, billing cycle, and subscription history."
    />

    {{-- Current plan --}}
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-200 px-4 py-4 sm:px-6 dark:border-gray-700">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Current plan
                    </p>

                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <h2 class="text-2xl font-bold capitalize text-gray-900 dark:text-white">
                            {{ $currentPlan }}
                        </h2>

                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">
                            {{ $statusLabel }}
                        </span>
                    </div>
                </div>

                <span class="inline-flex w-fit items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold {{ $planStyles[$currentPlan]['badge'] }}">
                    <span class="h-2 w-2 rounded-full {{ $planStyles[$currentPlan]['dot'] }}"></span>
                    {{ ucfirst($currentPlan) }} plan
                </span>
            </div>
        </div>

        <div class="px-4 py-4 sm:px-6">
            @if ($isTrialing)
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Your free trial ends in
                    <strong class="text-gray-900 dark:text-white">
                        {{ $spa->trialDaysLeft() }} day(s)
                    </strong>.
                </p>
            @elseif ($hasActiveSubscription && $subscription?->expires_at)
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Your subscription is active until
                    <strong class="text-gray-900 dark:text-white">
                        {{ $subscription->expires_at->format('F d, Y') }}
                    </strong>.
                </p>
            @elseif ($isLocked)
                <p class="text-sm text-red-700 dark:text-red-300">
                    Your subscription has expired. Choose a plan below to restore access.
                </p>
            @else
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Choose a plan below to activate access to the system.
                </p>
            @endif
        </div>
    </section>

    {{-- Plan selection --}}
    @if (! $hasActiveSubscription && ! $isTrialing)
        <form
            id="subscriptionCheckoutForm"
            action="{{ route('owner.subscription.checkout') }}"
            method="POST"
        >
            @csrf

            <input
                id="billingCycleInput"
                type="hidden"
                name="billing_cycle"
                value="monthly"
            >

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-4 py-4 sm:px-6 dark:border-gray-700">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Choose your plan
                            </h2>

                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Select a plan and billing cycle before continuing to payment.
                            </p>
                        </div>

                        <div class="w-full sm:w-auto">
                            <label
                                for="billingCycleSelector"
                                class="mb-1.5 block text-xs font-semibold text-gray-600 dark:text-gray-300"
                            >
                                Billing cycle
                            </label>

                            <select
                                id="billingCycleSelector"
                                class="w-full rounded-xl border-gray-300 text-sm focus:border-[#8B7355] focus:ring-[#8B7355] sm:w-48 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="monthly">Monthly billing</option>
                                <option value="yearly">Yearly billing</option>
                            </select>
                        </div>
                    </div>

                    @error('plan')
                        <p class="mt-4 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">
                            {{ $message }}
                        </p>
                    @enderror

                    @error('billing_cycle')
                        <p class="mt-2 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="grid gap-4 p-4 sm:p-6 md:grid-cols-3">
                    @foreach ($planKeys as $planKey)
                        @php
                            $plan = $plans[$planKey] ?? null;

                            if (! is_array($plan)) {
                                continue;
                            }

                            $styles = $planStyles[$planKey];

                            $monthlyPrice = (float) ($plan['monthly_price'] ?? 0);
                            $yearlyPrice = (float) ($plan['yearly_price'] ?? 0);
                            $monthlyYearlyCost = $monthlyPrice * 12;
                            $yearlySavings = max(0, $monthlyYearlyCost - $yearlyPrice);
                        @endphp

                        <label class="relative block h-full cursor-pointer">
                            <input
                                type="radio"
                                name="plan"
                                value="{{ $planKey }}"
                                class="plan-option peer sr-only"
                                required
                            >

                            <div class="flex h-full flex-col rounded-2xl border-2 p-5 transition {{ $styles['card'] }} {{ $styles['selected'] }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <span class="mb-3 inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-semibold {{ $styles['badge'] }}">
                                            <span class="h-2 w-2 rounded-full {{ $styles['dot'] }}"></span>
                                            {{ ucfirst($planKey) }}
                                        </span>

                                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                                            {{ $plan['name'] ?? ucfirst($planKey) }}
                                        </h3>

                                        <p class="mt-2 text-xs leading-5 text-gray-500 dark:text-gray-400">
                                            Up to {{ $plan['max_branches'] ?? '—' }} branch(es)
                                            and {{ $plan['max_staff'] ?? '—' }} staff
                                        </p>
                                    </div>

                                    <span class="hidden text-xl text-[#8B7355] peer-checked:block dark:text-[#C4A97D]">
                                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                    </span>
                                </div>

                                <div class="mt-6">
                                    <p class="text-3xl font-bold text-gray-900 dark:text-white">
                                        ₱<span
                                            class="plan-price"
                                            data-monthly="{{ number_format($monthlyPrice, 0) }}"
                                            data-yearly="{{ number_format($yearlyPrice, 0) }}"
                                        >{{ number_format($monthlyPrice, 0) }}</span>

                                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                            <span class="plan-period">/month</span>
                                        </span>
                                    </p>

                                    <p class="yearly-reference mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        ₱{{ number_format($yearlyPrice, 0) }} per year
                                    </p>

                                    <p
                                        class="yearly-savings mt-2 hidden text-xs font-semibold text-emerald-600 dark:text-emerald-400"
                                        data-savings="{{ number_format($yearlySavings, 0) }}"
                                    >
                                        Save ₱{{ number_format($yearlySavings, 0) }} per year
                                    </p>
                                </div>

                                <div class="mt-6 flex-1 border-t border-gray-200 pt-5 dark:border-gray-700">
                                    <p class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">
                                        Included features
                                    </p>

                                    <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
                                        @foreach (($plan['features'] ?? []) as $feature)
                                            <li class="flex items-start gap-2">
                                                <i class="mt-0.5 text-emerald-600 fa-solid fa-check dark:text-emerald-400" aria-hidden="true"></i>

                                                <span class="leading-5">
                                                    {{ $featureDescriptions[$feature] ?? \Illuminate\Support\Str::of($feature)->replace('_', ' ')->title() }}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>

                                <p class="mt-6 text-xs text-gray-500 dark:text-gray-400">
                                    {{ count($plan['features'] ?? []) }}
                                    included feature{{ count($plan['features'] ?? []) === 1 ? '' : 's' }}
                                </p>
                            </div>
                        </label>
                    @endforeach
                </div>

                <div class="border-t border-gray-200 bg-gray-50 px-4 py-4 sm:px-6 dark:border-gray-700 dark:bg-gray-900/40">
                    <button
                        type="submit"
                        class="min-h-[48px] w-full rounded-xl bg-[#8B7355] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#7A6348] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800"
                    >
                        Continue to Payment
                    </button>
                </div>
            </section>
        </form>
    @endif

    {{-- Billing history --}}
    @if ($subscriptionHistory->isNotEmpty())
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <details class="group">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-4 sm:px-6">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                            Billing History
                        </h2>

                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            View previous subscription payments and receipts.
                        </p>
                    </div>

                    <i class="text-gray-500 transition-transform fa-solid fa-chevron-down group-open:rotate-180 dark:text-gray-400" aria-hidden="true"></i>
                </summary>

                <div class="space-y-3 border-t border-gray-200 p-4 sm:p-6 dark:border-gray-700">
                    @foreach ($subscriptionHistory as $history)
                        @php
                            $historyPlan = strtolower((string) ($history->business_tier ?: 'basic'));

                            if ($historyPlan === 'professional') {
                                $historyPlan = 'premium';
                            }

                            if (! in_array($historyPlan, $planKeys, true)) {
                                $historyPlan = 'basic';
                            }

                            $historyStyles = $planStyles[$historyPlan];

                            $historyStatus = $history->status ?: $history->payment_status ?: 'unknown';

                            $historyStatusClass = $historyStatus === 'active'
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                : ($historyStatus === 'cancelled'
                                    ? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
                                    : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300');
                        @endphp

                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-semibold {{ $historyStyles['badge'] }}">
                                            <span class="h-2 w-2 rounded-full {{ $historyStyles['dot'] }}"></span>
                                            {{ ucfirst($historyPlan) }}
                                        </span>

                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ ucfirst($history->billing_cycle ?? 'monthly') }} billing
                                        </span>

                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $historyStatusClass }}">
                                            {{ ucfirst($historyStatus) }}
                                        </span>
                                    </div>

                                    <div class="mt-3 grid gap-2 text-sm text-gray-500 sm:grid-cols-3 dark:text-gray-400">
                                        <p>
                                            Amount:
                                            <span class="font-semibold text-gray-700 dark:text-gray-300">
                                                ₱{{ number_format((float) $history->amount, 2) }}
                                            </span>
                                        </p>

                                        <p>
                                            Paid:
                                            <span class="font-semibold text-gray-700 dark:text-gray-300">
                                                {{ $history->starts_at?->format('F d, Y') ?? $history->created_at?->format('F d, Y') }}
                                            </span>
                                        </p>

                                        @if ($history->expires_at)
                                            <p>
                                                Valid until:
                                                <span class="font-semibold text-gray-700 dark:text-gray-300">
                                                    {{ $history->expires_at->format('F d, Y') }}
                                                </span>
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                @if ($history->payment_status === 'paid' || $history->status === 'active')
                                    <a
                                        href="{{ route('owner.subscription.receipt', $history->id) }}"
                                        class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-[#8B7355] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#7A6348]"
                                    >
                                        <i class="fa-solid fa-download" aria-hidden="true"></i>
                                        Download Receipt
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </details>
        </section>
    @endif

    {{-- Cancellation --}}
    @if ($hasActiveSubscription && $subscription)
        <section class="rounded-2xl border border-red-200 bg-red-50 p-4 shadow-sm sm:p-5 dark:border-red-800 dark:bg-red-900/10">
            <h2 class="text-base font-semibold text-red-900 dark:text-red-200">
                Cancel Subscription
            </h2>

            <p class="mt-2 text-sm leading-6 text-red-700 dark:text-red-300">
                Cancellation stops the next renewal. Your current plan remains available until the end of the current billing period.
            </p>

            @if ($subscription->expires_at)
                <p class="mt-2 text-sm text-red-700 dark:text-red-300">
                    Current access ends on
                    <strong>
                        {{ $subscription->expires_at->format('F d, Y') }}
                    </strong>.
                </p>
            @endif

            <form
                action="{{ route('owner.subscription.cancel-subscription') }}"
                method="POST"
                class="mt-4"
                onsubmit="return confirm('Cancel the next renewal for this subscription?');"
            >
                @csrf

                <button
                    type="submit"
                    class="min-h-[44px] rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900"
                >
                    Cancel Next Renewal
                </button>
            </form>
        </section>
    @endif

</div>

@if (! $hasActiveSubscription && ! $isTrialing)
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selector = document.getElementById('billingCycleSelector');
            const hiddenInput = document.getElementById('billingCycleInput');

            if (!selector || !hiddenInput) {
                return;
            }

            function updatePlanPrices() {
                const cycle = selector.value;

                hiddenInput.value = cycle;

                document.querySelectorAll('.plan-price').forEach(function (price) {
                    price.textContent = cycle === 'yearly'
                        ? price.dataset.yearly
                        : price.dataset.monthly;
                });

                document.querySelectorAll('.plan-period').forEach(function (period) {
                    period.textContent = cycle === 'yearly'
                        ? '/year'
                        : '/month';
                });

                document.querySelectorAll('.yearly-reference').forEach(function (reference) {
                    reference.classList.toggle('hidden', cycle === 'yearly');
                });

                document.querySelectorAll('.yearly-savings').forEach(function (savings) {
                    savings.classList.toggle('hidden', cycle !== 'yearly');
                });
            }

            selector.addEventListener('change', updatePlanPrices);
            updatePlanPrices();
        });
    </script>
@endif
@endsection
