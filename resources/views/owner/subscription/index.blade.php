@extends('layouts.app')

@section('title', 'Subscription')
@section('content')
<div class="max-w-3xl p-6 mx-auto">

<x-page-header
    title="Subscription"
    subtitle="Manage your spa subscription plan."
/>

{{-- CURRENT PLAN --}}
<div class="p-6 mt-6 bg-white shadow rounded-xl dark:bg-gray-800 dark:border dark:border-gray-700">

    <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-200">
        Current Plan
    </h2>

    <div class="flex items-center justify-between mt-4">

        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Tier
            </p>

            <p class="text-xl font-bold text-gray-800 capitalize dark:text-gray-100">
                {{ $spa->business_tier }}
            </p>
        </div>

        <div class="text-right">

            @if($spa->business_tier === 'professional')
                <span class="px-3 py-1 text-sm font-semibold text-green-700 bg-green-100 rounded-full">
                    Active
                </span>
            @else
                <span class="px-3 py-1 text-sm font-semibold text-gray-700 bg-gray-200 rounded-full">
                    Basic
                </span>
            @endif

        </div>

    </div>

    {{-- Expiry info --}}
    @if($subscription && $subscription->expires_at && $subscription->expires_at->isFuture())
        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
            Subscription expires on
            <span class="font-semibold">
                {{ $subscription->expires_at->format('F d, Y') }}
            </span>
        </p>
    @endif

</div>


{{-- SHOW UPGRADE IF BASIC --}}
@if($spa->business_tier !== 'professional')

<div class="p-6 mt-6 bg-white shadow rounded-xl dark:bg-gray-800 dark:border dark:border-gray-700">

    <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-200">
        Upgrade to Professional
    </h2>

    <p class="mt-2 text-gray-500 dark:text-gray-400">
        Unlock advanced features to grow your spa business.
    </p>

    <ul class="mt-6 space-y-2 text-gray-600 list-disc list-inside dark:text-gray-300">
        <li>Branch public listing</li>
        <li>Customer online reservation</li>
        <li>Enhanced decision support tools</li>
        <li>Priority support</li>
        <li>Unlimited staff and branches</li>
    </ul>

    <p class="mt-6 text-xl font-bold text-gray-800 dark:text-gray-100">
        ₱200 / month
    </p>

    <form action="{{ route('owner.subscription.checkout') }}" method="POST" class="mt-6">
        @csrf

        <button
            type="submit"
            class="w-full px-6 py-3 font-semibold text-white transition-all duration-200 bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg hover:opacity-90 focus:ring-4 focus:ring-[#8B7355]/50">

            Upgrade to Professional

        </button>
    </form>

</div>

@endif


{{-- SHOW CANCEL IF PROFESSIONAL --}}
@if($spa->business_tier === 'professional')

<div class="p-6 mt-6 border border-red-200 bg-red-50 rounded-xl">

    <h3 class="text-lg font-semibold text-red-700">
        Cancel Subscription
    </h3>

    <p class="mt-2 text-sm text-red-600">
        Cancelling will downgrade your spa to the Basic tier immediately, and you will lose access to all Professional features.
    </p>

    <button
        type="button"
        onclick="document.getElementById('cancel-sub-modal').classList.remove('hidden')"
        class="px-6 py-2 mt-4 text-white bg-red-600 rounded-lg hover:bg-red-700">

        Cancel Subscription

    </button>

</div>

{{-- CONFIRMATION MODAL --}}
<div id="cancel-sub-modal" class="fixed inset-0 z-50 flex items-center justify-center hidden px-4">

    {{-- backdrop --}}
    <div class="absolute inset-0 bg-black/50" onclick="document.getElementById('cancel-sub-modal').classList.add('hidden')"></div>

    {{-- modal card --}}
    <div class="relative w-full max-w-md p-6 bg-white shadow-xl dark:bg-gray-800 rounded-xl">

        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">
            Are you sure you want to cancel?
        </h3>

        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            This will immediately downgrade your spa to the <span class="font-semibold">Basic</span> tier.
            You'll lose access to branch listing, online reservations, decision support tools, priority support,
            and unlimited staff/branches right away. This action cannot be undone.
        </p>

        <div class="flex justify-end gap-3 mt-6">

            <button
                type="button"
                onclick="document.getElementById('cancel-sub-modal').classList.add('hidden')"
                class="px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                Keep my subscription
            </button>

            <form action="{{ route('owner.subscription.cancel-subscription') }}" method="POST">
                @csrf
                <button
                    type="submit"
                    class="px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700">
                    Yes, cancel it
                </button>
            </form>

        </div>

    </div>

</div>

@endif

</div>
@endsection
