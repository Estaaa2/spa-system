@extends('layouts.app')

@section('title', 'Subscription & Billing')

@section('content')
@php
    $isTrialing = $spa->isTrialing();
    $isInGrace = $spa->isInGracePeriod();
    $isLocked = $spa->isLocked();
    $canStartTrial = $spa->canStartTrial();
    $currentPlan = $spa->currentPlan(); // basic | premium | expired
    $graceEndsAt = $spa->graceEndsAt();
    $lastEnded = $spa->lastEndedPeriod();
    $nextPeriod = $scheduledPeriods->first();

    // One state drives the status card.
    $state = match (true) {
        $isTrialing => 'trial',
        $subscription !== null => 'active',
        $isInGrace => 'grace',
        $canStartTrial => 'new',
        default => 'locked',
    };

    $stateBadge = [
        'trial' => ['Free Trial', 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'],
        'active' => ['Active', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
        'grace' => ['Grace Period', 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'],
        'new' => ['No Plan Yet', 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'],
        'locked' => ['Access Locked', 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'],
    ][$state];

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
    ];
    $neutralStyle = $planStyles['basic'];
    $headerPlan = $currentPlan === 'expired' ? null : $currentPlan;

    $featureLabels = config('subscription.feature_labels', []);
    $alwaysIncluded = config('subscription.always_included', []);
    $basicFeatures = $plans['basic']['features'] ?? [];

    $methodLabels = collect(config('subscription.payment_methods', []))
        ->map(fn ($m) => config("subscription.payment_method_labels.{$m}", ucfirst($m)))
        ->implode(', ');

    $fmt = fn ($date) => $date?->format('F d, Y');

    $actionLabels = [
        'new' => 'Start plan',
        'renewal' => 'Renew',
        'upgrade' => 'Upgrade',
        'downgrade' => 'Switch at next period',
    ];

    // Everything the summary panel needs, per plan and cycle.
    $quoteData = [];
    foreach ($quotes as $planKey => $byCycle) {
        foreach ($byCycle as $cycle => $quote) {
            $action = $actionLabels[$quote->kind] ?? 'Pay';

            if ($quote->kind === 'upgrade' && $quote->credit > 0) {
                $action = 'Upgrade now';
            } elseif ($quote->kind === 'renewal' && $quote->isScheduled()) {
                $action = 'Pay next period early';
            }

            $quoteData[$planKey][$cycle] = [
                'allowed' => $quote->allowed,
                'message' => $quote->message,
                'kind' => $quote->kind,
                'action' => $action,
                'listPrice' => number_format($quote->listPrice, 2),
                'credit' => $quote->credit > 0 ? number_format($quote->credit, 2) : null,
                'amount' => number_format($quote->amount, 2),
                'startsAt' => $fmt($quote->startsAt),
                'expiresAt' => $fmt($quote->expiresAt),
                'scheduled' => $quote->isScheduled(),
            ];
        }
    }

    // Preselect the plan the owner is on, so "renew" is one click.
    $preselectPlan = old('plan', in_array($currentPlan, array_keys($plans), true) ? $currentPlan : null);
    $preselectCycle = old('billing_cycle', $subscription?->billing_cycle ?? 'monthly');

    $historyStates = [
        'active' => ['Active', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
        'scheduled' => ['Scheduled', 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'],
        'ended' => ['Ended', 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'],
        'credited' => ['Credited to upgrade', 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'],
        'pending' => ['Awaiting payment', 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'],
        'failed' => ['Not completed', 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'],
        'expired' => ['Not completed', 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'],
    ];

    $kindLabels = [
        'new' => 'New plan',
        'renewal' => 'Renewal',
        'upgrade' => 'Upgrade',
        'downgrade' => 'Plan change',
    ];

    $btnPrimary = 'inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl bg-[#8B7355] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#7A6348] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-offset-gray-800';
@endphp

<div class="max-w-6xl p-4 mx-auto space-y-6 sm:p-6">

    <x-page-header
        title="Subscription & Billing"
        subtitle="Manage your plan, pay for your next period, and download receipts."
    />

    {{-- ── Current status ─────────────────────────────────────────────── --}}
    <section class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-800">
        <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                        Current plan
                    </p>

                    <div class="flex flex-wrap items-center gap-3 mt-2">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ $headerPlan ? ucfirst($headerPlan) : 'None' }}
                        </h2>

                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $stateBadge[1] }}">
                            {{ $stateBadge[0] }}
                        </span>
                    </div>
                </div>

                @if ($headerPlan)
                    <span class="inline-flex w-fit items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold {{ $planStyles[$headerPlan]['badge'] }}">
                        <span class="h-2 w-2 rounded-full {{ $planStyles[$headerPlan]['dot'] }}"></span>
                        {{ config("plans.{$headerPlan}.max_branches") }} branches · {{ config("plans.{$headerPlan}.max_staff") }} staff
                    </span>
                @endif
            </div>
        </div>

        <div class="px-4 py-4 space-y-3 text-sm sm:px-6">
            @if ($state === 'trial')
                <p class="text-gray-600 dark:text-gray-300">
                    Your free trial gives full Premium access until
                    <strong class="text-gray-900 dark:text-white">{{ $fmt($spa->trial_ends_at) }}</strong>
                    ({{ $spa->trialDaysLeft() }} day{{ $spa->trialDaysLeft() === 1 ? '' : 's' }} left).
                </p>
                @unless ($nextPeriod)
                    <p class="text-gray-600 dark:text-gray-300">
                        Choose a plan below any time. Paying now does not shorten your trial: your paid plan starts when the trial ends.
                    </p>
                @endunless
            @elseif ($state === 'active')
                <p class="text-gray-600 dark:text-gray-300">
                    Current period:
                    <strong class="text-gray-900 dark:text-white">
                        {{ $fmt($subscription->starts_at) }} – {{ $fmt($subscription->expires_at) }}
                    </strong>
                    ({{ ucfirst($subscription->billing_cycle) }}).
                </p>
            @elseif ($state === 'grace')
                <div class="flex items-start gap-3 p-3 border rounded-xl border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-900/10 dark:text-amber-300">
                    <i class="mt-0.5 fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    <p>
                        Your {{ ucfirst(\App\Models\Spa::normalisePlanKey($lastEnded?->business_tier)) }} plan ended on
                        <strong>{{ $fmt($lastEnded?->expires_at) }}</strong>.
                        Access continues until <strong>{{ $fmt($graceEndsAt) }}</strong>, then your spa, staff accounts and public listing are locked.
                        Pay below to keep everything running.
                    </p>
                </div>
            @elseif ($state === 'new')
                <p class="text-gray-600 dark:text-gray-300">
                    Your spa has a free one-month Premium trial available.
                    <a href="{{ route('owner.trial.choose') }}" class="font-semibold text-[#8B7355] underline hover:text-[#6F5430] dark:text-[#C4A97D]">Start your free trial</a>
                    or choose a paid plan below.
                </p>
            @else
                <div class="flex items-start gap-3 p-3 text-red-700 border border-red-200 rounded-xl bg-red-50 dark:border-red-800 dark:bg-red-900/10 dark:text-red-300">
                    <i class="mt-0.5 fa-solid fa-lock" aria-hidden="true"></i>
                    <p>
                        Your spa is locked: staff cannot sign in and your branches are hidden from customers.
                        Your data is kept. Choose a plan below to restore access right away.
                    </p>
                </div>
            @endif

            @if ($nextPeriod)
                <div class="flex items-start gap-3 p-3 text-blue-800 border border-blue-200 rounded-xl bg-blue-50 dark:border-blue-800 dark:bg-blue-900/10 dark:text-blue-300">
                    <i class="mt-0.5 fa-solid fa-calendar-check" aria-hidden="true"></i>
                    <p>
                        Next period paid:
                        <strong>{{ ucfirst(\App\Models\Spa::normalisePlanKey($nextPeriod->business_tier)) }} {{ ucfirst($nextPeriod->billing_cycle) }}</strong>,
                        {{ $fmt($nextPeriod->starts_at) }} – {{ $fmt($nextPeriod->expires_at) }}.
                    </p>
                </div>
            @endif

            @if (! $isLocked && $spa->isOverPlanLimits())
                <div class="flex flex-col gap-3 p-3 border sm:flex-row sm:items-center sm:justify-between rounded-xl border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-900/10 dark:text-amber-300">
                    <p class="flex items-start gap-3">
                        <i class="mt-0.5 fa-solid fa-user-clock" aria-hidden="true"></i>
                        <span>Some branches or staff are paused because your spa has more than this plan allows. Nothing was deleted.</span>
                    </p>
                    <a href="{{ route('owner.subscription.limits') }}" class="shrink-0 font-semibold underline hover:no-underline">
                        Choose what stays active
                    </a>
                </div>
            @endif

            @if ($paidThroughAt && $paidThroughAt->isFuture())
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Paid access runs until {{ $fmt($paidThroughAt) }}. Plans do not renew automatically, and nothing is charged unless you pay below.
                </p>
            @endif
        </div>
    </section>

    {{-- ── Customer bookings affected by the lock ─────────────────────── --}}
    @if ($affectedBookingCount > 0)
        <section class="overflow-hidden bg-white border shadow-sm border-amber-200 rounded-2xl dark:border-amber-800 dark:bg-gray-800">
            <div class="flex flex-col gap-3 px-4 py-4 border-b sm:flex-row sm:items-start sm:justify-between sm:px-6 border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/10">
                <div class="flex items-start gap-3 text-amber-800 dark:text-amber-300">
                    <i class="mt-0.5 fa-solid fa-calendar-xmark" aria-hidden="true"></i>
                    <div>
                        <h2 class="text-base font-semibold">
                            {{ $affectedBookingCount }} upcoming customer booking{{ $affectedBookingCount === 1 ? '' : 's' }}
                            {{ $state === 'grace' ? 'after your spa locks' : 'while your spa is locked' }}
                        </h2>
                        <p class="mt-1 text-sm">
                            @if ($state === 'grace')
                                These fall on or after {{ $fmt($lockDate) }}. After that, your staff can't sign in to serve them.
                            @else
                                Your staff can't sign in to serve them, and customers can't make new online bookings.
                            @endif
                            The bookings are not cancelled and customers are not notified by Levictas. Renew, or contact these customers yourself.
                        </p>
                    </div>
                </div>

                <a href="{{ route('owner.subscription.affected-bookings') }}"
                   class="inline-flex shrink-0 min-h-[44px] items-center justify-center gap-2 rounded-xl border border-[#8B7355] bg-white px-4 py-2 text-sm font-semibold text-[#8B7355] transition hover:bg-[#8B7355]/5 dark:border-[#C4A97D] dark:bg-gray-800 dark:text-[#C4A97D]">
                    <i class="fa-solid fa-file-csv" aria-hidden="true"></i>
                    Download list (CSV)
                </a>
            </div>

            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($affectedBookings as $booking)
                    <li class="flex flex-col gap-1 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 truncate dark:text-white">{{ $booking->customer_name ?: 'Walk-in customer' }}</p>
                            <p class="text-xs text-gray-500 truncate dark:text-gray-400">
                                {{ $booking->branch?->name }}
                                @if ($booking->customer_phone) · {{ $booking->customer_phone }} @endif
                            </p>
                        </div>
                        <p class="text-xs font-medium text-gray-700 shrink-0 dark:text-gray-300">
                            {{ $booking->appointment_date?->format('M d, Y') }}
                            · {{ \Illuminate\Support\Carbon::parse($booking->start_time)->format('h:i A') }}
                        </p>
                    </li>
                @endforeach
            </ul>

            @if ($affectedBookingCount > $affectedBookings->count())
                <p class="px-4 py-3 text-xs text-gray-500 border-t border-gray-200 sm:px-6 dark:border-gray-700 dark:text-gray-400">
                    Showing the first {{ $affectedBookings->count() }}. Download the list to see all {{ $affectedBookingCount }}.
                </p>
            @endif
        </section>
    @endif

    {{-- ── Plan selection ─────────────────────────────────────────────── --}}
    <form id="subscriptionCheckoutForm" action="{{ route('owner.subscription.checkout') }}" method="POST">
        @csrf

        <input id="billingCycleInput" type="hidden" name="billing_cycle" value="{{ $preselectCycle }}">

        <section class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-800">
            <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $state === 'active' || $state === 'trial' ? 'Renew or change your plan' : 'Choose your plan' }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Prepaid. Paying early adds a new period after your current one, so you never lose days.
                        </p>
                    </div>

                    <div class="w-full sm:w-auto">
                        <label for="billingCycleSelector" class="mb-1.5 block text-xs font-semibold text-gray-600 dark:text-gray-300">
                            Billing cycle
                        </label>

                        <select
                            id="billingCycleSelector"
                            class="w-full text-sm border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-[#8B7355] sm:w-48 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="monthly" @selected($preselectCycle === 'monthly')>Monthly billing</option>
                            <option value="yearly" @selected($preselectCycle === 'yearly')>Yearly billing (2 months free)</option>
                        </select>
                    </div>
                </div>

                @error('payment')
                    <p class="px-3 py-2 mt-4 text-sm text-red-700 rounded-xl bg-red-50 dark:bg-red-900/20 dark:text-red-300" role="alert">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="grid gap-4 p-4 sm:p-6 md:grid-cols-2">
                @foreach ($plans as $planKey => $plan)
                    @php
                        $styles = $planStyles[$planKey] ?? $neutralStyle;
                        $monthlyPrice = (float) ($plan['monthly_price'] ?? 0);
                        $yearlyPrice = (float) ($plan['yearly_price'] ?? 0);
                        $yearlySavings = max(0, $monthlyPrice * 12 - $yearlyPrice);

                        $isBasicCard = $planKey === 'basic';
                        $shownFeatures = $isBasicCard
                            ? $plan['features'] ?? []
                            : array_values(array_diff($plan['features'] ?? [], $basicFeatures));

                        $isCurrentCard = $headerPlan === $planKey && ($state === 'active' || $state === 'grace');
                        $isTrialCard = $state === 'trial' && $planKey === $spa->trialPlan();
                    @endphp

                    <label class="relative block h-full cursor-pointer">
                        <input
                            type="radio"
                            name="plan"
                            value="{{ $planKey }}"
                            class="sr-only plan-option peer"
                            @checked($preselectPlan === $planKey)
                            required
                        >

                        <div class="flex h-full flex-col rounded-2xl border-2 p-5 transition {{ $styles['card'] }} {{ $styles['selected'] }}">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2 mb-3">
                                        <span class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-semibold {{ $styles['badge'] }}">
                                            <span class="h-2 w-2 rounded-full {{ $styles['dot'] }}"></span>
                                            {{ $plan['name'] }}
                                        </span>

                                        @if ($isCurrentCard)
                                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                                Current plan
                                            </span>
                                        @elseif ($isTrialCard)
                                            <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                                                Your trial
                                            </span>
                                        @endif
                                    </div>

                                    <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">
                                        Up to {{ $plan['max_branches'] }} branches and {{ $plan['max_staff'] }} active staff
                                    </p>
                                </div>
                            </div>

                            <div class="mt-5">
                                <p class="text-3xl font-bold text-gray-900 dark:text-white">
                                    ₱<span
                                        class="plan-price"
                                        data-monthly="{{ number_format($monthlyPrice, 0) }}"
                                        data-yearly="{{ number_format($yearlyPrice, 0) }}"
                                    >{{ number_format($preselectCycle === 'yearly' ? $yearlyPrice : $monthlyPrice, 0) }}</span>
                                    <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                        <span class="plan-period">{{ $preselectCycle === 'yearly' ? '/year' : '/month' }}</span>
                                    </span>
                                </p>

                                <p class="mt-2 text-xs font-semibold yearly-savings text-emerald-600 dark:text-emerald-400 {{ $preselectCycle === 'yearly' ? '' : 'hidden' }}">
                                    Save ₱{{ number_format($yearlySavings, 0) }} a year
                                </p>
                            </div>

                            <div class="flex-1 pt-5 mt-5 border-t border-gray-200 dark:border-gray-700">
                                <p class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $isBasicCard ? 'Included' : 'Everything in Basic, plus' }}
                                </p>

                                <ul class="space-y-2.5 text-sm text-gray-600 dark:text-gray-300">
                                    @if ($isBasicCard)
                                        @foreach ($alwaysIncluded as $label)
                                            <li class="flex items-start gap-2">
                                                <i class="mt-0.5 fa-solid fa-check text-emerald-600 dark:text-emerald-400" aria-hidden="true"></i>
                                                <span class="leading-5">{{ $label }}</span>
                                            </li>
                                        @endforeach
                                    @endif

                                    @foreach ($shownFeatures as $feature)
                                        @continue(in_array($feature, ['billing_page', 'this_branch'], true))
                                        <li class="flex items-start gap-2">
                                            <i class="mt-0.5 fa-solid fa-check text-emerald-600 dark:text-emerald-400" aria-hidden="true"></i>
                                            <span class="leading-5">
                                                {{ $featureLabels[$feature] ?? \Illuminate\Support\Str::of($feature)->replace('_', ' ')->title() }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>

            {{-- Summary: what the selected plan + cycle costs and when it runs --}}
            <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 sm:px-6 dark:border-gray-700 dark:bg-gray-900/40">
                <div id="quoteEmpty" class="text-sm text-gray-500 dark:text-gray-400 {{ $preselectPlan ? 'hidden' : '' }}">
                    Select a plan to see the total and dates.
                </div>

                <div id="quoteBlocked" class="hidden px-3 py-2 text-sm rounded-xl bg-amber-50 text-amber-800 dark:bg-amber-900/20 dark:text-amber-300" role="status"></div>

                <dl id="quoteDetails" class="hidden grid-cols-2 text-sm gap-x-4 gap-y-2 sm:grid-cols-4">
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Plan price</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white">₱<span data-quote="listPrice"></span></dd>
                    </div>
                    <div data-quote-row="credit">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Unused Basic credit</dt>
                        <dd class="font-semibold text-emerald-700 dark:text-emerald-400">−₱<span data-quote="credit"></span></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Starts</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white"><span data-quote="startsAt"></span></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Ends</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white"><span data-quote="expiresAt"></span></dd>
                    </div>
                </dl>

                <p id="quoteNote" class="hidden mt-3 text-xs text-gray-500 dark:text-gray-400"></p>

                <div class="mt-4">
                    <button id="checkoutButton" type="submit" class="{{ $btnPrimary }}" @disabled(! $preselectPlan)>
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <span id="checkoutLabel">Continue to Payment</span>
                    </button>

                    <p class="mt-2 text-xs text-center text-gray-500 dark:text-gray-400">
                        Secure payment by PayMongo: {{ $methodLabels }}.
                    </p>
                </div>
            </div>
        </section>
    </form>

    {{-- ── Billing history ────────────────────────────────────────────── --}}
    @if ($subscriptionHistory->isNotEmpty())
        <section class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-800">
            <details class="group" @if ($scheduledPeriods->isNotEmpty() || $state === 'grace') open @endif>
                <summary class="flex items-center justify-between gap-4 px-4 py-4 list-none cursor-pointer sm:px-6">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Billing History</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Your plan payments and receipts.
                        </p>
                    </div>

                    <i class="text-gray-500 transition-transform fa-solid fa-chevron-down group-open:rotate-180 dark:text-gray-400" aria-hidden="true"></i>
                </summary>

                <div class="p-4 space-y-3 border-t border-gray-200 sm:p-6 dark:border-gray-700">
                    @foreach ($subscriptionHistory as $history)
                        @php
                            $historyPlan = \App\Models\Spa::normalisePlanKey($history->business_tier);
                            $historyStyles = $planStyles[$historyPlan] ?? $neutralStyle;
                            [$stateLabel, $stateClass] = $historyStates[$history->state()] ?? $historyStates['failed'];
                        @endphp

                        <div class="p-4 border border-gray-200 rounded-xl dark:border-gray-700">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-semibold {{ $historyStyles['badge'] }}">
                                            <span class="h-2 w-2 rounded-full {{ $historyStyles['dot'] }}"></span>
                                            {{ $historyPlan === 'expired' ? 'Plan' : ucfirst($historyPlan) }}
                                        </span>

                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ ucfirst($history->billing_cycle ?? 'monthly') }}
                                            · {{ $kindLabels[$history->kind] ?? 'Payment' }}
                                        </span>

                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $stateClass }}">
                                            {{ $stateLabel }}
                                        </span>
                                    </div>

                                    <div class="grid gap-2 mt-3 text-sm text-gray-500 sm:grid-cols-3 dark:text-gray-400">
                                        <p>
                                            Paid:
                                            <span class="font-semibold text-gray-700 dark:text-gray-300">
                                                ₱{{ number_format((float) $history->amount, 2) }}
                                            </span>
                                            @if ((float) $history->credit_amount > 0)
                                                <span class="text-xs">(₱{{ number_format((float) $history->credit_amount, 2) }} credit)</span>
                                            @endif
                                        </p>

                                        @if ($history->isPaid() && $history->starts_at)
                                            <p class="sm:col-span-2">
                                                Period:
                                                <span class="font-semibold text-gray-700 dark:text-gray-300">
                                                    {{ $history->starts_at->format('M d, Y') }} – {{ $history->expires_at?->format('M d, Y') ?? 'No end date' }}
                                                </span>
                                            </p>
                                        @else
                                            <p class="sm:col-span-2">
                                                Started:
                                                <span class="font-semibold text-gray-700 dark:text-gray-300">
                                                    {{ $history->created_at?->format('M d, Y h:i A') }}
                                                </span>
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                @if ($history->isPaid())
                                    <a
                                        href="{{ route('owner.subscription.receipt', $history->id) }}"
                                        class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-[#8B7355] px-4 py-2 text-sm font-semibold text-[#8B7355] transition hover:bg-[#8B7355]/5 dark:border-[#C4A97D] dark:text-[#C4A97D]"
                                    >
                                        <i class="fa-solid fa-download" aria-hidden="true"></i>
                                        Receipt
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </details>
        </section>
    @endif

</div>

<script>
(function () {
    'use strict';

    const QUOTES = @json($quoteData);

    const form = document.getElementById('subscriptionCheckoutForm');
    const cycleSelect = document.getElementById('billingCycleSelector');
    const cycleInput = document.getElementById('billingCycleInput');
    const button = document.getElementById('checkoutButton');
    const label = document.getElementById('checkoutLabel');
    const emptyEl = document.getElementById('quoteEmpty');
    const blockedEl = document.getElementById('quoteBlocked');
    const detailsEl = document.getElementById('quoteDetails');
    const noteEl = document.getElementById('quoteNote');
    const creditRow = detailsEl.querySelector('[data-quote-row="credit"]');

    if (!form || !cycleSelect) return;

    function selectedPlan() {
        const checked = form.querySelector('.plan-option:checked');
        return checked ? checked.value : null;
    }

    function setText(key, value) {
        const el = detailsEl.querySelector('[data-quote="' + key + '"]');
        if (el) el.textContent = value ?? '';
    }

    function render() {
        const cycle = cycleSelect.value;
        cycleInput.value = cycle;

        document.querySelectorAll('.plan-price').forEach(p => {
            p.textContent = cycle === 'yearly' ? p.dataset.yearly : p.dataset.monthly;
        });
        document.querySelectorAll('.plan-period').forEach(p => {
            p.textContent = cycle === 'yearly' ? '/year' : '/month';
        });
        document.querySelectorAll('.yearly-savings').forEach(s => {
            s.classList.toggle('hidden', cycle !== 'yearly');
        });

        const plan = selectedPlan();
        const quote = plan && QUOTES[plan] ? QUOTES[plan][cycle] : null;

        emptyEl.classList.toggle('hidden', !!quote);
        blockedEl.classList.add('hidden');
        detailsEl.classList.add('hidden');
        detailsEl.classList.remove('grid');
        noteEl.classList.add('hidden');

        if (!quote) {
            button.disabled = true;
            label.textContent = 'Continue to Payment';
            return;
        }

        if (!quote.allowed) {
            blockedEl.textContent = quote.message;
            blockedEl.classList.remove('hidden');
            button.disabled = true;
            label.textContent = 'Not available';
            return;
        }

        setText('listPrice', quote.listPrice);
        setText('credit', quote.credit);
        setText('startsAt', quote.startsAt);
        setText('expiresAt', quote.expiresAt);
        creditRow.classList.toggle('hidden', !quote.credit);
        detailsEl.classList.remove('hidden');
        detailsEl.classList.add('grid');

        let note = '';
        if (quote.kind === 'upgrade' && quote.credit) {
            note = 'Premium starts now. The unused value of your Basic time is taken off the price.';
        } else if (quote.kind === 'downgrade') {
            note = 'You keep Premium until your paid time ends, then this plan starts. Branches and staff above the Basic limit are paused, not deleted.';
        } else if (quote.scheduled) {
            note = 'Nothing changes today. This period starts right after your current access ends.';
        }

        if (note) {
            noteEl.textContent = note;
            noteEl.classList.remove('hidden');
        }

        button.disabled = false;
        label.textContent = quote.action + ' · Pay ₱' + quote.amount;
    }

    cycleSelect.addEventListener('change', render);
    form.querySelectorAll('.plan-option').forEach(r => r.addEventListener('change', render));

    // Double-click guard: one checkout per click.
    form.addEventListener('submit', function (e) {
        if (button.disabled) { e.preventDefault(); return; }
        button.disabled = true;
        label.textContent = 'Opening PayMongo…';
    });

    render();
})();
</script>
@endsection
