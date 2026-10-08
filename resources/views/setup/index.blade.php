<x-guest-layout>
    <div class="p-8 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
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
                Start by entering your registered spa business name. You will add your main branch and submit verification documents in the next steps.
            </p>
        </div>

        <div class="mb-12">
            <div class="flex items-center justify-center overflow-x-auto">
                <div class="flex items-center min-w-max">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-[#8B7355] text-white">
                            1
                        </div>

                        <span class="ml-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Business Info
                        </span>
                    </div>

                    <div class="w-16 h-1 mx-4 bg-gray-200 rounded sm:w-24 dark:bg-gray-700"></div>

                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 text-gray-400 bg-gray-200 rounded-full dark:bg-gray-700 dark:text-gray-300">
                            2
                        </div>

                        <span class="ml-3 text-sm font-medium text-gray-500 dark:text-gray-400">
                            Main Branch
                        </span>
                    </div>

                    <div class="w-16 h-1 mx-4 bg-gray-200 rounded sm:w-24 dark:bg-gray-700"></div>

                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 text-gray-400 bg-gray-200 rounded-full dark:bg-gray-700 dark:text-gray-300">
                            3
                        </div>

                        <span class="ml-3 text-sm font-medium text-gray-500 dark:text-gray-400">
                            Documents
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('setup.store-spa') }}" class="m-10 space-y-6">
            @csrf

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
                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-[#8B7355] focus:ring-[#8B7355] focus:outline-none"
                    placeholder="Enter your registered spa name"
                />

                @error('spa_name')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="p-4 mt-6 border border-blue-200 bg-blue-50 rounded-2xl dark:bg-blue-900/10 dark:border-blue-800">
                <div class="flex gap-3">
                    <div class="mt-0.5 text-blue-600 dark:text-blue-400">
                        <i class="fa-solid fa-circle-info"></i>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-blue-800 dark:text-blue-300">
                            Verification documents are part of setup
                        </p>

                        <p class="mt-1 text-sm leading-6 text-blue-700 dark:text-blue-300">
                            In the final step you will submit one valid government ID, your DTI or SEC certificate, your BIR Certificate of Registration, and your Business Permit for administrator review.
                        </p>
                    </div>
                </div>
            </div>

            <div class="pt-6">
                <button
                    type="submit"
                    class="w-full min-h-[44px] bg-gradient-to-r from-[#7A6348] to-[#6F5430] hover:opacity-90 text-white font-medium py-3 px-4 rounded-xl transition-opacity"
                >
                    Continue
                </button>
            </div>
        </form>
    </div>
</x-guest-layout>
