@extends('layouts.app')

@section('title', 'Subscription Expired')

@section('content')
<div class="flex min-h-[70vh] items-center justify-center px-4 py-10">
    <div class="w-full max-w-lg rounded-3xl border border-amber-200 bg-white p-8 text-center shadow-sm dark:border-amber-800 dark:bg-gray-800">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-300">
            <i class="fa-solid fa-lock text-2xl" aria-hidden="true"></i>
        </div>

        <h1 class="mt-6 text-2xl font-semibold text-gray-900 dark:text-white">
            Subscription Expired
        </h1>

        <p class="mt-3 text-gray-600 dark:text-gray-300">
            Your spa's subscription has expired. Please contact the owner.
        </p>

        <div class="mt-6">
            <a
                href="{{ route('profile.edit') }}"
                class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-gray-300 px-5 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
            >
                <i class="fa-solid fa-user" aria-hidden="true"></i>
                View Profile
            </a>
        </div>
    </div>
</div>
@endsection
