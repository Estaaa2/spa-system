<x-guest-layout>

    <div class="mb-10 text-center">
            <div class="relative flex items-center justify-center mb-6">
                <!-- Back Button on Left -->
                <a href="{{ url('/login') }}" class="absolute left-0 inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-[#8B7355] dark:hover:text-[#8B7355] transition-colors duration-200">
                    <i class="fa-solid fa-arrow-left text-2xl text-[#8B7355]"></i>
                </a>

                <!-- Logo in Center -->
                <img
                    src="{{ asset('images/1.png') }}"
                    alt="Levictas"
                    class="w-auto h-16 mt-10 rounded-md"
                />
            </div>

            <h2 class="text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display'] mb-2">
                Forgot Password
            </h2>
            <p class="text-gray-600 dark:text-gray-400">
                {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
            </p>
        </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block w-full mt-1" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <button type="submit" class="w-full bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#6F5430] hover:to-[#5A4526] text-white font-medium py-3 px-4 rounded-lg transition-all duration-300 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-[#8B7355] focus:ring-offset-2 shadow-lg hover:shadow-xl">
                    <span class="flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                        {{ __('Email Password Reset Link') }}
                    </span>
                </button>
        </div>
    </form>
</x-guest-layout>
