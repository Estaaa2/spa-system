<!DOCTYPE html>
<html lang="en">

{{--
    Public page layout (landing footer Phase 1): the landing page's navbar,
    footer and look, for pages that anyone can open — guests, customers and
    business users alike. Routes that use it must NOT add LandingPageRedirect.

    Usage:
        @extends('layouts.public')
        @section('title', 'Privacy Policy')
        @section('content') ... @endsection

    Loads landing-nav.js instead of welcome.js, so these pages stay light.
    Dark mode follows the OS, like the landing page (Tailwind "media").
--}}

<head>
    @include('partials.landing.head', [
        'entries' => ['resources/css/app.css', 'resources/css/landing.css', 'resources/js/landing-nav.js'],
    ])
    @stack('head')
</head>

{{-- flex column + flex-1 main: the footer stays at the bottom on short pages. --}}
<body
    class="flex flex-col min-h-screen bg-[#F6EFE6] dark:bg-gray-900 text-gray-800 dark:text-gray-100 selection:bg-[#D2A85B]/30 selection:text-[#3C2F23]">

    @include('partials.landing.nav')

    @auth
        @include('partials.landing.logout-modal')
    @endauth

    <main class="flex-1 pt-20">
        @yield('content')
    </main>

    @include('partials.landing.footer')

    @stack('scripts')
</body>

</html>
