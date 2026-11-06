@extends('layouts.public')

{{--
    Contact page (landing footer Phase 3, D4 / Q1-A). Static: no form.
    Email and address come from config/levictas.php → contact (the same
    values as the legal pages and the staff ID consent notice); the social
    links from config/levictas.php → social, shown only when set.
--}}

@section('title', 'Contact Us')

@section('content')
    @php
        $email = \App\Support\Legal\Contact::email();
        $emailIsSet = ! \App\Support\Legal\Contact::isPlaceholder($email);
        $address = \App\Support\Legal\Contact::address();
        $socials = collect([
            ['key' => 'linkedin', 'label' => 'LinkedIn', 'icon' => 'fa-linkedin-in'],
            ['key' => 'x',        'label' => 'X',        'icon' => 'fa-x-twitter'],
            ['key' => 'github',   'label' => 'GitHub',   'icon' => 'fa-github'],
        ])->map(fn ($s) => $s + ['url' => trim((string) config('levictas.social.' . $s['key']))])
          ->filter(fn ($s) => $s['url'] !== '');
        $card = 'p-6 sm:p-7 shadow-sm bg-white/70 dark:bg-gray-900/40 rounded-3xl ring-1 ring-black/5 dark:ring-white/10';
        $iconBox = 'flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10 flex-shrink-0';
    @endphp

    <div class="px-4 py-12 sm:px-6 sm:py-16">
        <div class="max-w-4xl mx-auto">
            @include('partials.public.back-home')

            <header class="mt-4 text-center">
                <div class="flex items-center justify-center gap-4 sm:gap-6">
                    <span class="hidden sm:block h-px w-16 sm:w-24 bg-gradient-to-r from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                    <h1 class="text-3xl sm:text-4xl font-['Playfair_Display'] text-[#3C2F23] dark:text-white font-semibold">Contact Us</h1>
                    <span class="hidden sm:block h-px w-16 sm:w-24 bg-gradient-to-l from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                </div>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">Questions about Levictas, a booking, a refund or your data? Reach us here.</p>
            </header>

            <div class="grid gap-6 mt-10 md:grid-cols-2">
                <div class="{{ $card }} flex gap-4">
                    <div class="{{ $iconBox }}"><i class="fa-solid fa-envelope text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i></div>
                    <div class="min-w-0">
                        <h2 class="font-semibold text-[#3C2F23] dark:text-white">Email</h2>
                        <p class="mt-1 text-sm text-gray-600 break-words dark:text-gray-300">
                            @if ($emailIsSet)
                                <a href="mailto:{{ $email }}" class="legal-link">{{ $email }}</a>
                            @else
                                {{ $email }}
                            @endif
                        </p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">We reply by email.</p>
                    </div>
                </div>

                <div class="{{ $card }} flex gap-4">
                    <div class="{{ $iconBox }}"><i class="fa-solid fa-location-dot text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i></div>
                    <div class="min-w-0">
                        <h2 class="font-semibold text-[#3C2F23] dark:text-white">Address</h2>
                        <address class="mt-1 text-sm not-italic text-gray-600 dark:text-gray-300">Levictas<br>{{ $address }}</address>
                    </div>
                </div>
            </div>

            <div class="{{ $card }} mt-6">
                <h2 class="font-semibold text-[#3C2F23] dark:text-white">Who to contact</h2>
                <ul class="mt-4 space-y-3 text-sm leading-6 text-gray-600 dark:text-gray-300">
                    <li class="flex gap-3">
                        <i class="fa-solid fa-calendar-check mt-1 text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                        <span><strong class="text-[#3C2F23] dark:text-white">A booking you made</strong> (running late, cancelling, changing details): call the branch. Its phone number is on the spa page. To change the date or time, send a reschedule request from My Appointments.</span>
                    </li>
                    <li class="flex gap-3">
                        <i class="fa-solid fa-rotate-left mt-1 text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                        <span><strong class="text-[#3C2F23] dark:text-white">Refunds:</strong> email us with the details listed in the <a href="{{ route('legal.refund') }}" class="legal-link">Refund Policy</a>.</span>
                    </li>
                    <li class="flex gap-3">
                        <i class="fa-solid fa-shield-halved mt-1 text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                        <span><strong class="text-[#3C2F23] dark:text-white">Your personal data</strong> (access, correction, deletion): email us. The <a href="{{ route('legal.privacy') }}" class="legal-link">Privacy Policy</a> explains your rights.</span>
                    </li>
                    <li class="flex gap-3">
                        <i class="fa-solid fa-store mt-1 text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                        <span><strong class="text-[#3C2F23] dark:text-white">Listing your spa or your subscription:</strong> email us, or start with <a href="{{ route('register.business') }}" class="legal-link">Join as a Partner</a>.</span>
                    </li>
                    <li class="flex gap-3">
                        <i class="fa-solid fa-circle-question mt-1 text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                        <span><strong class="text-[#3C2F23] dark:text-white">Anything else:</strong> the <a href="{{ route('faq') }}" class="legal-link">FAQ</a> may already have the answer.</span>
                    </li>
                </ul>
            </div>

            @if ($socials->isNotEmpty())
                <div class="{{ $card }} mt-6 text-center">
                    <h2 class="font-semibold text-[#3C2F23] dark:text-white">Follow the team</h2>
                    <div class="flex items-center justify-center gap-3 mt-4">
                        @foreach ($socials as $social)
                            <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer"
                                aria-label="Levictas on {{ $social['label'] }}"
                                class="inline-flex items-center justify-center w-11 h-11 transition rounded-xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10 text-[#6F5430] dark:text-[#C4A97D] hover:bg-[#EFE3D6] dark:hover:bg-gray-600">
                                <i class="fa-brands {{ $social['icon'] }}" aria-hidden="true"></i>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
