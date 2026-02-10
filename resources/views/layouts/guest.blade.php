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
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
            <div class="flex flex-col items-center justify-center min-h-screen px-4 py-8 sm:px-6 sm:py-10">

                <!-- Logo -->
                <div class="mb-6 sm:mb-8">
                    <a href="/">
                        <x-application-logo class="w-16 h-16 text-gray-500 fill-current sm:w-20 sm:h-20" />
                    </a>
                </div>

                <!-- Card Container (responsive width + padding) -->
                <div
                    class="w-full max-w-md px-4 py-4 overflow-hidden dark:bg-gray-800 rounded-2xl sm:px-6 sm:py-6 lg:px-8 lg:py-8 sm:max-w-xl md:max-w-3xl lg:max-w-5xl xl:max-w-6xl"
                >
                    {{ $slot }}
                </div>

            </div>
        </div>
    </body>
</html>
