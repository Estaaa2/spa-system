<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Levictas | Spa & Wellness</title>

    @vite(['resources/css/app.css','resources/css/landing.css', 'resources/js/app.js'])

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="bg-[#F6EFE6] text-gray-800 selection:bg-[#D2A85B]/30 selection:text-[#3C2F23]">

<nav class="fixed top-0 left-0 right-0 z-50 transition-all duration-300 nav-glass" id="topNav">
    <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            <div class="flex items-center">
                <a href="{{ url('/') }}" class="flex items-center space-x-3 group">
                    <img src="{{ asset('images/1.png') }}" alt="Levictas" class="w-auto h-10 rounded-md ring-1 ring-black/5">
                    <div class="flex flex-col leading-tight">
                        <span class="text-2xl font-semibold text-[#2D3748] font-['Playfair_Display'] tracking-wide group-hover:text-[#6F5430] transition">
                            Levictas
                        </span>
                        <span class="text-[10px] tracking-[0.18em] text-gray-500 uppercase">
                            Spa & Wellness Sanctuary
                        </span>
                    </div>
                </a>
            </div>

            <div class="items-center hidden space-x-2 md:flex">
                <a href="{{ url('/') }}"
                   class="relative px-4 py-2 text-sm font-medium rounded-full transition
                   {{ request()->is('/') ? 'text-[#6F5430] bg-white/60 ring-1 ring-black/5' : 'text-gray-700 hover:text-[#8B7355] hover:bg-white/50' }}">
                    Home
                </a>

                @guest
                    <a href="{{ route('login') }}"
                    class="relative px-4 py-2 text-sm font-medium rounded-full transition
                    {{ request()->is('login') ? 'text-[#6F5430] bg-white/60 ring-1 ring-black/5' : 'text-gray-700 hover:text-[#8B7355] hover:bg-white/50' }}">
                        Login
                    </a>

                    <a href="{{ route('register') }}"
                    class="relative px-4 py-2 text-sm font-medium rounded-full transition
                    {{ request()->is('register') ? 'text-[#6F5430] bg-white/60 ring-1 ring-black/5' : 'text-gray-700 hover:text-[#8B7355] hover:bg-white/50' }}">
                        Register
                    </a>

                    <a href="{{ route('register.business') }}"
                    class="booking-btn ml-3 px-6 py-2.5 text-sm font-semibold text-white rounded-full transition-all duration-300 shadow-lg hover:shadow-xl active:translate-y-0.5">
                        Join as a Partner
                    </a>

                @else
                    @role('customer')
                        <!-- Customer Profile Menu -->
                        <div class="relative inline-block text-left">
                            <button type="button" class="flex items-center justify-center w-10 h-10 overflow-hidden rounded-full ring-1 ring-black/5">
                                <img src="{{ Auth::user()->profile_photo_url ?? asset('images/default-profile.png') }}" alt="Profile">
                            </button>
                            <div class="absolute right-0 hidden w-48 mt-2 origin-top-right bg-white divide-y divide-gray-100 rounded-md shadow-lg ring-1 ring-black ring-opacity-5" id="profileDropdown">
                                <div class="py-1">
                                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</a>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="block w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100">
                                            Logout
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endrole
                @endguest
            </div>

            <div class="md:hidden">
                <button type="button" id="mobile-menu-button"
                        class="relative p-2 text-gray-700 transition-colors duration-200 rounded-xl hover:bg-white/60 ring-1 ring-black/5">
                    <i class="text-xl fas fa-bars"></i>
                </button>
            </div>
        </div>
    </div>

    <div id="mobile-menu" class="hidden bg-[#F6EFE6]/95 border-t border-black/10 shadow-lg md:hidden">
        <div class="px-3 pt-3 pb-5 space-y-2">
            <a href="{{ url('/') }}"
               class="block px-4 py-3 rounded-xl text-base font-medium transition
               {{ request()->is('/') ? 'bg-white/70 text-[#6F5430] ring-1 ring-black/5' : 'text-gray-700 hover:bg-white/60' }}">
                Home
            </a>

            @guest
                <a href="{{ route('login') }}" class="block px-4 py-3 text-base font-medium rounded-xl hover:bg-white/60">Login</a>
                <a href="{{ route('register') }}" class="block px-4 py-3 text-base font-medium rounded-xl hover:bg-white/60">Register</a>
                <a href="{{ route('register.business') }}" class="block px-4 py-3 text-base font-medium rounded-xl hover:bg-white/60">Join as a Partner</a>
            @else
                @role('customer')
                
                    <a href="#" class="block px-4 py-3 text-base font-medium rounded-xl hover:bg-white/60">Profile</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full px-4 py-3 text-base font-medium text-left rounded-xl hover:bg-white/60">
                            Logout
                        </button>
                    </form>
                @endrole
            @endguest
        </div>
    </div>
</nav>

<main class="pt-20">
    <section class="relative overflow-hidden">
        <div class="absolute inset-0">
            <img src="{{ asset('images/heads.png') }}" class="object-cover w-full h-full" alt="Hero">
            <div class="absolute inset-0 bg-black/45"></div>
            <div class="absolute inset-0 bg-gradient-to-b from-black/30 via-black/35 to-[#F6EFE6]"></div>
        </div>

        <div class="relative px-6 py-24 mx-auto text-center max-w-7xl">
            <div class="max-w-3xl mx-auto">
                <p class="inline-flex items-center gap-2 px-4 py-2 text-xs tracking-[0.2em] uppercase text-white/90 bg-white/10 rounded-full ring-1 ring-white/10">
                    <i class="fa-solid fa-spa text-white/80"></i>
                    Wellness • Relaxation • Care
                </p>

                <h1 class="mt-6 text-4xl md:text-6xl font-['Playfair_Display'] text-white font-semibold leading-tight">
                    Find and Book the Best Spa
                </h1>

                <p class="mt-4 text-lg md:text-xl text-white/90">
                    Relaxation, wellness, and pampering — made easy.
                </p>
            </div>

            <form action="{{ route('booking') }}" method="GET"
                  class="grid max-w-4xl grid-cols-1 gap-4 p-4 mx-auto mt-10 shadow-2xl bg-white/90 rounded-2xl ring-1 ring-black/5 md:grid-cols-12">
                <div class="flex items-center gap-3 px-4 py-3 bg-white border border-black/10 md:col-span-8 rounded-xl">
                    <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-[#F6EFE6] ring-1 ring-black/5">
                        <i class="fa-solid fa-location-dot text-[#8B7355]"></i>
                    </span>
                    <input type="text" name="city" placeholder="Where are you at?"
                        class="w-full text-sm bg-transparent border-0 focus:ring-0 soft-ring placeholder:text-gray-400">
                </div>

                <button class="md:col-span-4 booking-btn text-white rounded-xl font-semibold hover:opacity-95 transition shadow-lg active:translate-y-0.5">
                    <span class="inline-flex items-center justify-center gap-2 py-3">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Search
                    </span>
                </button>
            </form>
        </div>

        <div class="h-10 bg-gradient-to-b from-transparent to-[#F6EFE6]"></div>
    </section>

    <!-- ================= FEATURED SPAS ================= -->
    <section class="py-20">
        <div class="px-6 mx-auto mt-10 max-w-7xl">
            <div class="text-center">
                <div class="flex items-center justify-center gap-6">
                    <span class="h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355]"></span>
                    <h2 class="text-4xl font-['Playfair_Display'] text-[#3C2F23] font-semibold">
                        Featured Spas
                    </h2>
                    <span class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355]"></span>
                </div>

                <p class="mt-3 text-sm text-gray-600">
                    Curated picks for a premium relaxation experience.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 mt-12 md:grid-cols-4">
                @php
                    $spas = [
                        ['Liz Spa','1st.png'],
                        ['Carreza Spa','2nd.png'],
                        ['Aj Way Spa','3rd.png'],
                        ['Harmony Spa','4th.png']
                    ];
                @endphp

                @foreach($spas as $spa)
                    <div class="overflow-hidden transition bg-white border shadow-lg border-black/5 rounded-2xl hover:-translate-y-1 hover:shadow-2xl">
                        <div class="relative">
                            <img src="{{ asset('images/'.$spa[1]) }}" class="object-cover w-full h-44" alt="{{ $spa[0] }}">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/35 via-black/0 to-transparent"></div>
                            <div class="absolute bottom-3 left-3 inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-white bg-black/35 rounded-full ring-1 ring-white/10">
                                <i class="fa-solid fa-fire"></i> Popular
                            </div>
                        </div>

                        <div class="p-5">
                            <h3 class="font-semibold text-[#3C2F23]">{{ $spa[0] }}</h3>

                            <div class="flex items-center justify-between mt-3">
                                <div class="flex gap-1 text-[#D2A85B] text-sm">
                                    <i class="fa-solid fa-spa"></i>
                                    <i class="fa-solid fa-spa"></i>
                                    <i class="fa-solid fa-spa"></i>
                                    <i class="fa-solid fa-spa"></i>
                                    <i class="fa-solid fa-spa opacity-30"></i>
                                </div>
                                <span class="text-xs text-gray-500">4.0+</span>
                            </div>

                            <a href="{{ route('login') }}"
                               class="block mt-5 text-center booking-btn text-white py-2.5 rounded-xl text-sm font-semibold shadow-md hover:shadow-lg transition active:translate-y-0.5">
                                Book Now
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- ================= HOW IT WORKS ================= -->
    <section class="bg-[#EFE3D6] py-20">
        <div class="px-6 mx-auto max-w-7xl">
            <div class="text-center">
                <div class="flex items-center justify-center gap-6">
                    <span class="h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355]"></span>
                    <h2 class="text-4xl font-['Playfair_Display'] text-[#3C2F23] font-semibold">
                        How It Works
                    </h2>
                    <span class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355]"></span>
                </div>

                <p class="mt-3 text-sm text-gray-600">
                    Book in minutes with a simple flow.
                </p>
            </div>

            <div class="grid gap-6 mt-12 md:grid-cols-3">
                <div class="p-8 text-center transition shadow-sm bg-white/70 rounded-3xl ring-1 ring-black/5 hover:shadow-lg">
                    <div class="flex items-center justify-center w-14 h-14 mx-auto rounded-2xl bg-[#F6EFE6] ring-1 ring-black/5">
                        <i class="fa-solid fa-location-dot text-2xl text-[#8B7355]"></i>
                    </div>
                    <h3 class="mt-5 font-semibold text-[#3C2F23]">Find Your Spa</h3>
                    <p class="mt-2 text-sm text-gray-600">Browse verified spas near you.</p>
                </div>

                <div class="p-8 text-center transition shadow-sm bg-white/70 rounded-3xl ring-1 ring-black/5 hover:shadow-lg">
                    <div class="flex items-center justify-center w-14 h-14 mx-auto rounded-2xl bg-[#F6EFE6] ring-1 ring-black/5">
                        <i class="fa-solid fa-list-check text-2xl text-[#8B7355]"></i>
                    </div>
                    <h3 class="mt-5 font-semibold text-[#3C2F23]">Choose Service</h3>
                    <p class="mt-2 text-sm text-gray-600">Select the service you want.</p>
                </div>

                <div class="p-8 text-center transition shadow-sm bg-white/70 rounded-3xl ring-1 ring-black/5 hover:shadow-lg">
                    <div class="flex items-center justify-center w-14 h-14 mx-auto rounded-2xl bg-[#F6EFE6] ring-1 ring-black/5">
                        <i class="fa-solid fa-spa text-2xl text-[#8B7355]"></i>
                    </div>
                    <h3 class="mt-5 font-semibold text-[#3C2F23]">Relax & Enjoy</h3>
                    <p class="mt-2 text-sm text-gray-600">Arrive and unwind.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= WHY BOOK ================= -->
    <section class="py-20">
        <div class="px-6 mx-auto max-w-7xl">
            <div class="text-center">
                <div class="flex items-center justify-center gap-6">
                    <span class="h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355]"></span>
                    <h2 class="text-4xl font-['Playfair_Display'] text-[#3C2F23] font-semibold">
                        Why Book With Us
                    </h2>
                    <span class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355]"></span>
                </div>

                <p class="mt-3 text-sm text-gray-600">
                    Built for convenience and trust.
                </p>
            </div>

            <div class="grid gap-6 mt-12 md:grid-cols-4">
                <div class="transition bg-white shadow-sm p-7 rounded-3xl ring-1 ring-black/5 hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] ring-1 ring-black/5">
                        <i class="fa-solid fa-check text-xl text-[#8B7355]"></i>
                    </div>
                    <h4 class="mt-5 font-semibold text-[#3C2F23]">Verified Spas</h4>
                    <p class="mt-2 text-sm text-gray-600">Only trusted listings appear on the platform.</p>
                </div>

                <div class="transition bg-white shadow-sm p-7 rounded-3xl ring-1 ring-black/5 hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] ring-1 ring-black/5">
                        <i class="fa-solid fa-calendar-check text-xl text-[#8B7355]"></i>
                    </div>
                    <h4 class="mt-5 font-semibold text-[#3C2F23]">Easy Booking</h4>
                    <p class="mt-2 text-sm text-gray-600">Reserve quickly with clear scheduling.</p>
                </div>

                <div class="transition bg-white shadow-sm p-7 rounded-3xl ring-1 ring-black/5 hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] ring-1 ring-black/5">
                        <i class="fa-solid fa-user-nurse text-xl text-[#8B7355]"></i>
                    </div>
                    <h4 class="mt-5 font-semibold text-[#3C2F23]">Expert Therapists</h4>
                    <p class="mt-2 text-sm text-gray-600">Quality care from professional practitioners.</p>
                </div>

                <div class="transition bg-white shadow-sm p-7 rounded-3xl ring-1 ring-black/5 hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] ring-1 ring-black/5">
                        <i class="fa-solid fa-lock text-xl text-[#8B7355]"></i>
                    </div>
                    <h4 class="mt-5 font-semibold text-[#3C2F23]">Secure Payments</h4>
                    <p class="mt-2 text-sm text-gray-600">Safe checkout experience and privacy-focused flow.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= CTA ================= -->
    <section class="py-16">
        <div class="px-6 mx-auto max-w-7xl">
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-[#6F5430] to-[#8B7355] p-10 md:p-14 text-center text-white shadow-2xl">
                <div class="absolute inset-0 opacity-20"
                     style="background-image: radial-gradient(circle at 20% 20%, rgba(255,255,255,.35) 0, transparent 35%),
                                            radial-gradient(circle at 80% 30%, rgba(255,255,255,.25) 0, transparent 40%),
                                            radial-gradient(circle at 50% 90%, rgba(255,255,255,.18) 0, transparent 45%);">
                </div>

                <div class="relative">
                    <h2 class="text-3xl md:text-4xl font-['Playfair_Display'] font-semibold">
                        Own a Spa? List Your Business with Us!
                    </h2>
                    <p class="mt-3 text-sm text-white/90 md:text-base">
                        Reach more customers and manage bookings easily.
                    </p>

                    <a href="{{ route('register') }}"
                       class="inline-flex items-center justify-center gap-2 mt-7 bg-white text-[#6F5430] px-8 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transition active:translate-y-0.5">
                        <i class="fa-solid fa-arrow-right"></i>
                        Get Started
                    </a>
                </div>
            </div>
        </div>
    </section>

</main>

<!-- ================= FOOTER ================= -->
<footer class="relative mt-16">
    <div class="absolute inset-0">
        <img src="{{ asset('images/footers.png') }}" class="object-cover w-full h-full" alt="Footer">
        <div class="absolute inset-0 bg-black/65"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-black/30 to-black/70"></div>
    </div>

    <div class="relative px-6 mx-auto text-white py-14 max-w-7xl">
        <div class="grid gap-10 md:grid-cols-12">
            <div class="md:col-span-5">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('images/1.png') }}" alt="Levictas" class="h-10 rounded-md ring-1 ring-white/10">
                    <div>
                        <h3 class="font-['Playfair_Display'] text-2xl font-semibold">Levictas</h3>
                        <p class="mt-1 text-xs tracking-[0.18em] uppercase text-white/70">
                            Spa & Wellness Sanctuary
                        </p>
                    </div>
                </div>

                <p class="max-w-md mt-5 text-sm text-white/75">
                    Find trusted spas and reserve appointments with ease — your relaxation journey starts here.
                </p>

                <div class="flex items-center gap-3 mt-6">
                    <a href="#" class="inline-flex items-center justify-center w-10 h-10 transition rounded-xl bg-white/10 ring-1 ring-white/10 hover:bg-white/15">
                        <i class="fa-brands fa-facebook-f"></i>
                    </a>
                    <a href="#" class="inline-flex items-center justify-center w-10 h-10 transition rounded-xl bg-white/10 ring-1 ring-white/10 hover:bg-white/15">
                        <i class="fa-brands fa-instagram"></i>
                    </a>
                    <a href="#" class="inline-flex items-center justify-center w-10 h-10 transition rounded-xl bg-white/10 ring-1 ring-white/10 hover:bg-white/15">
                        <i class="fa-brands fa-x-twitter"></i>
                    </a>
                </div>
            </div>

            <div class="md:col-span-7">
                <div class="grid gap-8 sm:grid-cols-3">
                    <div>
                        <p class="text-sm font-semibold tracking-wide">Company</p>
                        <div class="mt-4 space-y-2 text-sm text-white/75">
                            <a class="block transition hover:text-white" href="#">About</a>
                            <a class="block transition hover:text-white" href="#">Contact</a>
                            <a class="block transition hover:text-white" href="#">FAQ</a>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-semibold tracking-wide">Legal</p>
                        <div class="mt-4 space-y-2 text-sm text-white/75">
                            <a class="block transition hover:text-white" href="#">Terms</a>
                            <a class="block transition hover:text-white" href="#">Privacy</a>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-semibold tracking-wide">Get Started</p>
                        <div class="mt-4 space-y-2 text-sm text-white/75">
                            <a class="block transition hover:text-white" href="{{ route('register') }}">Register</a>
                            <a class="block transition hover:text-white" href="{{ route('login') }}">Login</a>
                            <a class="block transition hover:text-white" href="{{ route('booking') }}">Book Now</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-3 pt-6 mt-12 border-t border-white/10 md:flex-row md:items-center md:justify-between">
            <p class="text-xs text-white/60">© {{ date('Y') }} Levictas. All rights reserved.</p>
            <p class="text-xs text-white/55">Made with care for comfort & wellness.</p>
        </div>
    </div>
</footer>

<script>
    // Mobile menu
    const btn = document.getElementById('mobile-menu-button');
    const menu = document.getElementById('mobile-menu');
    btn?.addEventListener('click', () => {
        menu.classList.toggle('hidden');
    });

    // Profile dropdown toggle
    const profileBtn = document.querySelector('.relative.inline-block button');
    const profileDropdown = document.getElementById('profileDropdown');
    profileBtn?.addEventListener('click', () => profileDropdown.classList.toggle('hidden'));

    // Nav scroll effect
    const nav = document.getElementById('topNav');
    const onScroll = () => {
        if (window.scrollY > 10) nav.classList.add('nav-scrolled');
        else nav.classList.remove('nav-scrolled');
    };
    window.addEventListener('scroll', onScroll);
    onScroll();
</script>

</body>
</html>
