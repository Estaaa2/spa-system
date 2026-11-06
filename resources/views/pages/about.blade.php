@extends('layouts.public')

{{--
    About page (landing footer Phase 3; moved off the landing page on review,
    where "How It Works" and "Why Book With Us" already cover the benefits).
    Footer → /about. Team and school come from config/levictas.php (Q3-A),
    the same values as the legal pages.
--}}

@section('title', 'About Us')

@section('content')
    @php
        $team = \App\Support\Legal\Contact::team();
        $school = \App\Support\Legal\Contact::school();
        $card = 'p-7 shadow-sm bg-white/70 dark:bg-gray-900/40 rounded-3xl ring-1 ring-black/5 dark:ring-white/10';
        $iconBox = 'flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10';
    @endphp

    <div class="px-4 py-12 sm:px-6 sm:py-16">
        <div class="max-w-5xl mx-auto">
            @include('partials.public.back-home')

            <header class="mt-4 text-center">
                <div class="flex items-center justify-center gap-4 sm:gap-6">
                    <span class="hidden sm:block h-px w-16 sm:w-24 bg-gradient-to-r from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                    <h1 class="text-3xl sm:text-4xl font-['Playfair_Display'] text-[#3C2F23] dark:text-white font-semibold">About Levictas</h1>
                    <span class="hidden sm:block h-px w-16 sm:w-24 bg-gradient-to-l from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                </div>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">One place to find, book and run spas in Cavite.</p>
            </header>

            <div class="grid gap-6 mt-10 md:grid-cols-3">
                <div class="{{ $card }}">
                    <div class="{{ $iconBox }}"><i class="fa-solid fa-spa text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i></div>
                    <h2 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">For customers</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                        Browse verified spas, compare services and prices, and book an in-branch or home-service
                        appointment online, with your bookings and schedule in one place.
                    </p>
                </div>

                <div class="{{ $card }}">
                    <div class="{{ $iconBox }}"><i class="fa-solid fa-store text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i></div>
                    <h2 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">For spas</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                        Take online bookings and manage schedules, staff and daily operations across branches,
                        with reports that support everyday decisions.
                    </p>
                </div>

                <div class="{{ $card }}">
                    <div class="{{ $iconBox }}"><i class="fa-solid fa-people-group text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i></div>
                    <h2 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">Who we are</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                        Levictas is a capstone project developed by {{ $team }}, students of {{ $school }}.
                    </p>
                </div>
            </div>

            <div class="flex flex-col items-center justify-center gap-3 mt-10 sm:flex-row">
                <a href="{{ url('/') }}#browseSections"
                    class="booking-btn px-6 py-2.5 text-sm font-semibold text-white rounded-full shadow-lg hover:shadow-xl transition active:translate-y-0.5">
                    Browse spas
                </a>
                <a href="{{ route('faq') }}"
                    class="px-6 py-2.5 text-sm font-medium rounded-full text-[#6F5430] dark:text-[#C4A97D] bg-white/70 dark:bg-gray-900/40 ring-1 ring-black/5 dark:ring-white/10 hover:bg-white dark:hover:bg-gray-800 transition">
                    Read the FAQ
                </a>
                <a href="{{ route('contact') }}"
                    class="px-6 py-2.5 text-sm font-medium rounded-full text-[#6F5430] dark:text-[#C4A97D] bg-white/70 dark:bg-gray-900/40 ring-1 ring-black/5 dark:ring-white/10 hover:bg-white dark:hover:bg-gray-800 transition">
                    Contact us
                </a>
            </div>
        </div>
    </div>
@endsection
