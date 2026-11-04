<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Email Verified | Levictas Spa & Wellness</title>

    <link rel="icon" type="image/png" href="{{ asset('images/1.png') }}">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body class="min-h-screen antialiased bg-[#F8F5F1] dark:bg-gray-900">

<div class="fixed inset-0 z-50 flex items-center justify-center px-4 bg-black/60 backdrop-blur-sm">

    <div class="w-full max-w-md p-8 text-center bg-white border border-gray-200 shadow-xl rounded-2xl dark:bg-gray-800 dark:border-gray-700">

        <img
            src="{{ asset('images/1.png') }}"
            alt="Levictas"
            class="object-contain h-12 mx-auto mb-6 rounded-md">

        <div class="relative flex items-center justify-center mx-auto mb-6 w-28 h-28">

            <div
                id="successPing"
                class="absolute inset-0 rounded-full bg-emerald-100 dark:bg-emerald-900/30 animate-ping">
            </div>

            <div class="relative flex items-center justify-center w-24 h-24 border-4 rounded-full border-emerald-100 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/30">

                <div
                    id="successSpinner"
                    class="w-12 h-12 border-4 rounded-full border-emerald-200 border-t-emerald-600 animate-spin dark:border-emerald-900 dark:border-t-emerald-400">
                </div>

                <i
                    id="successCheck"
                    class="absolute hidden text-4xl text-emerald-600 fa-solid fa-check dark:text-emerald-400">
                </i>

            </div>

        </div>

        <p
            id="successLabel"
            class="text-xs font-semibold tracking-widest uppercase text-emerald-600 dark:text-emerald-400">
            Finalizing Verification
        </p>

        <h1
            id="successTitle"
            class="mt-2 text-2xl font-semibold text-[#2D3748] dark:text-white font-['Playfair_Display']">
            Preparing your account
        </h1>

        <p
            id="successMessage"
            class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
            Your email has been verified. We're getting your Levictas account ready.
        </p>

        <div class="w-full h-1.5 mt-6 overflow-hidden bg-gray-100 rounded-full dark:bg-gray-700">
            <div
                id="successProgress"
                class="h-full transition-all duration-700 rounded-full bg-emerald-600"
                style="width: 15%;">
            </div>
        </div>

        <p
            id="redirectMessage"
            class="mt-4 text-xs text-gray-400 transition-opacity duration-300 opacity-0">
            Redirecting automatically...
        </p>

    </div>

</div>

<style>
    @media (prefers-reduced-motion: reduce) {
        #successPing,
        #successSpinner {
            animation: none !important;
        }

        #successProgress,
        #redirectMessage {
            transition: none !important;
        }
    }
</style>

<script>
    const redirectUrl =
        @json($redirectUrl);

    const spinner =
        document.getElementById(
            'successSpinner'
        );

    const check =
        document.getElementById(
            'successCheck'
        );

    const ping =
        document.getElementById(
            'successPing'
        );

    const label =
        document.getElementById(
            'successLabel'
        );

    const title =
        document.getElementById(
            'successTitle'
        );

    const message =
        document.getElementById(
            'successMessage'
        );

    const progress =
        document.getElementById(
            'successProgress'
        );

    const redirectMessage =
        document.getElementById(
            'redirectMessage'
        );

    requestAnimationFrame(function () {
        progress.style.width = '70%';
    });

    setTimeout(function () {
        progress.style.width = '100%';

        spinner.classList.add(
            'hidden'
        );

        check.classList.remove(
            'hidden'
        );

        ping.classList.remove(
            'animate-ping'
        );

        label.textContent =
            'Email Verified';

        title.textContent =
            'Your account is ready';

        message.textContent =
            'Your email has been successfully verified. Welcome to Levictas.';

        redirectMessage.classList.remove(
            'opacity-0'
        );
    }, 900);

    setTimeout(function () {
        window.location.href =
            redirectUrl;
    }, 2200);
</script>

</body>
</html>
