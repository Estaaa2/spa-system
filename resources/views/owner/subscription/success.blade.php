@extends('layouts.app')

@section('content')
<div class="p-6 max-w-2xl mx-auto text-center">
    <div class="bg-white shadow rounded-xl dark:bg-gray-800 dark:border dark:border-gray-700 p-8">
        <i class="fa-solid fa-circle-check text-5xl text-green-500"></i>
        <h2 class="mt-4 text-2xl font-semibold text-gray-700 dark:text-gray-200">Payment Successful!</h2>
        <p class="mt-2 text-gray-500 dark:text-gray-400">
            Congratulations! Your spa is now on the Professional tier.
        </p>

        <a href="{{ route('owner.subscription.index') }}" 
            class="mt-6 inline-block px-6 py-3 text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6347] transition-colors">
            Go back to Subscriptions
        </a>
    </div>
</div>
@endsection