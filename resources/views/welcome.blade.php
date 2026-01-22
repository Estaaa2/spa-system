<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Levictas') }}</title>

        <!-- Font Awesome Icons -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

        <link rel="preconnect" href="https://fonts.bunny.net">
        @vite(['resources/css/landing.css'])
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
        @endif
    </head>

    <body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] font-['Inter',_sans-serif] min-h-screen">
        <nav class="fixed top-0 left-0 right-0 z-50 transition-all duration-300 nav-glass">
            <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-20">
                    <div class="flex items-center">
                        <a href="{{ url('/') }}" class="flex items-center space-x-3 group">
                            <img src="{{ asset('images/1.png') }}" alt="Levictas" class="w-auto h-10 rounded-md">
                            <div class="flex flex-col">
                                <span class="text-2xl font-semibold text-[#2D3748] dark:text-white font-['Playfair_Display'] tracking-wide">Levictas</span>
                                <span class="text-xs tracking-wider text-gray-500 dark:text-gray-400">SPA & WELLNESS SANCTUARY</span>
                            </div>
                        </a>
                    </div>

                    <div class="items-center hidden space-x-8 md:flex">
                        <a href="{{ url('/') }}" class="relative px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-[#8B7355] dark:hover:text-[#8B7355] {{ request()->is('/') ? 'text-[#8B7355]' : '' }}">
                            Home
                        </a>
                        <a href="{{ route('login') }}" class="relative px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-[#8B7355] dark:hover:text-[#8B7355] {{ request()->is('login') ? 'text-[#8B7355]' : '' }}">
                            Login
                        </a>
                        <a href="{{ route('register') }}" class="relative px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-[#8B7355] dark:hover:text-[#8B7355] {{ request()->is('register') ? 'text-[#8B7355]' : '' }}">
                            Register
                        </a>
                        <a href="{{ route('booking') }}" class="booking-btn ml-4 px-6 py-2.5 text-sm font-medium text-white rounded-full transition-all duration-300 shadow-lg hover:shadow-xl">
                            Book Now
                        </a>
                    </div>

                    <div class="md:hidden">
                        <button type="button" id="mobile-menu-button" class="relative p-2 text-gray-700 transition-colors duration-200 rounded-lg dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">
                            <i class="text-xl fas fa-bars"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div id="mobile-menu" class="hidden bg-white border-t border-gray-200 shadow-lg md:hidden dark:bg-gray-900 dark:border-gray-800">
                <div class="px-2 pt-2 pb-4 space-y-1">
                    <a href="{{ url('/') }}" class="block px-3 py-3 rounded-lg text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 {{ request()->is('/') ? 'bg-gray-50 dark:bg-gray-800 text-[#8B7355]' : '' }}">
                        Home
                    </a>
                    <a href="{{ route('login') }}" class="block px-3 py-3 rounded-lg text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 {{ request()->is('login') ? 'bg-gray-50 dark:bg-gray-800 text-[#8B7355]' : '' }}">
                        Login
                    </a>
                    <a href="{{ route('register') }}" class="block px-3 py-3 rounded-lg text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 {{ request()->is('register') ? 'bg-gray-50 dark:bg-gray-800 text-[#8B7355]' : '' }}">
                        Register
                    </a>
                    <div class="pt-4">
                        <a href="{{ route('booking') }}" class="block w-full px-3 py-3 text-base font-medium text-center text-white rounded-lg booking-btn">
                            Book Now
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <main class="pt-20">
            <section class="relative overflow-hidden bg-gradient-to-br from-[#FAF7F2] via-white to-[#FAF7F2] dark:from-gray-900 dark:via-gray-900 dark:to-gray-800">
                <div class="absolute inset-0 hero-gradient"></div>
                <div class="relative px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div class="grid items-center gap-12 py-16 lg:grid-cols-2 lg:gap-20 lg:py-24">
                        <div class="relative z-10">
                            <div class="floating-element mb-6 inline-block px-4 py-2 bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-full shadow-lg">
                                <p class="text-sm font-semibold tracking-wider text-white uppercase">Welcome to Levictas</p>
                            </div>

                            <h1 class="text-5xl lg:text-6xl xl:text-7xl font-light text-[#2D3748] dark:text-white leading-tight mb-8 font-['Playfair_Display']">
                                Find Your <span class="text-[#8B7355] italic">Inner Balance</span> & Peace
                            </h1>

                            <p class="max-w-2xl mb-10 text-lg leading-relaxed text-gray-600 lg:text-xl dark:text-gray-300">
                                At Levictas, we create a sanctuary where mind, body, and spirit unite in perfect harmony. Experience transformative wellness treatments that restore your natural rhythm and elevate your well-being.
                            </p>

                            <div class="flex flex-col gap-6 sm:flex-row">
                                <a href="{{ route('booking') }}" class="relative inline-flex items-center justify-center px-8 py-4 text-lg font-medium text-white transition-all duration-300 rounded-full shadow-xl booking-btn group">
                                    <span class="relative z-10">Book an Appointment</span>
                                    <i class="ml-3 transition-transform fas fa-arrow-right group-hover:translate-x-1"></i>
                                </a>
                                <a href="" class="group relative inline-flex items-center justify-center px-8 py-4 text-lg font-medium text-[#8B7355] border-2 border-[#8B7355] hover:bg-[#8B7355] hover:text-white rounded-full transition-all duration-300">
                                    <span>View Services</span>
                                    <i class="ml-3 transition-transform fas fa-arrow-right group-hover:translate-x-1"></i>
                                </a>
                            </div>
                        </div>

                        <div class="relative">
                            <div class="relative rounded-3xl overflow-hidden shadow-2xl transform hover:scale-[1.02] transition-transform duration-500">
                                <img class="w-full h-[500px] object-cover" src="{{ asset('images/face.jpeg') }}" alt="Serene spa environment at Levictas">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent"></div>
                            </div>

                            <div class="absolute max-w-xs p-6 transition-transform duration-300 transform bg-white shadow-2xl -bottom-6 -left-6 dark:bg-gray-800 rounded-2xl hover:scale-105">
                                <div class="flex items-center space-x-4">
                                    <div class="shrink-0">
                                        <div class="w-14 h-14 bg-gradient-to-br from-[#FAF7F2] to-white dark:from-gray-700 dark:to-gray-800 rounded-xl flex items-center justify-center shadow-lg">
                                            <i class="fas fa-clock text-[#8B7355] text-xl"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <p class="mb-1 text-sm text-gray-500 dark:text-gray-400">Average Session</p>
                                        <p class="text-2xl font-semibold text-[#2D3748] dark:text-white stat-number">90 minutes</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="py-16 bg-white dark:bg-gray-900">
                <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                        <div class="feature-card bg-[#FAF7F2] dark:bg-gray-800 rounded-2xl p-8 hover:shadow-2xl transition-all duration-300">
                            <div class="feature-icon w-14 h-14 bg-gradient-to-br from-[#8B7355] to-[#6F5430] rounded-xl flex items-center justify-center mb-6">
                                <i class="text-xl text-white fas fa-award"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-[#2D3748] dark:text-white mb-4">Certified Therapists</h3>
                            <p class="text-gray-600 dark:text-gray-300">Our therapists are certified professionals with extensive training in holistic wellness practices.</p>
                        </div>

                        <div class="feature-card bg-[#FAF7F2] dark:bg-gray-800 rounded-2xl p-8 hover:shadow-2xl transition-all duration-300">
                            <div class="feature-icon w-14 h-14 bg-gradient-to-br from-[#8B7355] to-[#6F5430] rounded-xl flex items-center justify-center mb-6">
                                <i class="text-xl text-white fas fa-leaf"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-[#2D3748] dark:text-white mb-4">Natural Ingredients</h3>
                            <p class="text-gray-600 dark:text-gray-300">We use only organic, sustainable ingredients sourced from ethical suppliers worldwide.</p>
                        </div>

                        <div class="feature-card bg-[#FAF7F2] dark:bg-gray-800 rounded-2xl p-8 hover:shadow-2xl transition-all duration-300">
                            <div class="feature-icon w-14 h-14 bg-gradient-to-br from-[#8B7355] to-[#6F5430] rounded-xl flex items-center justify-center mb-6">
                                <i class="text-xl text-white fas fa-user-cog"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-[#2D3748] dark:text-white mb-4">Personalized Approach</h3>
                            <p class="text-gray-600 dark:text-gray-300">Every treatment is customized to meet your unique needs and wellness goals.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Treatments Section-->
            <section class="py-20 bg-gradient-to-b from-white to-[#FAF7F2] dark:from-gray-900 dark:to-gray-800">
                <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div class="mb-16 text-center">
                        <span class="inline-block px-4 py-2 bg-gradient-to-r from-[#8B7355]/10 to-[#6F5430]/10 dark:from-[#8B7355]/20 dark:to-[#6F5430]/20 rounded-full mb-4">
                            <p class="text-sm font-semibold uppercase tracking-wider text-[#8B7355]"><i class="mr-2 fas fa-spa"></i>Our Treatments</p>
                        </span>
                        <h2 class="text-4xl lg:text-5xl font-light text-[#2D3748] dark:text-white mb-6 font-['Playfair_Display']">
                            Restorative Wellness Experiences
                        </h2>
                        <p class="max-w-2xl mx-auto text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            Each treatment is carefully designed to address specific needs, using only the finest natural ingredients and ancient techniques.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
                        <!-- Treatment Card 1 -->
                        <div class="p-6 bg-white border border-gray-100 treatment-card group dark:bg-gray-800 rounded-3xl hover:shadow-2xl dark:border-gray-700">
                            <div class="relative mb-6 overflow-hidden rounded-2xl h-60">
                                <img src="{{ asset('images/back.jpeg') }}" alt="Therapeutic Massage" class="object-cover w-full h-full transition-transform duration-700 transform group-hover:scale-110">
                                <div class="absolute inset-0 transition-opacity duration-300 opacity-0 bg-gradient-to-t from-black/50 to-transparent group-hover:opacity-100"></div>
                            </div>
                            <h3 class="text-2xl font-semibold text-[#2D3748] dark:text-white mb-4 group-hover:text-[#8B7355] transition-colors duration-300">Therapeutic Massage</h3>
                            <p class="mb-6 leading-relaxed text-gray-600 dark:text-gray-300">Deep tissue and Swedish techniques to release tension and restore muscular balance.</p>
                        </div>

                        <!-- Treatment Card 2 -->
                        <div class="p-6 bg-white border border-gray-100 treatment-card group dark:bg-gray-800 rounded-3xl hover:shadow-2xl dark:border-gray-700">
                            <div class="relative mb-6 overflow-hidden rounded-2xl h-60">
                                <img src="{{ asset('images/f-hand.webp') }}" alt="Holistic Facial" class="object-cover w-full h-full transition-transform duration-700 transform group-hover:scale-110">
                                <div class="absolute inset-0 transition-opacity duration-300 opacity-0 bg-gradient-to-t from-black/50 to-transparent group-hover:opacity-100"></div>
                            </div>
                            <h3 class="text-2xl font-semibold text-[#2D3748] dark:text-white mb-4 group-hover:text-[#8B7355] transition-colors duration-300">Holistic Facials</h3>
                            <p class="mb-6 leading-relaxed text-gray-600 dark:text-gray-300">Customized skincare treatments using organic products for radiant, healthy skin.</p>
                            <a href="" class="inline-flex items-center text-[#8B7355] font-medium group-hover:text-[#6F5430] transition-colors duration-300">
                                <i class="mr-2 fas fa-arrow-right"></i> Learn more
                            </a>
                        </div>

                        <!-- Treatment Card 3 -->
                        <div class="p-6 bg-white border border-gray-100 treatment-card group dark:bg-gray-800 rounded-3xl hover:shadow-2xl dark:border-gray-700">
                            <div class="relative mb-6 overflow-hidden rounded-2xl h-60">
                                <img src="{{ asset('images/body.jpeg') }}" alt="Energy Healing" class="object-cover w-full h-full transition-transform duration-700 transform group-hover:scale-110">
                                <div class="absolute inset-0 transition-opacity duration-300 opacity-0 bg-gradient-to-t from-black/50 to-transparent group-hover:opacity-100"></div>
                            </div>
                            <h3 class="text-2xl font-semibold text-[#2D3748] dark:text-white mb-4 group-hover:text-[#8B7355] transition-colors duration-300">Energy Healing</h3>
                            <p class="mb-6 leading-relaxed text-gray-600 dark:text-gray-300">Reiki and crystal therapy to balance your energy centers and promote deep relaxation.</p>
                            <a href="" class="inline-flex items-center text-[#8B7355] font-medium group-hover:text-[#6F5430] transition-colors duration-300">
                                <i class="mr-2 fas fa-arrow-right"></i> Learn more
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Stats Section -->
            <section class="py-20 bg-gradient-to-r from-[#8B7355] to-[#6F5430] relative overflow-hidden">
                <div class="absolute inset-0 bg-pattern opacity-10"></div>
                <div class="relative px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div class="grid grid-cols-2 gap-12 text-center md:grid-cols-4">
                        <div class="floating-element">
                            <div class="mb-3 text-5xl font-bold text-white lg:text-6xl stat-number"><i class="mr-2 fas fa-calendar-star"></i>15+</div>
                            <div class="text-sm tracking-wider uppercase text-white/90">Years Experience</div>
                        </div>
                        <div class="floating-element" style="animation-delay: 2s;">
                            <div class="mb-3 text-5xl font-bold text-white lg:text-6xl stat-number"><i class="mr-2 fas fa-heart"></i>98%</div>
                            <div class="text-sm tracking-wider uppercase text-white/90">Client Satisfaction</div>
                        </div>
                        <div class="floating-element" style="animation-delay: 4s;">
                            <div class="mb-3 text-5xl font-bold text-white lg:text-6xl stat-number"><i class="mr-2 fas fa-hand-holding-heart"></i>50+</div>
                            <div class="text-sm tracking-wider uppercase text-white/90">Wellness Treatments</div>
                        </div>
                        <div class="floating-element" style="animation-delay: 6s;">
                            <div class="mb-3 text-5xl font-bold text-white lg:text-6xl stat-number"><i class="mr-2 fas fa-user-md"></i>12</div>
                            <div class="text-sm tracking-wider uppercase text-white/90">Expert Therapists</div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Testimonials Section -->
            <section class="py-20 bg-white dark:bg-gray-900">
                <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div class="mb-16 text-center">
                        <span class="inline-block px-4 py-2 bg-gradient-to-r from-[#8B7355]/10 to-[#6F5430]/10 dark:from-[#8B7355]/20 dark:to-[#6F5430]/20 rounded-full mb-4">
                            <p class="text-sm font-semibold uppercase tracking-wider text-[#8B7355]"><i class="mr-2 fas fa-comment-alt"></i>Client Stories</p>
                        </span>
                        <h2 class="text-4xl lg:text-5xl font-light text-[#2D3748] dark:text-white mb-6 font-['Playfair_Display']">
                            Experiences That Speak Volumes
                        </h2>
                    </div>

                    <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                        <!-- Testimonial 1 -->
                        <div class="testimonial-card rounded-3xl p-8 border border-gray-100 dark:border-gray-800 hover:border-[#8B7355] transition-colors duration-300">
                            <div class="flex items-center mb-6">
                                <div class="relative overflow-hidden border-2 border-white rounded-full shadow-lg w-14 h-14 dark:border-gray-800">
                                    <img src="{{ asset('images/marjo.jpg') }}" alt="Client" class="object-cover w-full h-full">
                                </div>
                                <div class="ml-4">
                                    <h4 class="font-semibold text-gray-900 dark:text-white">Marjo Catibod</h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400"><i class="mr-1 fas fa-clock"></i>Regular Client • 2 years</p>
                                </div>
                            </div>
                            <p class="italic leading-relaxed text-gray-600 dark:text-gray-300">"Levictas has transformed my approach to self-care. The therapists are truly gifted healers who understand the connection between mind and body."</p>
                        </div>

                        <!-- Testimonial 2 -->
                        <div class="testimonial-card rounded-3xl p-8 border border-gray-100 dark:border-gray-800 hover:border-[#8B7355] transition-colors duration-300">
                            <div class="flex items-center mb-6">
                                <div class="relative overflow-hidden border-2 border-white rounded-full shadow-lg w-14 h-14 dark:border-gray-800">
                                    <img src="{{ asset('images/cee.jpg') }}" alt="Client" class="object-cover w-full h-full">
                                </div>
                                <div class="ml-4">
                                    <h4 class="font-semibold text-gray-900 dark:text-white">Cedie Heyrosa</h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400"><i class="mr-1 fas fa-user-clock"></i>First-time Visitor</p>
                                </div>
                            </div>
                            <p class="italic leading-relaxed text-gray-600 dark:text-gray-300">"The most relaxing experience I've ever had. I left feeling completely renewed and centered. The attention to detail is remarkable."</p>
                        </div>

                        <!-- Testimonial 3 -->
                        <div class="testimonial-card rounded-3xl p-8 border border-gray-100 dark:border-gray-800 hover:border-[#8B7355] transition-colors duration-300">
                            <div class="flex items-center mb-6">
                                <div class="relative overflow-hidden border-2 border-white rounded-full shadow-lg w-14 h-14 dark:border-gray-800">
                                    <img src="{{ asset('images/d.jpg') }}" alt="Client" class="object-cover w-full h-full">
                                </div>
                                <div class="ml-4">
                                    <h4 class="font-semibold text-gray-900 dark:text-white">Pat Andrew Vicuna</h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400"><i class="mr-1 fas fa-briefcase"></i>Corporate Wellness Client</p>
                                </div>
                            </div>
                            <p class="italic leading-relaxed text-gray-600 dark:text-gray-300">"We bring our entire team here quarterly. It's been incredible for workplace stress management and overall team well-being."</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA Section -->
            <section class="relative py-24 overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-r from-[#8B7355] to-[#6F5430]"></div>
                <div class="relative max-w-6xl px-4 mx-auto text-center sm:px-6 lg:px-8">
                    <h2 class="text-4xl lg:text-5xl font-light text-white mb-6 font-['Playfair_Display'] leading-tight">
                        Ready to Begin Your Wellness Journey?
                    </h2>
                    <p class="max-w-2xl mx-auto mb-10 text-lg leading-relaxed text-white/90">
                        Take the first step toward balance and serenity. Book your appointment today and experience the Levictas difference.
                    </p>

                    <div class="flex flex-col justify-center gap-6 sm:flex-row">
                        <a href="{{ route('booking') }}" class="group relative inline-flex items-center justify-center px-10 py-4 text-lg font-medium text-[#8B7355] bg-white hover:bg-gray-50 rounded-full transition-all duration-300 shadow-2xl hover:shadow-3xl">
                            <i class="mr-3 fas fa-calendar-check"></i>
                            <span class="relative z-10">Book Now</span>
                            <i class="ml-3 transition-transform fas fa-arrow-right group-hover:translate-x-1"></i>
                        </a>
                        <a href="" class="relative inline-flex items-center justify-center px-10 py-4 text-lg font-medium text-white transition-all duration-300 border-2 border-white rounded-full group hover:bg-white/10">
                            <i class="mr-3 fas fa-phone-alt"></i>
                            <span>Contact Us</span>
                            <i class="ml-3 transition-transform fas fa-arrow-right group-hover:translate-x-1"></i>
                        </a>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="py-16 bg-gradient-to-b from-gray-50 to-white dark:from-gray-900 dark:to-gray-800">
            <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-12 md:grid-cols-4">
                    <div>
                        <div class="flex items-center mb-6 space-x-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-[#8B7355] to-[#6F5430] rounded-lg flex items-center justify-center">
                                <img src="{{ asset('images/1.png') }}" alt="Levictas" class="w-auto h-10 rounded-md">
                            </div>
                            <div>
                                <span class="text-xl font-semibold text-gray-900 dark:text-white font-['Playfair_Display']">Levictas</span>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Spa & Wellness</p>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Experience transformative treatments designed to lighten the body, quiet the mind, and elevate the spirit.</p>
                    </div>

                    <div>
                        <h4 class="text-lg font-semibold text-[#8B7355] dark:text-white mb-6"><i class="mr-2 fas fa-spa"></i>Services</h4>
                        <ul class="space-y-3">
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-[#8B7355] transition-colors"><i class="mr-2 fas fa-hands"></i>Massage Therapy</a></li>
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-[#8B7355] transition-colors"><i class="mr-2 fas fa-smile-beam"></i>Facial Treatments</a></li>
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-[#8B7355] transition-colors"><i class="mr-2 fa-solid fa-fire-flame-simple"></i>Energy Healing</a></li>
                            <li><a href="#" class="text-gray-600 dark:text-gray-400 hover:text-[#8B7355] transition-colors"><i class="mr-2 fas fa-box"></i>Wellness Packages</a></li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="text-lg font-semibold text-[#8B7355] dark:text-white mb-6"><i class="mr-2 fas fa-address-book"></i>Contact</h4>
                        <ul class="space-y-3">
                            <li class="text-gray-600 dark:text-gray-400"><i class="mr-2 fas fa-building"></i>Amafel Building</li>
                            <li class="text-gray-600 dark:text-gray-400"><i class="mr-2 fas fa-map-marker-alt"></i>140 Aguinaldo Hwy, Dasmariñas, Cavite</li>
                            <li class="text-gray-600 dark:text-gray-400"><i class="mr-2 fas fa-phone"></i>+639357816489</li>
                            <li class="text-gray-600 dark:text-gray-400"><i class="mr-2 fas fa-envelope"></i>levictas@gmail.com</li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="text-lg font-semibold text-[#8B7355] dark:text-white mb-6"><i class="mr-2 fas fa-clock"></i>Hours</h4>
                        <ul class="space-y-3">
                            <li class="text-gray-600 dark:text-gray-400"><i class="mr-2 fas fa-calendar-day"></i>Monday - Friday: 9am - 8pm</li>
                            <li class="text-gray-600 dark:text-gray-400"><i class="mr-2 fas fa-calendar-week"></i>Saturday: 10am - 6pm</li>
                            <li class="text-gray-600 dark:text-gray-400"><i class="mr-2 fas fa-calendar"></i>Sunday: 11am - 5pm</li>
                        </ul>
                    </div>
                </div>

                <div class="my-12 section-divider"></div>

                <div class="flex flex-col items-center justify-between md:flex-row">
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400 md:mb-0"><i class="mr-1 far fa-copyright"></i> 2026 Levictas Spa & Wellness. All rights reserved.</p>
                    <div class="flex space-x-6">
                        <a href="#" class="text-gray-600 dark:text-gray-400 hover:text-[#8B7355] transition-colors">
                            <i class="text-xl fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="text-gray-600 dark:text-gray-400 hover:text-[#8B7355] transition-colors">
                            <i class="text-xl fab fa-twitter"></i>
                        </a>
                        <a href="#" class="text-gray-600 dark:text-gray-400 hover:text-[#8B7355] transition-colors">
                            <i class="text-xl fab fa-instagram"></i>
                        </a>
                        <a href="#" class="text-gray-600 dark:text-gray-400 hover:text-[#8B7355] transition-colors">
                            <i class="text-xl fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>
            </div>
        </footer>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const menuButton = document.getElementById('mobile-menu-button');
                const mobileMenu = document.getElementById('mobile-menu');
                const menuIcon = menuButton.querySelector('i');

                menuButton.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');

                    // Toggle menu icon between bars and times
                    if (mobileMenu.classList.contains('hidden')) {
                        menuIcon.className = 'fas fa-bars text-xl';
                    } else {
                        menuIcon.className = 'fas fa-times text-xl';
                    }
                });

                // Close menu when clicking outside
                document.addEventListener('click', function(event) {
                    if (!menuButton.contains(event.target) && !mobileMenu.contains(event.target)) {
                        mobileMenu.classList.add('hidden');
                        menuIcon.className = 'fas fa-bars text-xl';
                    }
                });

                // Smooth scroll for anchor links
                document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                    anchor.addEventListener('click', function(e) {
                        e.preventDefault();
                        const targetId = this.getAttribute('href');
                        if(targetId === '#') return;

                        const targetElement = document.querySelector(targetId);
                        if(targetElement) {
                            window.scrollTo({
                                top: targetElement.offsetTop - 80,
                                behavior: 'smooth'
                            });
                        }
                    });
                });

                // Add scroll effect to navbar
                let lastScrollTop = 0;
                const navbar = document.querySelector('nav');

                window.addEventListener('scroll', function() {
                    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

                    if (scrollTop > 100) {
                        navbar.classList.add('shadow-lg', 'bg-white/80', 'dark:bg-gray-900/80');
                        navbar.classList.remove('shadow-none');
                    } else {
                        navbar.classList.remove('shadow-lg', 'bg-white/80', 'dark:bg-gray-900/80');
                        navbar.classList.add('shadow-none');
                    }

                    lastScrollTop = scrollTop;
                });
            });
        </script>
    </body>
</html>
