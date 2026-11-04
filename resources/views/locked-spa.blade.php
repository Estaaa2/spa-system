@extends('layouts.app')

@section('title', 'Spa Unavailable')

@section('content')
<div class="flex min-h-[70vh] items-center justify-center px-4 py-10">
    <div class="w-full max-w-lg rounded-3xl border border-amber-200 bg-white p-8 text-center shadow-sm dark:border-amber-800 dark:bg-gray-800">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-300">
            <i class="fa-solid fa-lock text-2xl" aria-hidden="true"></i>
        </div>

        <h1 class="mt-6 text-2xl font-semibold text-gray-900 dark:text-white">
            {{ $spaName }} is unavailable right now
        </h1>

        <p class="mt-3 text-gray-600 dark:text-gray-300">
            The spa's Levictas plan has ended, so staff can't use Levictas until the owner renews it.
            Your account and work records are kept. Please contact your spa owner.
        </p>

        <div class="mt-6 flex flex-col justify-center gap-2 sm:flex-row">
            <a
                href="{{ route('spa.locked') }}"
                class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-[#8B7355] px-5 py-2 text-sm font-semibold text-white hover:bg-[#7A6348]"
            >
                <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                Check again
            </a>

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
