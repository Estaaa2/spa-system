@extends('layouts.app')

@section('title', 'Choose Your Free Trial')

@section('content')
<div class="min-h-screen px-4 py-8 sm:px-6 lg:px-8">
    <div class="max-w-6xl mx-auto">
        <div class="max-w-2xl mx-auto text-center">
            <p class="text-sm font-semibold uppercase tracking-wide text-[#8B7355]">
                Welcome to Levictas
            </p>

            <h1 class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white sm:text-4xl">
                Choose your free trial
            </h1>

            <p class="mt-3 text-gray-600 dark:text-gray-300">
                Try Basic or Premium for one month. No payment is needed to start your trial.
            </p>

            <div class="inline-flex items-center gap-2 px-4 py-2 mt-4 text-sm font-semibold rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                <i class="fa-solid fa-gift" aria-hidden="true"></i>
                1 month free, no payment needed
            </div>
        </div>

        <div class="grid max-w-5xl gap-6 mx-auto mt-10 md:grid-cols-2">
            @foreach ($plans as $key => $plan)
                <div class="flex flex-col p-6 bg-white border border-gray-200 shadow-sm rounded-3xl dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">
                                {{ $plan['name'] }}
                            </h2>

                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                {{ $plan['max_branches'] }} branch{{ $plan['max_branches'] === 1 ? '' : 'es' }}
                                · {{ $plan['max_staff'] }} staff members
                            </p>
                        </div>

                        @if ($key === 'premium')
                            <span class="px-3 py-1 text-xs font-semibold text-blue-700 bg-blue-100 rounded-full dark:bg-blue-900/30 dark:text-blue-300">
                                Popular
                            </span>
                        @endif
                    </div>

                    <div class="mt-6">
                        <span class="text-3xl font-semibold text-gray-900 dark:text-white">
                            ₱{{ number_format($plan['monthly_price'], 0) }}
                        </span>

                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            / month after trial
                        </span>
                    </div>

                    <ul class="flex-1 mt-6 space-y-3">
                        @foreach ($plan['features'] as $feature)
                            <li class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <i class="fa-solid fa-check mt-0.5 text-emerald-500" aria-hidden="true"></i>
                                <span>{{ ucwords(str_replace('_', ' ', $feature)) }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <form method="POST" action="{{ route('owner.trial.start') }}" class="mt-8">
                        @csrf
                        <input type="hidden" name="plan" value="{{ $key }}">

                        <button
                            type="submit"
                            class="inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#8B7355] to-[#6F5430] px-5 py-3 text-sm font-semibold text-white transition hover:from-[#7A6348] hover:to-[#5E4728]"
                        >
                            <i class="fa-solid fa-rocket" aria-hidden="true"></i>
                            Start {{ $plan['name'] }} Trial
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
