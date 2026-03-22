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
                        Almost There.<br/>New Password.
                    </p>
                    <p class="max-w-sm mt-3 text-white/85">
                        Choose a strong new password to secure your account.
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
                        Reset Password
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400">
                        Enter your new password below.
                    </p>
                </div>

                <form method="POST" action="{{ route('password.store') }}" class="space-y-6">
                    @csrf

                    <!-- Password Reset Token -->
                    <input type="hidden" name="token" value="{{ $request->route('token') }}">

                    <!-- Email Address -->
                    <div>
                        <x-input-label for="email" :value="__('Email')" class="text-sm font-medium text-gray-700 dark:text-gray-300" />
                        <div class="relative mt-2">
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
                                :value="old('email', $request->email)"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="Enter your email"
                            />
                        </div>
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <!-- New Password -->
                    <div>
                        <x-input-label for="password" :value="__('New Password')" class="text-sm font-medium text-gray-700 dark:text-gray-300" />
                        <div class="relative mt-2">
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
                                autocomplete="new-password"
                                placeholder="Enter new password"
                            />
                        </div>
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <x-input-label for="password_confirmation" :value="__('Confirm Password')" class="text-sm font-medium text-gray-700 dark:text-gray-300" />
                        <div class="relative mt-2">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <x-text-input
                                id="password_confirmation"
                                class="block w-full pl-10 bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 focus:border-[#8B7355] focus:ring-[#8B7355] transition-colors duration-200"
                                type="password"
                                name="password_confirmation"
                                required
                                autocomplete="new-password"
                                placeholder="Confirm new password"
                            />
                        </div>
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4">
                        <button type="submit"
                                class="w-full bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#6F5430] hover:to-[#5A4526] text-white font-medium py-3 px-4 rounded-lg transition-all duration-300 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-[#8B7355] focus:ring-offset-2 shadow-lg hover:shadow-xl">
                            <span class="flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                                {{ __('Reset Password') }}
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
</x-guest-layout>
