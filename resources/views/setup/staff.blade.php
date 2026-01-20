<x-guest-layout>
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-10">
            <div class="flex justify-between items-center mb-6">
                <a href="{{ route('setup.branches') }}" class="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-[#8B7355] dark:hover:text-[#8B7355] transition-colors duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Branches
                </a>
                <h2 class="text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display']">
                    Staff - {{ $branch->name }}
                </h2>
                <div class="w-20"></div>
            </div>
        </div>
            <div class="grid grid-cols-1 gap-6">
                <!-- Add Staff Card -->
                <div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">
                            Add New Staff
                        </h3>

                        <form method="POST" action="{{ route('setup.store-staff', $branch) }}" class="space-y-4">
                            @csrf

                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Full Name *
                                </label>
                                <input
                                    type="text"
                                    id="name"
                                    name="name"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-[#8B7355] focus:outline-none"
                                    placeholder="Staff name"
                                />
                                @error('name')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Email Address *
                                </label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-[#8B7355] focus:outline-none"
                                    placeholder="staff@example.com"
                                />
                                @error('email')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Role *
                                </label>
                                <select
                                    id="role"
                                    name="role"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-[#8B7355] focus:outline-none"
                                >
                                    <option value="">Select a role</option>
                                    <option value="manager">Manager</option>
                                    <option value="receptionist">Receptionist</option>
                                    <option value="therapist">Therapist</option>
                                </select>
                                @error('role')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                A temporary password will be generated and sent to their email.
                            </p>

                            <button
                                type="submit"
                                class="w-full bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#6F5430] hover:to-[#5A4526] text-white font-medium py-2 px-4 rounded-lg transition-all"
                            >
                                Create Staff Account
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Staff List -->
                <div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">
                            Branch Staff
                        </h3>

                        @if($branchUsers->isEmpty())
                            <p class="text-gray-500 dark:text-gray-400">
                                No staff members added yet.
                            </p>
                        @else
                            <div class="space-y-3">
                                @foreach($branchUsers as $user)
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h4 class="font-semibold text-gray-800 dark:text-white">
                                                    {{ $user->name }}
                                                </h4>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $user->email }}
                                                </p>
                                                <div class="mt-2 flex gap-2">
                                                    @forelse($user->getRoleNames() as $role)
                                                        <span class="inline-block px-2 py-1 text-xs font-semibold text-white bg-[#8B7355] rounded">
                                                            {{ ucfirst($role) }}
                                                        </span>
                                                    @empty
                                                        <span class="text-xs text-gray-500">No role assigned</span>
                                                    @endforelse
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
