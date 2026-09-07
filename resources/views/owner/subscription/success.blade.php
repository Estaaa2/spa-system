@extends('layouts.app')

@section('content')
<div class="max-w-2xl p-6 mx-auto text-center">
    <div class="p-8 bg-white shadow rounded-xl dark:bg-gray-800 dark:border dark:border-gray-700">
        <i class="text-5xl text-green-500 fa-solid fa-circle-check"></i>
        <h2 class="mt-4 text-2xl font-semibold text-gray-700 dark:text-gray-200">Payment Successful!</h2>
        <p class="mt-2 text-gray-500 dark:text-gray-400">
            Congratulations! Your spa is now on the Professional tier.
        </p>

        <div class="flex flex-col-reverse items-center justify-center gap-3 mt-6 sm:flex-row">
            @if ($subscription && $subscription->payment_status === 'paid')
                <a href="{{ route('owner.subscription.receipt', $subscription->id) }}"
                    class="inline-flex items-center justify-center gap-2 px-6 py-3 text-[#8B7355] border border-[#8B7355] rounded-lg hover:bg-[#8B7355]/5 transition-colors">
                    <i class="text-sm fa-solid fa-download"></i>
                    Download Receipt
                </a>
            @else
                {{-- Webhook may not have landed yet — let the owner refresh instead of a dead-end --}}
                <a href="{{ route('owner.subscription.success') }}"
                    class="inline-flex items-center justify-center gap-2 px-6 py-3 text-gray-500 transition-colors border border-gray-300 rounded-lg hover:bg-gray-50 dark:text-gray-400 dark:border-gray-600 dark:hover:bg-gray-700/50">
                    <i class="text-sm fa-solid fa-rotate"></i>
                    Refresh Status
                </a>
            @endif

            <a href="{{ route('owner.subscription.index') }}"
                class="inline-flex items-center justify-center px-6 py-3 text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6347] transition-colors">
                Go back to Subscriptions
            </a>
        </div>
    </div>
</div>
@endsection