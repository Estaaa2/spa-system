<x-guest-layout>
    <div class="max-w-5xl mx-auto lg:px-0">
        <!-- Header -->
        <div class="relative mb-5 text-center">
            <a href="{{ url('/') }}" class="absolute left-0 inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-[#8B7355] dark:hover:text-[#8B7355] transition-colors duration-200">
                    <i class="fa-solid fa-arrow-left text-2xl text-[#8B7355]"></i>
                </a>
            <img
                src="{{ asset('images/1.png') }}"
                alt="Levictas"
                class="h-16 mx-auto mt-10 rounded-md"
            />

            <h2 class="mt-3 text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display'] mb-2">
                Set Up Branches
            </h2>

            <p class="text-gray-600 dark:text-gray-400">
                Add your spa locations
            </p>
        </div>

        <!-- Cards Layout -->
        <div class="grid grid-cols-1 gap-8">
            <!-- Add New Branch (LEFT) -->
            <div class="w-full">
                <div class="w-full p-6 bg-white rounded-lg shadow-lg dark:bg-gray-800">
                    <h3 class="mb-2 text-lg font-semibold text-gray-800 dark:text-white">
                        Add New Branch
                    </h3>

                    <form method="POST" action="{{ route('setup.store-branch') }}" class="space-y-4">
                        @csrf

                        <div>
                            <label for="branch_name" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Branch Name *
                            </label>
                            <input
                                type="text"
                                id="branch_name"
                                name="branch_name"
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-[#8B7355] focus:outline-none"
                                placeholder="e.g., Downtown Branch"
                            />
                            @error('branch_name')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="location" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Location *
                            </label>
                            <input
                                type="text"
                                id="location"
                                name="location"
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-[#8B7355] focus:outline-none"
                                placeholder="City / Area"
                            />
                            @error('location')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <button
                            type="submit"
                            class="w-full bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#6F5430] hover:to-[#5A4526] text-white font-medium py-2 px-4 rounded-lg transition-all"
                        >
                            Add Branch
                        </button>
                    </form>
                </div>
            </div>

            <!-- Your Branches (RIGHT) -->
            <div class="w-full">
                <div class="w-full p-6 bg-white rounded-lg shadow-lg dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">
                        Your Branches
                    </h3>

                    @if($branches->isEmpty())
                        <p class="text-gray-500 dark:text-gray-400">
                            Add at least one branch to continue.
                        </p>
                    @else
                        <div class="space-y-3">
                            @foreach($branches as $branch)
                                <div class="p-4 border border-gray-200 rounded-lg dark:border-gray-700">
                                    <h4 class="font-semibold text-gray-800 dark:text-white">
                                        {{ $branch->name }}
                                    </h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $branch->location }}
                                    </p>

                                    <div class="flex gap-2 mt-3">
                                        <a
                                            href="{{ route('setup.operating-hours', $branch) }}"
                                            class="px-3 py-1 text-sm text-blue-700 bg-blue-100 rounded hover:bg-blue-200"
                                        >
                                            Set Hours
                                        </a>
                                        <a
                                            href="{{ route('setup.staff', $branch) }}"
                                            class="px-3 py-1 text-sm text-green-700 bg-green-100 rounded hover:bg-green-200"
                                        >
                                            Add Staff
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                            <a
                                href="{{ route('setup.complete') }}"
                                class="block w-full text-center bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#6F5430] hover:to-[#5A4526] text-white font-medium py-3 px-4 rounded-lg transition-all"
                            >
                                Finish Setup & Go to Dashboard
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
