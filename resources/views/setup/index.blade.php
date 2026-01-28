<x-guest-layout>
    <div class="max-w-2xl mx-auto">
        <!-- Header -->
        <div class="relative mb-10 text-center">
            <a href="{{ url('/') }}" class="absolute left-0 inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-[#8B7355] dark:hover:text-[#8B7355] transition-colors duration-200">
                    <i class="fa-solid fa-arrow-left text-2xl text-[#8B7355]"></i>
                </a>

            <img
                src="{{ asset('images/1.png') }}"
                alt="Levictas"
                class="h-16 mx-auto mt-10 rounded-md"
            />

            <h1 class="mt-5 text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display'] mb-2">
                Set Up Your Spa Business
            </h1>

            <p class="text-gray-600 dark:text-gray-400">
                Let's get your business up and running
            </p>
        </div>

        <!-- Step Indicator -->
        <div class="mb-12">
            <div class="flex items-center justify-center">
                <!-- Step 1 -->
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-10 h-10 rounded-full bg-[#8B7355] text-white">
                        <i class="text-sm leading-none fa-solid fa-check"></i>
                    </div>

                    <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Business Info
                    </span>
                </div>


                <div class="w-24 h-1 mx-4 bg-gray-200 dark:bg-gray-700"></div>

                <!-- Step 2 -->
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-10 h-10 text-gray-400 bg-gray-200 rounded-full dark:bg-gray-700">
                        2
                    </div>
                    <span class="ml-3 text-sm font-medium text-gray-500 dark:text-gray-400">
                        Branches
                    </span>
                </div>

                <div class="w-24 h-1 mx-4 bg-gray-200 dark:bg-gray-700"></div>

                <!-- Step 3 -->
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-10 h-10 text-gray-400 bg-gray-200 rounded-full dark:bg-gray-700">
                        3
                    </div>
                    <span class="ml-3 text-sm font-medium text-gray-500 dark:text-gray-400">
                        Staff
                    </span>
                </div>
            </div>
        </div>

        <!-- Business Info Form -->
        <div class="p-8 bg-white rounded-lg shadow-lg dark:bg-gray-800">
            <form method="POST" action="{{ route('setup.store-spa') }}" class="space-y-6">
                @csrf

                <!-- Spa Name -->
                <div>
                    <label for="spa_name" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
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
