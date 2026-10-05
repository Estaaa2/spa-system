<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Login | Levictas Spa & Wellness</title>

    <link rel="icon" type="image/png" href="{{ asset('images/1.png') }}">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen antialiased bg-[#F8F5F1] dark:bg-gray-900">

<main class="min-h-screen lg:grid lg:grid-cols-2">

    <section class="relative hidden overflow-hidden lg:block">
        <img src="{{ asset('images/face.jpeg') }}"
            alt="Spa"
            class="absolute inset-0 object-cover w-full h-full">

        <div class="absolute inset-0 bg-black/30"></div>

        <div class="relative z-10 flex items-end min-h-screen p-12">
            <div class="max-w-md text-white">
                <h1 class="text-4xl font-light font-['Playfair_Display']">
                    Relax. Refresh.<br>Renew.
                </h1>

                <p class="mt-4 leading-7 text-white/85">
                    Sign in to manage appointments, spa operations,
                    or your personal wellness experience.
                </p>
            </div>
        </div>
    </section>

    <section class="flex items-center min-h-screen px-4 py-8 bg-white sm:px-8 dark:bg-gray-800">

        <div class="w-full max-w-md mx-auto">

            <div class="relative flex items-center justify-center mb-6">

                <a href="{{ url('/') }}"
                    aria-label="Return to home"
                    class="absolute left-0 inline-flex items-center justify-center min-h-[44px] min-w-[44px] text-[#6F5430] rounded-xl hover:bg-[#F8F5F1] dark:text-[#C4A97D] dark:hover:bg-gray-700">

                    <i class="text-xl fa-solid fa-chevron-left"></i>
                </a>

                <img
                    src="{{ asset('images/1.png') }}"
                    alt="Levictas"
                    class="w-auto rounded-md h-14">
            </div>

            <div class="mb-8 text-center">
                <h2 class="text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display']">
                    Welcome back
                </h2>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Sign in to your Levictas account.
                </p>
            </div>

            @if(session('status'))
                <div class="p-4 mb-5 text-sm border text-emerald-700 border-emerald-200 rounded-xl bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300">
                    <i class="mr-2 fa-solid fa-circle-check"></i>
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST"
                action="{{ route('login') }}"
                class="space-y-5">

                @csrf

                <div>
                    <label for="email"
                        class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Email
                    </label>

                    <div class="relative">
                        <i class="absolute text-gray-400 -translate-y-1/2 fa-solid fa-envelope left-3 top-1/2"></i>

                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="Enter your email"
                            class="w-full min-h-[44px] py-2 pr-3 text-sm bg-white border border-gray-300 rounded-xl pl-10 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>

                    @error('email')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password"
                        class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Password
                    </label>

                    <div class="relative">
                        <i class="absolute text-gray-400 -translate-y-1/2 fa-solid fa-lock left-3 top-1/2"></i>

                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            placeholder="Enter your password"
                            class="w-full min-h-[44px] py-2 pr-12 text-sm bg-white border border-gray-300 rounded-xl pl-10 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                        <button
                            type="button"
                            data-target="password"
                            aria-label="Show password"
                            tabindex="-1"
                            class="toggle-password absolute inset-y-0 right-0 inline-flex items-center justify-center min-w-[44px] text-[#8B7355]">

                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>

                    @error('password')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between gap-3">

                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input
                            id="remember_me"
                            name="remember"
                            type="checkbox"
                            class="w-5 h-5 rounded border-gray-300 text-[#6F5430] focus:ring-[#8B7355]">

                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            Remember me
                        </span>
                    </label>

                    @if(Route::has('password.request'))
                        <a
                            href="{{ route('password.request') }}"
                            class="text-sm font-medium text-[#6F5430] hover:text-[#8B7355] dark:text-[#C4A97D]">
                            Forgot password?
                        </a>
                    @endif
                </div>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center w-full min-h-[48px] px-4 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-[#6F5430] to-[#8B7355] rounded-xl shadow-sm hover:from-[#5A4526] hover:to-[#7A6348]">

                    <i class="mr-2 fa-solid fa-right-to-bracket"></i>
                    Login
                </button>

                <div class="pt-4 text-center border-t border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Don't have an account?

                        <a
                            href="{{ route('register') }}"
                            class="ml-1 font-medium text-[#6F5430] dark:text-[#C4A97D]">
                            Create one now
                        </a>
                    </p>
                </div>

            </form>
        </div>
    </section>

</main>

<script>
document.querySelectorAll('.toggle-password').forEach(function (button) {
    button.addEventListener('click', function () {
        const target = document.getElementById(button.dataset.target);
        const icon = button.querySelector('i');

        if (!target || !icon) return;

        if (target.type === 'password') {
            target.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
            button.setAttribute('aria-label', 'Hide password');
        } else {
            target.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
            button.setAttribute('aria-label', 'Show password');
        }
    });
});
</script>

</body>
</html>
