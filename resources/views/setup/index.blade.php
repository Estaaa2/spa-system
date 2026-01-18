<x-guest-layout>
    <div class="max-w-2xl mx-auto">
        <div class="text-center mb-10">
            <h1 class="text-4xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display'] mb-2">
                Set Up Your Spa Business
            </h1>
            <p class="text-gray-600 dark:text-gray-400">
                Let's get your business up and running
            </p>
        </div>

        <!-- Step Indicator -->
        <div class="mb-12">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-10 h-10 rounded-full bg-[#8B7355] text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Business Info</span>
                </div>
                <div class="flex-1 h-1 mx-4 bg-gray-200 dark:bg-gray-700"></div>
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-400">
                        2
                    </div>
                    <span class="ml-3 text-sm font-medium text-gray-500 dark:text-gray-400">Branches</span>
                </div>
                <div class="flex-1 h-1 mx-4 bg-gray-200 dark:bg-gray-700"></div>
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-400">
                        3
                    </div>
                    <span class="ml-3 text-sm font-medium text-gray-500 dark:text-gray-400">Staff</span>
                </div>
            </div>
        </div>

        <!-- Business Info Form -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
            <form method="POST" action="{{ route('setup.store-spa') }}" class="space-y-6">
                @csrf

                <!-- Spa Name -->
                <div>
                    <label for="spa_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Spa Business Name *
                    </label>
                    <input
                        type="text"
                        id="spa_name"
                        name="spa_name"
                        value="{{ old('spa_name') }}"
                        required
                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-[#8B7355] focus:ring-[#8B7355] focus:outline-none"
                        placeholder="Enter your spa name"
                    />
                    @error('spa_name')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email -->
                <div>
                    <label for="spa_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Spa Email Address
                    </label>
                    <input
                        type="email"
                        id="spa_email"
                        name="spa_email"
                        value="{{ old('spa_email') }}"
                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-[#8B7355] focus:ring-[#8B7355] focus:outline-none"
                        placeholder="business@example.com"
                    />
                    @error('spa_email')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Phone -->
                <div>
                    <label for="spa_phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Spa Phone Number
                    </label>
                    <input
                        type="tel"
                        id="spa_phone"
                        name="spa_phone"
                        value="{{ old('spa_phone') }}"
                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-[#8B7355] focus:ring-[#8B7355] focus:outline-none"
                        placeholder="+1 (555) 000-0000"
                    />
                    @error('spa_phone')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Description -->
                <div>
                    <label for="spa_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Business Description
                    </label>
                    <textarea
                        id="spa_description"
                        name="spa_description"
                        rows="4"
                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-[#8B7355] focus:ring-[#8B7355] focus:outline-none"
                        placeholder="Tell us about your spa..."
                    >{{ old('spa_description') }}</textarea>
                    @error('spa_description')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Submit Button -->
                <div class="pt-6">
                    <button
                        type="submit"
                        class="w-full bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#6F5430] hover:to-[#5A4526] text-white font-medium py-3 px-4 rounded-lg transition-all duration-300 transform hover:scale-[1.02]"
                    >
                        Continue to Branches Setup
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>
