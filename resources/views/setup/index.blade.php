<x-guest-layout>
    <div class="px-4 py-6 sm:px-8 sm:py-8">
        <div class="max-w-3xl mx-auto">

            <div class="mb-6 text-center sm:mb-8">
                <img
                    src="{{ asset('images/1.png') }}"
                    alt="Levictas"
                    class="h-12 mx-auto rounded-md sm:h-14"
                />

                <h1 class="mt-4 text-2xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display'] sm:text-3xl">
                    Set Up Your Spa Business
                </h1>

                <p class="max-w-2xl mx-auto mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                    Start by entering your registered spa business name. You will add your main branch and submit verification documents in the next steps.
                </p>
            </div>

            <x-setup-stepper :current="1" />

            <form method="POST" action="{{ route('setup.store-spa') }}" class="space-y-6">
                @csrf

                <div>
                    <label for="spa_name" class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Spa Business Name <span class="text-red-600">*</span>
                    </label>

                    <input
                        type="text"
                        id="spa_name"
                        name="spa_name"
                        value="{{ old('spa_name', $spa?->name) }}"
                        required
                        placeholder="Enter your registered spa name"
                        class="w-full min-h-[44px] px-3 py-2 text-sm border rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-[#8B7355] focus:ring-2 focus:ring-[#8B7355]/20 focus:outline-none {{ $errors->has('spa_name') ? 'border-red-400' : 'border-gray-300 dark:border-gray-600' }}"
                    />

                    @error('spa_name')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex gap-3 p-4 border border-blue-200 bg-blue-50 rounded-2xl dark:bg-blue-900/10 dark:border-blue-800">
                    <i class="mt-0.5 text-blue-600 fa-solid fa-circle-info dark:text-blue-400" aria-hidden="true"></i>

                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-blue-800 dark:text-blue-300">
                            Verification documents are part of setup
                        </p>

                        <p class="mt-1 text-sm leading-6 text-blue-700 dark:text-blue-300">
                            In the final step you will submit one valid government ID, your DTI or SEC certificate, your BIR Certificate of Registration, and your Business Permit for administrator review.
                        </p>
                    </div>
                </div>

                <button
                    type="submit"
                    class="w-full min-h-[44px] bg-gradient-to-r from-[#7A6348] to-[#6F5430] text-white text-sm font-semibold py-3 px-4 rounded-xl transition-opacity shadow-sm hover:opacity-90"
                >
                    {{ $spa ? 'Save and Continue' : 'Continue' }}
                </button>
            </form>

        </div>
    </div>
</x-guest-layout>