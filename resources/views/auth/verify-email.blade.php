<x-guest-layout>
    <div class="grid grid-cols-1 overflow-hidden lg:grid-cols-2 rounded-2xl">

        <!-- LEFT IMAGE PANEL -->
        <div class="relative hidden lg:block min-h-[560px]">
            <img
                src="{{ asset('images/face.jpeg') }}"
                alt="Spa"
                class="absolute inset-0 object-cover w-full h-full"
            />
            <div class="absolute inset-0 bg-black/25"></div>
            <div class="relative z-10 flex items-end h-full p-10">
                <div class="text-white">
                    <p class="text-3xl font-light font-['Playfair_Display'] leading-tight">
                        One Last Step.
                    </p>
                    <p class="max-w-sm mt-3 text-white/85">
                        Verify your email to keep your account secure.
                    </p>
                </div>
            </div>
        </div>

        <!-- RIGHT PANEL -->
        <div class="p-8 bg-white dark:bg-gray-800 sm:p-12">
            <div class="max-w-md mx-auto">

                <!-- Logo -->
                <div class="mb-10 text-center">
                    <img src="{{ asset('images/1.png') }}" alt="Levictas" class="h-16 mx-auto mb-6 rounded-md">
                    <h2 class="text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display'] mb-2">
                        Verify Your Email
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400">
                        Thanks for signing up!
                    </p>
                </div>

                <!-- Icon -->
                <div class="flex justify-center mb-6">
                    <div class="flex items-center justify-center w-20 h-20 rounded-full bg-amber-50 dark:bg-amber-900/20">
                        <i class="text-4xl fa-solid fa-envelope text-[#8B7355] animate-pulse"></i>
                    </div>
                </div>

                <!-- Info text -->
                <div class="mb-4 text-sm text-center text-gray-600 dark:text-gray-400">
                    We sent a verification link to
                    <strong class="text-gray-800 dark:text-white">{{ Auth::user()->email }}</strong>.
                    <br class="mt-1">
                    Click the link in your inbox to activate your account.
                </div>

                <!-- Auto reload notice -->
                <div class="flex items-center justify-center gap-2 mb-6 text-xs text-gray-400 dark:text-gray-500">
                    <i class="fa-solid fa-rotate fa-spin text-[#8B7355]"></i>
                    <span>This page will refresh automatically to check your verification status.</span>
                </div>

                <!-- Success on resend -->
                @if (session('status') == 'verification-link-sent')
                    <div class="p-4 mb-6 text-sm text-green-700 border border-green-200 rounded-lg bg-green-50 dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
                        <i class="mr-2 fa-solid fa-circle-check"></i>
                        A new verification link has been sent to your email address.
                    </div>
                @endif

                <!-- Resend -->
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit"
                        class="w-full bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#6F5430] hover:to-[#5A4526] text-white font-medium py-3 px-4 rounded-lg transition-all duration-300 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-[#8B7355] focus:ring-offset-2 shadow-lg">
                        <i class="mr-2 fa-solid fa-paper-plane"></i>
                        Resend Verification Email
                    </button>
                </form>

                <!-- Logout -->
                <div class="mt-6 text-center">
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit"
                            class="text-sm text-gray-500 hover:text-[#8B7355] dark:text-gray-400 dark:hover:text-[#8B7355] transition-colors">
                            <i class="mr-1 fa-solid fa-right-from-bracket"></i>
                            Log out and use a different account
                        </button>
                    </form>
                </div>

                <p class="mt-6 text-xs text-center text-gray-400 dark:text-gray-500">
                    Didn't receive the email? Check your spam folder, or click resend above.
                </p>

            </div>
        </div>
    </div>

    <script>
        // Simply reload the page every 5 seconds.
        // When the page reloads, Laravel's 'verified' middleware will
        // catch the verified status from the database and redirect properly.
        // This avoids all session/cookie issues with fetch-based polling.
        setTimeout(function () {
            window.location.reload();
        }, 5000);
    </script>
</x-guest-layout>
