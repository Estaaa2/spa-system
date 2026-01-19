<x-guest-layout>
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-10">
            <h2 class="text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display'] mb-2">
                Set Up Branches
            </h2>
            <p class="text-gray-600 dark:text-gray-400">
                Add your spa locations
            </p>
        </div>
            <div class="grid grid-cols-1 gap-6">
                <!-- Add Branch Card -->
                <div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">
                            Add New Branch
                        </h3>

                        <form method="POST" action="{{ route('setup.store-branch') }}" class="space-y-4">
                            @csrf

                            <div>
                                <label for="branch_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
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
                                <label for="location" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Location *
                                </label>
                                <input
                                    type="text"
                                    id="location"
                                    name="location"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-[#8B7355] focus:outline-none"
                                    placeholder="City/Area"
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

                <!-- Branches List -->
                <div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">
                            Your Branches
                        </h3>

                        @if($branches->isEmpty())
                            <p class="text-gray-500 dark:text-gray-400">
                                Add at least one branch to continue.
                            </p>
                        @else
                            <div class="space-y-3">
                                @foreach($branches as $branch)
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                        <div class="flex justify-between items-start mb-3">
                                            <div>
                                                <h4 class="font-semibold text-gray-800 dark:text-white">
                                                    {{ $branch->name }}
                                                </h4>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $branch->location }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="flex gap-2">
                                            <a
                                                href="{{ route('setup.operating-hours', $branch) }}"
                                                class="text-sm px-3 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200"
                                            >
                                                Set Hours
                                            </a>
                                            <a
                                                href="{{ route('setup.staff', $branch) }}"
                                                class="text-sm px-3 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200"
                                            >
                                                Add Staff
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                                <a
                                    href="{{ route('setup.complete') }}"
                                    class="block text-center w-full bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#6F5430] hover:to-[#5A4526] text-white font-medium py-3 px-4 rounded-lg transition-all"
                                >
                                    Finish Setup & Go to Dashboard
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
