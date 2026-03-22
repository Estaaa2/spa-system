<x-guest-layout>
    <div class="grid grid-cols-1 overflow-hidden lg:grid-cols-2 rounded-2xl">

        <!-- LEFT IMAGE PANEL -->
        <div class="relative hidden lg:block min-h-[640px]">
            <img
                src="{{ asset('images/face.jpeg') }}"
                alt="Spa"
                class="absolute inset-0 object-cover w-full h-full"
            />
            <div class="absolute inset-0 bg-black/25"></div>

            <div class="relative z-10 flex items-end h-full p-10">
                <div class="text-white">
                    <p class="text-3xl font-light font-['Playfair_Display'] leading-tight">
                        Reset Your<br/>Password.
                    </p>
                    <p class="max-w-sm mt-3 text-white/85">
                        Enter your email and we'll send you a link to get back into your account.
                    </p>
                </div>
            </div>
        </div>

        <!-- RIGHT FORM PANEL -->
        <div class="p-8 bg-white dark:bg-gray-800 sm:p-12">
            <div class="max-w-md mx-auto">

                <!-- Logo Section -->
                <div class="mb-10 text-center">
                    <div class="relative flex items-center justify-center mb-6">
                        <!-- Back Button on Left -->
                        <a href="{{ url('/login') }}"
                           class="absolute left-0 inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-[#8B7355] dark:hover:text-[#8B7355] transition-colors duration-200">
                            <i class="fa-solid fa-circle-chevron-left text-3xl text-[#8B7355]"></i>
                        </a>

                        <!-- Logo in Center -->
                        <img
                            src="{{ asset('images/1.png') }}"
                            alt="Levictas"
                            class="w-auto h-16 mt-2 rounded-md"
                        />
                    </div>

                    <h2 class="text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display'] mb-2">
                        Forgot Password
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400">
                        We'll email you a password reset link.
                    </p>
                </div>

                <form method="POST" action="{{ route('password.email') }}" class="space-y-6">
                    @csrf

                    <!-- Email Address -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <x-input-label for="email" :value="__('Email')" class="text-sm font-medium text-gray-700 dark:text-gray-300" />
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <x-text-input
                                id="email"
                                class="block w-full pl-10 bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 focus:border-[#8B7355] focus:ring-[#8B7355] transition-colors duration-200"
                                type="email"
                                name="email"
                                :value="old('email')"
                                required
                                autofocus
                                autocomplete="email"
                                placeholder="Enter your email"
                            />
                        </div>
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4">
                        <button type="submit"
                                class="w-full bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#6F5430] hover:to-[#5A4526] text-white font-medium py-3 px-4 rounded-lg transition-all duration-300 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-[#8B7355] focus:ring-offset-2 shadow-lg hover:shadow-xl">
                            <span class="flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                {{ __('Email Password Reset Link') }}
                            </span>
                        </button>
                    </div>

                    <!-- Back to Login -->
                    <div class="pt-6 text-center border-t border-gray-200 dark:border-gray-800">
                        <p class="text-gray-600 dark:text-gray-400">
                            Remembered your password?
                            <a href="{{ route('login') }}" class="font-medium text-[#8B7355] hover:text-[#6F5430] dark:text-[#8B7355] dark:hover:text-[#6F5430] transition-colors duration-200 ml-1">
                                Back to Login
                            </a>
                        </p>
                    </div>
                </form>

            </div>
        </div>
    </div>

    @push('toasts')
    @if (session('status'))
    <div
        id="statusToast"
        class="fixed top-6 right-6 z-[200] flex items-start gap-3 px-5 py-4 bg-white rounded-2xl shadow-2xl ring-1 ring-black/10 max-w-sm animate-fade-in"
        style="position: fixed; top: 1.5rem; right: 1.5rem;"
    >
        <div class="flex items-center justify-center flex-shrink-0 bg-green-100 w-9 h-9 rounded-xl">
            <i class="text-green-600 fa-solid fa-circle-check"></i>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-gray-800">Success</p>
            <p class="mt-0.5 text-xs text-gray-500">{{ session('status') }}</p>
        </div>
        <button onclick="document.getElementById('statusToast').remove()"
            class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition mt-0.5">
            <i class="text-sm fa-solid fa-xmark"></i>
        </button>
    </div>
    <script>
        setTimeout(() => {
            const toast = document.getElementById('statusToast');
            if (toast) toast.remove();
        }, 5000);
    </script>
    @endif
    @endpush
</x-guest-layout>
