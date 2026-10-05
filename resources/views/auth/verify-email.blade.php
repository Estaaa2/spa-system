<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Verify Email | Levictas Spa & Wellness</title>

    <link rel="icon" type="image/png" href="{{ asset('images/1.png') }}">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen antialiased bg-[#F8F5F1] dark:bg-gray-900">

<main class="min-h-screen lg:grid lg:grid-cols-2">

    <section class="relative hidden overflow-hidden lg:block">
        <img
            src="{{ asset('images/face.jpeg') }}"
            alt="Spa"
            class="absolute inset-0 object-cover w-full h-full">

        <div class="absolute inset-0 bg-black/30"></div>

        <div class="relative z-10 flex items-end min-h-screen p-12">
            <div class="max-w-md text-white">
                <h1 class="text-4xl font-light font-['Playfair_Display']">
                    One last step.
                </h1>

                <p class="mt-4 leading-7 text-white/85">
                    Verify your email address to protect your Levictas account.
                </p>
            </div>
        </div>
    </section>

    <section class="flex items-center min-h-screen px-4 py-8 bg-white sm:px-8 dark:bg-gray-800">

        <div class="w-full max-w-md mx-auto">

            <div class="mb-5 text-center">
                <img
                    src="{{ asset('images/1.png') }}"
                    alt="Levictas"
                    class="w-auto mx-auto rounded-md h-14">
            </div>

            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 mb-5 rounded-full bg-[#8B7355]/10 text-[#6F5430] dark:bg-[#C4A97D]/10 dark:text-[#C4A97D]">
                    <i class="text-2xl fa-solid fa-envelope-circle-check"></i>
                </div>

                <h2 class="text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display']">
                    Verify Your Email
                </h2>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    We sent a verification email to
                </p>

                <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                    {{ Auth::user()->email }}
                </p>
            </div>

            @if(session('status') == 'verification-link-sent')
                <div class="p-4 mt-5 text-sm border rounded-xl border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300">
                    <i class="mr-2 fa-solid fa-circle-check"></i>
                    A new verification email and 6-digit code have been sent.
                </div>
            @endif

            <div class="p-5 mt-6 border border-gray-200 rounded-2xl bg-gray-50 dark:border-gray-700 dark:bg-gray-900/40">

                <div class="flex items-start gap-3">
                    <div class="flex items-center justify-center shrink-0 w-9 h-9 rounded-xl bg-[#8B7355]/10 text-[#6F5430] dark:text-[#C4A97D]">
                        <i class="fa-solid fa-link"></i>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            Verification link
                        </p>

                        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">
                            Open your email and click the
                            <strong>Verify Email Address</strong>
                            button.
                        </p>
                    </div>
                </div>

            </div>

            <div class="flex items-center gap-3 my-5">
                <div class="flex-1 border-t border-gray-200 dark:border-gray-700"></div>
                <span class="text-xs font-medium text-gray-400 uppercase">
                    Or use OTP
                </span>
                <div class="flex-1 border-t border-gray-200 dark:border-gray-700"></div>
            </div>

            <form
                method="POST"
                action="{{ route('verification.otp') }}"
                class="space-y-4">

                @csrf

                <div>
                    <label
                        for="otp"
                        class="block mb-2 text-sm font-medium text-center text-gray-700 dark:text-gray-300">
                        Enter your 6-digit verification code
                    </label>

                    <input
                        id="otp"
                        name="otp"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        required
                        placeholder="000000"
                        value="{{ old('otp') }}"
                        class="w-full min-h-[56px] px-4 text-2xl font-semibold tracking-[0.45em] text-center bg-white border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-2 focus:ring-[#8B7355]/20 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                    @error('otp')
                        <p class="mt-2 text-xs text-center text-red-600 dark:text-red-400">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center w-full min-h-[48px] px-4 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-[#6F5430] to-[#8B7355] rounded-xl shadow-sm">

                    <i class="mr-2 fa-solid fa-shield-halved"></i>
                    Verify Code
                </button>

            </form>

            <form
                method="POST"
                action="{{ route('verification.send') }}"
                class="mt-4">

                @csrf

                <button
                    type="submit"
                    class="inline-flex items-center justify-center w-full min-h-[44px] px-4 py-2 text-sm font-medium border border-[#8B7355] text-[#6F5430] rounded-xl hover:bg-[#F8F5F1] dark:text-[#C4A97D] dark:hover:bg-gray-700">

                    <i class="mr-2 fa-solid fa-paper-plane"></i>
                    Resend Verification Email
                </button>
            </form>

            <p class="mt-4 text-xs leading-5 text-center text-gray-500 dark:text-gray-400">
                The verification code expires after 10 minutes.
                Resending the email creates a new code.
            </p>

            <div class="pt-5 mt-5 text-center border-t border-gray-200 dark:border-gray-700">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button
                        type="submit"
                        class="inline-flex items-center min-h-[44px] text-sm text-gray-500 hover:text-[#6F5430] dark:text-gray-400 dark:hover:text-[#C4A97D]">

                        <i class="mr-2 fa-solid fa-right-from-bracket"></i>
                        Log out and use another account
                    </button>
                </form>
            </div>

        </div>
    </section>

</main>

<script>
const otpInput = document.getElementById('otp');

otpInput.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
});

document.addEventListener('visibilitychange', function () {
    if (!document.hidden) {
        setTimeout(function () {
            window.location.reload();
        }, 1000);
    }
});
</script>

</body>
</html>
