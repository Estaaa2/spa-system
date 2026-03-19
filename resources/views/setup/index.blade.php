<x-guest-layout>
    <div class="p-8 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <!-- Header -->
        <div class="relative mb-10 text-center">

            <img
                src="{{ asset('images/1.png') }}"
                alt="Levictas"
                class="h-16 mx-auto mt-10 rounded-md"
            />

            <h1 class="mt-5 text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display'] mb-2">
                Set Up Your Spa Business
            </h1>

            <p class="max-w-2xl mx-auto mt-3 text-sm leading-6 text-gray-600 dark:text-gray-400 sm:text-base">
                Start by entering your spa business name. You can complete branch and staff setup in the next steps.
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
            <form method="POST" action="{{ route('setup.store-spa') }}" class="m-10 space-y-6">
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

                <!-- Verification Notice -->
                <div class="p-4 mt-6 border rounded-xl bg-amber-50 border-amber-200 dark:bg-amber-500/10 dark:border-amber-400/20">
                    <div class="flex gap-3">
                        <div class="mt-0.5 text-amber-600 dark:text-amber-400">
                            <i class="fa-solid fa-circle-info"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                                Verification documents will be required later
                            </p>
                            <p class="mt-1 text-sm leading-6 text-amber-700 dark:text-amber-200/90">
                                To verify your business and enable public listing, you will later need to upload:
                                <span class="font-medium">one valid government ID</span>,
                                <span class="font-medium">DTI or SEC certificate</span>, and
                                <span class="font-medium">BIR Certificate of Registration</span>.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-6">
                    <button
                        type="submit"
                        class="w-full bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#6F5430] hover:to-[#5A4526] text-white font-medium py-3 px-4 rounded-lg transition-all duration-300 transform hover:scale-[1.02]"
                    >
                        Continue
                    </button>
                </div>
            </form>
    </div>
</x-guest-layout>
