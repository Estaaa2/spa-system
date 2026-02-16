<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="font-sans antialiased text-gray-900">
    <div class="relative min-h-screen">

        <!-- Background Image -->
        <div class="absolute inset-0">
            <img
                src="{{ asset('images/heads.png') }}"
                alt="Spa Background"
                class="object-cover w-full h-full"
            >
            <!-- Dark Overlay -->
            <div class="absolute inset-0 bg-black/50"></div>
        </div>

        <!-- Content -->
        <div class="relative z-10 flex flex-col items-center justify-center min-h-screen px-4 py-8 sm:px-6 sm:py-10">

            <!-- Logo -->
            <div class="mb-6 sm:mb-8">
                <a href="/">
                    <x-application-logo class="w-16 h-16 text-white fill-current sm:w-20 sm:h-20" />
                </a>
            </div>

            <!-- Card Container -->
            <div
                class="w-full max-w-md overflow-hidden shadow-3xl bg-white/100 backdrop-blur-md rounded-2xl sm:max-w-xl md:max-w-3xl lg:max-w-4xl"
            >
                {{ $slot }}
            </div>

        </div>
    </div>
</body>

</html>
