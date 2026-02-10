<x-guest-layout>
    <x-auth-session-status class="mb-10" :status="session('status')" />

    <div class="grid grid-cols-1 overflow-hidden lg:grid-cols-2 rounded-2xl ">

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
                        Relax. Refresh.<br/>Renew.
                    </p>
                    <p class="max-w-sm mt-3 text-white/85">
                        Login to manage your spa, branches, and appointments.
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
                        <a href="{{ url('/') }}"
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
                        Welcome Back
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400">
                        Login to your account
                    </p>
                </div>

                <form method="POST" action="{{ route('login') }}" class="space-y-6">
                    @csrf

                    <!-- Email Address -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <x-input-label for="email" :value="__('Email')" class="text-sm font-medium text-gray-700 dark:text-gray-300" />
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                autofocus
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
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <x-text-input
                                id="password"
                                class="block w-full pl-10 bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 focus:border-[#8B7355] focus:ring-[#8B7355] transition-colors duration-200"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                placeholder="Enter your password"
                            />
                        </div>
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <label for="remember_me" class="inline-flex items-center cursor-pointer">
                            <div class="relative">
                                <input
                                    id="remember_me"
                                    type="checkbox"
                                    class="sr-only peer"
                                    name="remember"
                                >
                                <div class="w-5 h-5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded peer-checked:bg-[#8B7355] peer-checked:border-[#8B7355] transition-all duration-200 flex items-center justify-center">
                                    <svg class="hidden w-3 h-3 text-white peer-checked:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                            </div>
                            <span class="text-sm text-gray-600 ms-3 dark:text-gray-400 whitespace-nowrap">{{ __('Remember me') }}</span>
                        </label>

                        @if (Route::has('password.request'))
                            <a class="text-sm text-[#8B7355] hover:text-[#6F5430] dark:text-[#8B7355] dark:hover:text-[#6F5430] transition-colors duration-200 font-medium whitespace-nowrap"
                               href="{{ route('password.request') }}">
                                {{ __('Forgot password?') }}
                            </a>
                        @endif
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4">
                        <button type="submit"
                                class="w-full bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#6F5430] hover:to-[#5A4526] text-white font-medium py-3 px-4 rounded-lg transition-all duration-300 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-[#8B7355] focus:ring-offset-2 shadow-lg hover:shadow-xl">
                            <span class="flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                </svg>
                                {{ __('Login') }}
                            </span>
                        </button>
                    </div>

                    <!-- Register Link -->
                    <div class="pt-6 text-center border-t border-gray-200 dark:border-gray-800">
                        <p class="text-gray-600 dark:text-gray-400">
                            Don't have an account?
                            <a href="{{ route('register') }}" class="font-medium text-[#8B7355] hover:text-[#6F5430] dark:text-[#8B7355] dark:hover:text-[#6F5430] transition-colors duration-200 ml-1">
                                Create one now
                            </a>
                        </p>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-guest-layout>
