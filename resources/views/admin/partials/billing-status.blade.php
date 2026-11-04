{{--
    Plan and billing state of one spa, for admin lists.
    Usage: @include('admin.partials.billing-status', ['spa' => $spa])
    Load the spa's paid subscriptions first to avoid a query per row.
--}}
@php
    $billingState = $spa->billingState();
    $billingPlan = in_array($billingState, ['trial', 'active', 'grace'], true) ? $spa->currentPlan() : null;

    $billingPlanClasses = [
        'basic' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        'premium' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    ];

    [$billingLabel, $billingClass] = [
        'trial' => ['Free trial', 'text-blue-700 dark:text-blue-300'],
        'active' => ['Paid', 'text-emerald-700 dark:text-emerald-400'],
        'grace' => ['Grace period', 'text-amber-700 dark:text-amber-400'],
        'locked' => ['Locked', 'text-red-700 dark:text-red-400'],
        'new' => ['No plan yet', 'text-gray-500 dark:text-gray-400'],
    ][$billingState];

    $billingUntil = match ($billingState) {
        'trial' => 'Until ' . $spa->trial_ends_at->format('M d, Y'),
        'active' => $spa->hasUnlimitedPeriod() ? 'No end date' : 'Until ' . $spa->paidThroughAt()?->format('M d, Y'),
        'grace' => 'Locks ' . $spa->graceEndsAt()?->format('M d, Y'),
        default => null,
    };
@endphp

<div class="flex flex-wrap items-center gap-2">
    @if ($billingPlan)
        <span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $billingPlanClasses[$billingPlan] ?? $billingPlanClasses['basic'] }}">
            {{ config("plans.{$billingPlan}.name", ucfirst($billingPlan)) }}
        </span>
    @endif

    <span class="text-sm font-semibold {{ $billingClass }}">{{ $billingLabel }}</span>
</div>

@if ($billingUntil)
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $billingUntil }}</p>
@endif
