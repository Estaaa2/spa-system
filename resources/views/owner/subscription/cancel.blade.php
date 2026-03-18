@extends('layouts.app')

@section('content')
<div class="max-w-2xl p-6 mx-auto text-center">
    <div class="p-8 bg-white shadow rounded-xl dark:bg-gray-800 dark:border dark:border-gray-700">
        <i class="text-5xl text-red-500 fa-solid fa-circle-xmark"></i>
        <h2 class="mt-4 text-2xl font-semibold text-gray-700 dark:text-gray-200">Payment Cancelled</h2>
        <p class="mt-2 text-gray-500 dark:text-gray-400">
            Your upgrade was not completed. You can try again anytime.
        </p>

        <a href="{{ route('owner.subscription.index') }}"
            class="mt-6 inline-block px-6 py-3 text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6347] transition-colors">
            Try Again
        </a>
    </div>
</div>
@endsection
