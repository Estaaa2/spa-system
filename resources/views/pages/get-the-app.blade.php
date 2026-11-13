@extends('layouts.public')

{{--
    "Get the app" page (mobile app Phase 2). Built like About / FAQ: public
    layout, landing nav and footer, warm palette. Everything shown comes from
    App\Support\MobileApp (config/levictas.php + the APK file), never the
    database. App features come from routes/api.php; owners can't log in to
    the app (Api\AuthController allows customers, therapists and staff marked
    "Performs treatments"), so the page offers them nothing beyond sharing it.
--}}

@section('title', 'Get the App')

@section('content')
    @php
        $app = \App\Support\MobileApp::class;
        $available = $app::available();
        $card = 'p-7 shadow-sm bg-white/70 dark:bg-gray-900/40 rounded-3xl ring-1 ring-black/5 dark:ring-white/10';
        $iconBox = 'flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10';
        $fact = 'px-4 py-3 rounded-2xl bg-[#F6EFE6] dark:bg-gray-800/70';
        $step = 'p-5 rounded-2xl bg-[#F6EFE6] dark:bg-gray-800/70';
        $stepNo = 'inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-full bg-[#8B7355]';
        $showQr = $available && $app::showQr();
    @endphp

    <div class="px-4 py-12 sm:px-6 sm:py-16">
        <div class="max-w-5xl mx-auto">
            @include('partials.public.back-home')

            <header class="mt-4 text-center">
                <div class="flex items-center justify-center gap-4 sm:gap-6">
                    <span class="hidden sm:block h-px w-16 sm:w-24 bg-gradient-to-r from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                    <h1 class="text-3xl sm:text-4xl font-['Playfair_Display'] text-[#3C2F23] dark:text-white font-semibold">Get the Levictas app</h1>
                    <span class="hidden sm:block h-px w-16 sm:w-24 bg-gradient-to-l from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                </div>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">Book and keep track of your spa appointments from your Android phone.</p>
            </header>

            <div class="grid gap-6 mt-10 {{ $showQr ? 'md:grid-cols-3' : '' }}">
                {{-- Download card --}}
                <section class="{{ $card }} {{ $showQr ? 'md:col-span-2' : '' }}" aria-labelledby="download-title">
                    <div class="flex items-center gap-4">
                        <img src="{{ asset('images/1.png') }}" alt=""
                            class="w-16 h-16 rounded-2xl ring-1 ring-black/5 {{ $available ? '' : 'opacity-60' }}">
                        <div>
                            <h2 id="download-title" class="text-xl font-semibold text-[#3C2F23] dark:text-white">Levictas for Android</h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                @if ($available)
                                    Version {{ $app::version() }}@if ($app::released()) · Released {{ $app::released() }}@endif
                                @else
                                    The download isn't available right now.
                                @endif
                            </p>
                        </div>
                    </div>

                    @if ($available)
                        <a href="{{ route('mobile-app.download') }}" data-testid="app-download"
                            class="booking-btn inline-flex items-center justify-center gap-2.5 min-h-[48px] mt-6 px-7 py-3 text-[15px] font-semibold text-white rounded-full shadow-lg hover:shadow-xl transition active:translate-y-0.5 w-full sm:w-auto">
                            <i class="fa-solid fa-download" aria-hidden="true"></i>
                            Download for Android
                        </a>

                        <dl class="grid grid-cols-1 gap-3 mt-6 sm:grid-cols-3">
                            <div class="{{ $fact }}">
                                <dt class="text-xs text-gray-600 dark:text-gray-400">File size</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-[#3C2F23] dark:text-white">{{ $app::size() }}</dd>
                            </div>
                            <div class="{{ $fact }}">
                                <dt class="text-xs text-gray-600 dark:text-gray-400">Needs</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-[#3C2F23] dark:text-white">Android {{ $app::minAndroid() }} or newer</dd>
                            </div>
                            <div class="{{ $fact }}">
                                <dt class="text-xs text-gray-600 dark:text-gray-400">File type</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-[#3C2F23] dark:text-white">APK</dd>
                            </div>
                        </dl>

                        @if ($app::sha256())
                            <details class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                                <summary class="flex items-center min-h-[44px] font-medium cursor-pointer text-[#6F5430] dark:text-[#C4A97D]">Check the file (optional)</summary>
                                <p class="mb-2">The file's SHA-256 fingerprint. It should match what your computer shows for the download.</p>
                                <code class="block px-3 py-2.5 font-mono text-xs break-all bg-white rounded-xl dark:bg-gray-900 ring-1 ring-black/5 dark:ring-white/10 text-gray-700 dark:text-gray-300">{{ $app::sha256() }}</code>
                            </details>
                        @endif
                    @else
                        <div class="flex gap-2.5 px-4 py-3.5 mt-5 text-sm leading-6 rounded-2xl bg-amber-50 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                            <i class="mt-1 fa-solid fa-circle-info" aria-hidden="true"></i>
                            <span>We're updating the app file. Please try again later. You can still book on this website.</span>
                        </div>
                        <a href="{{ url('/') }}#browseSections"
                            class="booking-btn inline-flex items-center min-h-[44px] mt-5 px-6 py-2.5 text-sm font-semibold text-white rounded-full shadow-lg hover:shadow-xl transition active:translate-y-0.5">
                            Browse spas
                        </a>
                    @endif
                </section>

                {{-- QR: for people viewing this page on a computer or a projector. Hidden on phones. --}}
                @if ($showQr)
                    <section class="{{ $card }} hidden md:flex flex-col items-center text-center" aria-labelledby="qr-title">
                        <h2 id="qr-title" class="font-semibold text-[#3C2F23] dark:text-white">On a computer?</h2>
                        <img src="{{ asset('images/get-the-app-qr.svg') }}" width="198" height="198"
                            alt="QR code that opens levictas.com/get-the-app"
                            class="mt-4 bg-white rounded-2xl ring-1 ring-black/5">
                        <p class="mt-4 text-sm leading-6 text-gray-600 dark:text-gray-400">Point your phone's camera at this code to open this page on your phone.</p>
                    </section>
                @endif
            </div>

            {{-- What the app does (routes/api.php) --}}
            <h2 class="mt-12 text-2xl font-['Playfair_Display'] font-semibold text-center text-[#3C2F23] dark:text-white">What you can do in the app</h2>
            <div class="grid gap-6 mt-6 md:grid-cols-2">
                <div class="{{ $card }}">
                    <div class="{{ $iconBox }}"><i class="fa-solid fa-calendar-check text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i></div>
                    <h3 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">For customers</h3>
                    <ul class="mt-2 space-y-1.5 text-sm leading-6 text-gray-600 list-disc list-inside dark:text-gray-400">
                        <li>Find spas near you and book an appointment</li>
                        <li>Pay for your booking online</li>
                        <li>See your appointments and ask to reschedule</li>
                        <li>Rate a spa after your visit</li>
                    </ul>
                </div>
                <div class="{{ $card }}">
                    <div class="{{ $iconBox }}"><i class="fa-solid fa-user-nurse text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i></div>
                    <h3 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">For therapists</h3>
                    <ul class="mt-2 space-y-1.5 text-sm leading-6 text-gray-600 list-disc list-inside dark:text-gray-400">
                        <li>See the bookings assigned to you and your schedule</li>
                        <li>Clock in and out, and mark when you've arrived</li>
                        <li>File leave requests and view your payslips</li>
                        <li>Check your performance</li>
                    </ul>
                </div>
            </div>
            <p class="max-w-2xl mx-auto mt-5 text-sm leading-6 text-center text-gray-600 dark:text-gray-400">
                <span class="font-semibold text-[#3C2F23] dark:text-white">Spa owners:</span>
                the app is for your customers and therapists. Share this page with your staff, and keep managing your spa on the website.
            </p>

            {{-- Install steps --}}
            <section class="mt-12 {{ $card }}" aria-labelledby="install-title">
                <h2 id="install-title" class="text-2xl font-['Playfair_Display'] font-semibold text-[#3C2F23] dark:text-white">How to install</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">The app isn't on the Play Store yet, so Android asks you to confirm a few times. This is normal for apps downloaded from a website.</p>
                <ol class="grid gap-4 mt-6 sm:grid-cols-2 lg:grid-cols-4">
                    <li class="{{ $step }}">
                        <span class="{{ $stepNo }}">1</span>
                        <p class="mt-3 text-sm font-semibold text-[#3C2F23] dark:text-white">Download the file</p>
                        <p class="mt-1.5 text-[13px] leading-5 text-gray-600 dark:text-gray-400">Tap Download for Android. If your browser warns about the file, tap Download anyway.</p>
                    </li>
                    <li class="{{ $step }}">
                        <span class="{{ $stepNo }}">2</span>
                        <p class="mt-3 text-sm font-semibold text-[#3C2F23] dark:text-white">Open it</p>
                        <p class="mt-1.5 text-[13px] leading-5 text-gray-600 dark:text-gray-400">Open the downloaded file from the notification or your Downloads folder.</p>
                    </li>
                    <li class="{{ $step }}">
                        <span class="{{ $stepNo }}">3</span>
                        <p class="mt-3 text-sm font-semibold text-[#3C2F23] dark:text-white">Allow the install</p>
                        <p class="mt-1.5 text-[13px] leading-5 text-gray-600 dark:text-gray-400">If asked, tap Settings and turn on "Allow from this source" for your browser, then go back. On Android 7, turn on "Unknown sources" in Settings, Security.</p>
                    </li>
                    <li class="{{ $step }}">
                        <span class="{{ $stepNo }}">4</span>
                        <p class="mt-3 text-sm font-semibold text-[#3C2F23] dark:text-white">Install and log in</p>
                        <p class="mt-1.5 text-[13px] leading-5 text-gray-600 dark:text-gray-400">Tap Install. If Play Protect warns you, tap More details if shown, then Install anyway. Log in with your Levictas account.</p>
                    </li>
                </ol>
            </section>

            <div class="flex items-start gap-3.5 px-5 py-4 mt-6 rounded-2xl bg-white/50 dark:bg-gray-900/30 ring-1 ring-black/5 dark:ring-white/10">
                <i class="mt-1 fa-solid fa-mobile-screen text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                <p class="text-sm leading-6 text-gray-600 dark:text-gray-400">
                    <span class="font-semibold text-[#3C2F23] dark:text-white">Using an iPhone?</span>
                    There's no iPhone app yet. You can book and manage appointments on this website from Safari.
                </p>
            </div>
        </div>
    </div>
@endsection
