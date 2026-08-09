<x-guest-layout>
    <div class="px-4 py-6">
        <div class="max-w-4xl mx-auto">

            <!-- Header -->
            <div class="relative mb-8 text-center">
                <a href="{{ url('setup/branches/') }}"
                   class="absolute left-0 inline-flex items-center text-sm text-gray-600 hover:text-[#8B7355] transition-colors duration-200">
                    <i class="fa-solid fa-circle-chevron-left text-3xl text-[#8B7355]"></i>
                </a>

                <img src="{{ asset('images/1.png') }}" alt="Levictas" class="mx-auto rounded-md h-14"/>

                <h2 class="mt-3 text-3xl font-light text-[#2D3748] font-['Playfair_Display']">
                    Staff Management
                </h2>
                <p class="mt-1 text-sm font-medium text-[#6F5430]">{{ $branch->name }}</p>
            </div>

            <!-- TWO COLUMN LAYOUT -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">

                <!-- LEFT: Add New Staff -->
                <div class="lg:col-span-4">
                    <div class="p-6 bg-white shadow-sm rounded-2xl ring-1 ring-black/5">
                        <h3 class="mb-4 text-base font-semibold text-[#3C2F23]">
                            Add New Staff
                        </h3>

                        <form method="POST" action="{{ route('setup.store-staff', $branch) }}" class="space-y-4">
                            @csrf

                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-600">Full Name *</label>
                                <input
                                    type="text"
                                    name="first_name"
                                    required
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-white text-gray-900 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none"
                                    placeholder="First name" />
                                <input
                                    type="text"
                                    name="last_name"
                                    required
                                    class="w-full mt-2 px-3 py-2 text-sm border border-gray-200 rounded-xl bg-white text-gray-900 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none"
                                    placeholder="Last name" />

                                @error('first_name')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                                @error('last_name')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-600">Email Address *</label>
                                <input
                                    type="email"
                                    name="email"
                                    required
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-white text-gray-900 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none"
                                    placeholder="staff@example.com"
                                />
                                @error('email')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-600">Role *</label>
                                <select
                                    name="role"
                                    required
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-white text-gray-900 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none"
                                >
                                    <option value="">Assign Staff Role</option>
                                    <option value="manager">Manager</option>
                                    <option value="receptionist">Receptionist</option>
                                    <option value="therapist">Therapist</option>
                                </select>
                                @error('role')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <p class="text-[11px] text-gray-400">
                                A temporary password will be generated and sent to their email.
                            </p>

                            <button
                                type="submit"
                                class="w-full bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#6F5430] hover:to-[#5A4526] text-white text-sm font-semibold py-2.5 px-4 rounded-xl transition-all shadow-sm hover:shadow-md active:translate-y-0.5"
                            >
                                Create Staff Account
                            </button>
                        </form>
                    </div>
                </div>

                <!-- RIGHT: Staff List -->
                <div class="lg:col-span-8">
                    <div class="p-6 bg-white shadow-sm rounded-2xl ring-1 ring-black/5">
                        <h3 class="mb-4 text-base font-semibold text-[#3C2F23]">
                            Branch Staff
                        </h3>

                        @if($branchUsers->isEmpty())
                            <div class="py-10 text-center text-gray-400">
                                <i class="mb-2 text-3xl text-gray-300 fa-solid fa-users"></i>
                                <p class="text-sm">No staff members added yet.</p>
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach($branchUsers as $user)
                                    <div class="p-4 bg-[#F6EFE6]/50 rounded-xl ring-1 ring-black/5">
                                        <div class="flex items-start justify-between">
                                            <div class="flex items-center gap-3">
                                                <div class="flex items-center justify-center w-9 h-9 rounded-full bg-[#8B7355] text-white text-sm font-semibold shrink-0">
                                                    {{ strtoupper(substr($user->first_name, 0, 1)) }}
                                                </div>
                                                <div>
                                                    <h4 class="text-sm font-semibold text-[#3C2F23]">{{ $user->first_name }} {{ $user->last_name }}</h4>
                                                    <p class="text-xs text-gray-500">{{ $user->email }}</p>
                                                    <div class="flex gap-2 mt-1.5">
                                                        @forelse($user->getRoleNames() as $role)
                                                            <span class="inline-block px-2 py-0.5 text-[10px] font-semibold text-white bg-[#8B7355] rounded-full">
                                                                {{ ucfirst($role) }}
                                                            </span>
                                                        @empty
                                                            <span class="text-xs text-gray-400">No role assigned</span>
                                                        @endforelse
                                                    </div>
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
