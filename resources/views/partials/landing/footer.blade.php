{{--
    Landing footer, shared by welcome.blade.php and layouts/public.blade.php
    (landing footer Phase 1). Social links come from config/levictas.php;
    an empty URL hides its icon. Legal links go to the Phase 2 pages; About,
    FAQ and Contact are their own pages (Phase 3).
--}}
@php
    $socialLinks = collect([
        ['key' => 'linkedin', 'label' => 'LinkedIn', 'icon' => 'fa-linkedin-in'],
        ['key' => 'x',        'label' => 'X',        'icon' => 'fa-x-twitter'],
        ['key' => 'github',   'label' => 'GitHub',   'icon' => 'fa-github'],
    ])->map(fn ($s) => $s + ['url' => trim((string) config('levictas.social.' . $s['key']))])
      ->filter(fn ($s) => $s['url'] !== '');
    $footerLink = 'block transition hover:text-white';
@endphp
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
                    <img src="{{ asset('images/1.png') }}" alt="Levictas"
                        class="h-10 rounded-md ring-1 ring-white/10">
                    <div>
                        <h3 class="font-['Playfair_Display'] text-2xl font-semibold">Levictas</h3>
                        <p class="mt-1 text-xs tracking-[0.18em] uppercase text-white/70">Spa & Wellness Sanctuary
                        </p>
                    </div>
                </div>
                <p class="max-w-md mt-5 text-sm text-white/75">
                    Find trusted spas and reserve appointments with ease — your relaxation journey starts here.
                </p>
                @if ($socialLinks->isNotEmpty())
                    <div class="flex items-center gap-3 mt-6">
                        @foreach ($socialLinks as $social)
                            <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer"
                                aria-label="Levictas on {{ $social['label'] }}"
                                class="inline-flex items-center justify-center w-10 h-10 transition rounded-xl bg-white/10 ring-1 ring-white/10 hover:bg-white/15">
                                <i class="fa-brands {{ $social['icon'] }}" aria-hidden="true"></i>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="md:col-span-7">
                <div class="grid gap-8 sm:grid-cols-3">
                    <div>
                        <p class="text-sm font-semibold tracking-wide">Company</p>
                        <div class="mt-4 space-y-2 text-sm text-white/75">
                            <a class="{{ $footerLink }}" href="{{ route('about') }}">About</a>
                            <a class="{{ $footerLink }}" href="{{ route('contact') }}">Contact</a>
                            <a class="{{ $footerLink }}" href="{{ route('faq') }}">FAQ</a>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-semibold tracking-wide">Legal</p>
                        <div class="mt-4 space-y-2 text-sm text-white/75">
                            <a class="{{ $footerLink }}" href="{{ route('legal.terms') }}">Terms and Conditions</a>
                            <a class="{{ $footerLink }}" href="{{ route('legal.privacy') }}">Privacy Policy</a>
                            <a class="{{ $footerLink }}" href="{{ route('legal.refund') }}">Refund Policy</a>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-semibold tracking-wide">Get Started</p>
                        <div class="mt-4 space-y-2 text-sm text-white/75">
                            {{-- Book Now goes to the spa list on /: customers book from a spa card.
                                 route('booking') is the business-side walk-in form (403 for customers). --}}
                            @guest
                                <a class="{{ $footerLink }}" href="{{ route('register') }}">Register</a>
                                <a class="{{ $footerLink }}" href="{{ route('login') }}">Login</a>
                                <a class="{{ $footerLink }}" href="{{ url('/') }}#browseSections">Book Now</a>
                            @else
                                @role('customer')
                                    <x-landing.nav-action open="appointments" action="openAppointmentsModal()"
                                        class="{{ $footerLink }} text-left">My Appointments</x-landing.nav-action>
                                    <x-landing.nav-action open="profile" action="openProfileModal();"
                                        class="{{ $footerLink }} text-left">Profile</x-landing.nav-action>
                                    <a class="{{ $footerLink }}" href="{{ url('/') }}#browseSections">Book Now</a>
                                @else
                                    <a class="{{ $footerLink }}" href="{{ \App\Support\BusinessHome::url(auth()->user()) }}">Go to Dashboard</a>
                                @endrole
                            @endguest
                            {{-- Mobile app Phase 2: for every visitor. --}}
                            <a class="{{ $footerLink }}" href="{{ route('mobile-app') }}">Get the App</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div
            class="flex flex-col gap-3 pt-6 mt-12 border-t border-white/10 md:flex-row md:items-center md:justify-between">
            <p class="text-xs text-white/60">© {{ date('Y') }} Levictas. All rights reserved.</p>
            <p class="text-xs text-white/55">Made with care for comfort & wellness.</p>
        </div>
    </div>
</footer>
