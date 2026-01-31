<x-guest-layout>
    <div class="max-w-4xl mx-auto">
        <div class="relative mb-10 text-center">
            <!-- Back Button -->
            <a
                href="{{ url('setup/branches/') }}"
                class="absolute left-0 top-1/2 -translate-y-1/2 inline-flex items-center text-gray-600 dark:text-gray-400 hover:text-[#8B7355] dark:hover:text-[#8B7355] transition-colors duration-200"
            >
                <i class="fa-solid fa-circle-chevron-left text-3xl text-[#8B7355]"></i>
            </a>

            <!-- Logo -->
            <img
                src="{{ asset('images/1.png') }}"
                alt="Levictas"
                class="h-16 mx-auto mt-10 rounded-md"
            />

            <!-- Title -->
            <h2 class="mt-4 text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display']">
                Staff - {{ $branch->name }}
            </h2>
        </div>

            <div class="grid grid-cols-1 gap-6">
                <!-- Add Staff Card -->
                <div>
                    <div class="p-6 bg-white rounded-lg shadow-lg dark:bg-gray-800">
                        <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">
                            Add New Staff
                        </h3>

                        <form method="POST" action="{{ route('setup.store-staff', $branch) }}" class="space-y-4">
                            @csrf

                            <div>
                                <label for="name" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
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
                                <label for="email" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
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
                                <label for="role" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
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
                    <div class="p-6 bg-white rounded-lg shadow-lg dark:bg-gray-800">
                        <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">
                            Branch Staff
                        </h3>

                        @if($branchUsers->isEmpty())
                            <p class="text-gray-500 dark:text-gray-400">
                                No staff members added yet.
                            </p>
                        @else
                            <div class="space-y-3">
                                @foreach($branchUsers as $user)
                                    <div class="p-4 border border-gray-200 rounded-lg dark:border-gray-700">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <h4 class="font-semibold text-gray-800 dark:text-white">
                                                    {{ $user->name }}
                                                </h4>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $user->email }}
                                                </p>
                                                <div class="flex gap-2 mt-2">
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
