<x-guest-layout>
    <div class="px-4 py-10">
        <div class="max-w-3xl mx-auto">

            <!-- Header -->
            <div class="relative mb-8 text-center">
                <a href="{{ url('/') }}"
                   class="absolute left-0 inline-flex items-center text-sm text-gray-600 hover:text-[#8B7355] transition-colors duration-200">
                    <i class="fa-solid fa-circle-chevron-left text-3xl text-[#8B7355]"></i>
                </a>

                <img
                    src="{{ asset('images/1.png') }}"
                    alt="Levictas"
                    class="mx-auto rounded-md h-14"
                />

                <h2 class="mt-3 text-3xl font-light text-[#2D3748] font-['Playfair_Display']">
                    Set Up Branches
                </h2>
                <p class="mt-1 text-sm text-gray-500">Add your spa locations</p>
            </div>

            <!-- TWO COLUMN LAYOUT -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">

                <!-- LEFT: Add New Branch -->
                <div class="lg:col-span-4">
                    <div class="p-6 bg-white shadow-sm rounded-2xl ring-1 ring-black/5">
                        <h3 class="mb-4 text-base font-semibold text-[#3C2F23]">
                            Add New Branch
                        </h3>

                        <form method="POST" action="{{ route('setup.store-branch') }}" class="space-y-4">
                            @csrf

                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-600">
                                    Branch Name *
                                </label>
                                <input
                                    type="text"
                                    name="branch_name"
                                    required
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-white text-gray-900 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none"
                                    placeholder="e.g., Downtown Branch"
                                />
                                @error('branch_name')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block mb-1 text-xs font-semibold text-gray-600">
                                    Location *
                                </label>
                                <input
                                    type="text"
                                    name="location"
                                    required
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-white text-gray-900 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none"
                                    placeholder="City / Area"
                                />
                                @error('location')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Has Home Service Toggle --}}
                            <div class="flex items-center gap-3 p-3 rounded-xl bg-[#F6EFE6] ring-1 ring-black/5">
                                <input
                                    type="checkbox"
                                    name="has_home_service"
                                    id="has_home_service"
                                    value="1"
                                    class="w-4 h-4 rounded text-[#8B7355] border-gray-300 focus:ring-[#8B7355]/40"
                                />
                                <label for="has_home_service" class="text-xs font-semibold text-gray-700 cursor-pointer">
                                    This branch offers <span class="text-[#6F5430]">Home Service</span>
                                </label>
                            </div>

                            <button
                                type="submit"
                                class="w-full mt-2 bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#6F5430] hover:to-[#5A4526] text-white text-sm font-semibold py-2.5 px-4 rounded-xl transition-all shadow-sm hover:shadow-md active:translate-y-0.5"
                            >
                                Add Branch
                            </button>
                        </form>
                    </div>
                </div>

                <!-- RIGHT: Your Branches -->
                <div class="lg:col-span-8">
                    <div class="p-6 bg-white shadow-sm rounded-2xl ring-1 ring-black/5">
                        <h3 class="mb-4 text-base font-semibold text-[#3C2F23]">
                            Your Branches
                        </h3>

                        @if($branches->isEmpty())
                            <div class="py-10 text-center text-gray-400">
                                <i class="mb-2 text-3xl text-gray-300 fa-solid fa-location-dot"></i>
                                <p class="text-sm">No branches yet. Add at least one to continue.</p>
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach($branches as $branch)
                                    <div class="p-4 border border-black/5 rounded-xl bg-[#F6EFE6]/50 ring-1 ring-black/5">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <h4 class="text-sm font-semibold text-[#3C2F23]">
                                                    {{ $branch->name }}
                                                </h4>
                                                <p class="mt-0.5 text-xs text-gray-500">
                                                    {{ $branch->location }}
                                                </p>
                                                @if($branch->has_home_service)
                                                    <span class="inline-flex items-center gap-1 mt-1.5 px-2 py-0.5 text-[10px] font-semibold text-[#6F5430] bg-[#F6EFE6] rounded-full ring-1 ring-black/5">
                                                        <i class="fa-solid fa-house-chimney text-[#8B7355]"></i>
                                                        Home Service
                                                    </span>
                                                @endif
                                            </div>

                                            <div class="flex gap-2">
                                                <a href="{{ route('setup.operating-hours', $branch) }}"
                                                   class="px-3 py-1.5 text-xs font-semibold text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 transition ring-1 ring-blue-100">
                                                    <i class="mr-1 fa-solid fa-clock"></i> Hours
                                                </a>
                                                <a href="{{ route('setup.staff', $branch) }}"
                                                   class="px-3 py-1.5 text-xs font-semibold text-green-700 bg-green-50 rounded-lg hover:bg-green-100 transition ring-1 ring-green-100">
                                                    <i class="mr-1 fa-solid fa-user-plus"></i> Staff
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="pt-5 mt-5 border-t border-black/5">
                                <a href="{{ route('setup.complete') }}"
                                   class="block w-full text-center bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:from-[#6F5430] hover:to-[#5A4526] text-white text-sm font-semibold py-3 px-4 rounded-xl transition-all shadow-sm hover:shadow-md active:translate-y-0.5">
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
