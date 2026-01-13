<x-guest-layout>
    <div class="max-w-md mx-auto">
        <!-- Logo Section -->
        <div class="text-center mb-10">
            <div class="flex items-center justify-between mb-6">
                <!-- Back Button on Left -->
                <a href="{{ url('/') }}" class="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-[#8B7355] dark:hover:text-[#8B7355] transition-colors duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back
                </a>

                <!-- Logo and Text in Center -->
                <div class="flex items-center justify-center flex-grow ml-10">
                    <img src="{{ asset('images/1.png') }}" alt="Levictas" class="h-16 w-auto rounded-md mr-3 mt-10">
                </div>

                <!-- Empty div for balance -->
                <div class="w-20"></div>
            </div>
            <h2 class="text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display'] mb-2">
                Create Account
            </h2>
            <p class="text-gray-600 dark:text-gray-400">
                Sign up for your Levictas account
            </p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-6">
            @csrf

            <!-- Name -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <x-input-label for="name" :value="__('Name')" class="text-sm font-medium text-gray-700 dark:text-gray-300" />
                </div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <x-text-input
                        id="name"
                        class="block w-full pl-10 bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 focus:border-[#8B7355] focus:ring-[#8B7355] transition-colors duration-200"
                        type="text"
                        name="name"
                        :value="old('name')"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="Enter your full name"
                    />
                </div>
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <!-- Email Address -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <x-input-label for="email" :value="__('Email')" class="text-sm font-medium text-gray-700 dark:text-gray-300" />
                </div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                        </svg>
                    </div>
                    <x-text-input
                        id="email"
                        class="block w-full pl-10 bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 focus:border-[#8B7355] focus:ring-[#8B7355] transition-colors duration-200"
                        type="email"
                        name="email"
                        :value="old('email')"
                        required
                        autocomplete="username"
                        placeholder="Enter your email"
                    />
                </div>
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <!-- Password -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <x-input-label for="password" :value="__('Password')" class="text-sm font-medium text-gray-700 dark:text-gray-300" />
                </div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <x-text-input
                        id="password"
                        class="block w-full pl-10 bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 focus:border-[#8B7355] focus:ring-[#8B7355] transition-colors duration-200"
                        type="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        placeholder="Enter your password"
                    />
                </div>
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <!-- Confirm Password -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <x-input-label for="password_confirmation" :value="__('Confirm Password')" class="text-sm font-medium text-gray-700 dark:text-gray-300" />
                </div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <x-text-input
                        id="password_confirmation"
                        class="block w-full pl-10 bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 focus:border-[#8B7355] focus:ring-[#8B7355] transition-colors duration-200"
                        type="password"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Confirm your password"
                    />
                </div>
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <!-- Submit Button -->
            <div class="pt-4">
                <button type="submit" class="w-full bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#6F5430] hover:to-[#5A4526] text-white font-medium py-3 px-4 rounded-lg transition-all duration-300 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-[#8B7355] focus:ring-offset-2 shadow-lg hover:shadow-xl">
                    <span class="flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        {{ __('Register') }}
                    </span>
                </button>
            </div>

            <!-- Login Link -->
            <div class="text-center pt-6 border-t border-gray-200 dark:border-gray-800">
                <p class="text-gray-600 dark:text-gray-400">
                    Already have an account?
                    <a href="{{ route('login') }}" class="font-medium text-[#8B7355] hover:text-[#6F5430] dark:text-[#8B7355] dark:hover:text-[#6F5430] transition-colors duration-200 ml-1">
                        Sign in now
                    </a>
                </p>
            </div>
        </form>
    </div>
</x-guest-layout>
