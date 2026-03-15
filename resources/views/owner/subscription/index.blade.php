@extends('layouts.app')

@section('content')
<div class="p-6 max-w-3xl mx-auto">

<x-page-header 
    title="Subscription" 
    subtitle="Manage your spa subscription plan." 
/>

{{-- CURRENT PLAN --}}
<div class="mt-6 bg-white shadow rounded-xl dark:bg-gray-800 dark:border dark:border-gray-700 p-6">

    <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-200">
        Current Plan
    </h2>

    <div class="mt-4 flex items-center justify-between">

        <div>
            <p class="text-gray-500 dark:text-gray-400 text-sm">
                Tier
            </p>

            <p class="text-xl font-bold text-gray-800 dark:text-gray-100 capitalize">
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
    @if($subscription && $subscription->expires_at)
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

<div class="mt-6 bg-white shadow rounded-xl dark:bg-gray-800 dark:border dark:border-gray-700 p-6">

    <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-200">
        Upgrade to Professional
    </h2>

    <p class="mt-2 text-gray-500 dark:text-gray-400">
        Unlock advanced features to grow your spa business.
    </p>

    <ul class="mt-6 space-y-2 list-disc list-inside text-gray-600 dark:text-gray-300">
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

<div class="mt-6 bg-red-50 border border-red-200 rounded-xl p-6">

    <h3 class="text-lg font-semibold text-red-700">
        Cancel Subscription
    </h3>

    <p class="mt-2 text-sm text-red-600">
        Cancelling will downgrade your spa to the Basic tier immediately, and you will lose access to all Professional features.
    </p>

    <form action="{{ route('owner.subscription.cancel-subscription') }}" method="POST" class="mt-4">
        @csrf

        <button
            class="px-6 py-2 text-white bg-red-600 rounded-lg hover:bg-red-700">

            Cancel Subscription

        </button>
    </form>

</div>

@endif

</div>
@endsection