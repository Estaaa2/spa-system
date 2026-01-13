<x-app-layout>

    <div class="min-h-screen flex">
        <div class="flex-1">
            <!-- Dashboard Content -->
            <main class="p-8">
                <div class="max-w-7xl mx-auto">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    </div>

                    <!-- Add more dashboard widgets/content below -->
                    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- Example Widget 1 -->
                        <div class="bg-gradient-to-r from-[#8B7355] to-[#6F5430] rounded-lg p-6 text-white shadow-lg">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm opacity-90">Total Appointments</p>
                                    <p class="text-3xl font-bold mt-2">12</p>
                                </div>
                                <div class="h-12 w-12 bg-white/20 rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Example Widget 2 -->
                        <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600">Services Available</p>
                                    <p class="text-3xl font-bold text-gray-800 mt-2">8</p>
                                </div>
                                <div class="h-12 w-12 bg-[#8B7355]/10 rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-[#8B7355]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Example Widget 3 -->
                        <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600">Members</p>
                                    <p class="text-3xl font-bold text-gray-800 mt-2">45</p>
                                </div>
                                <div class="h-12 w-12 bg-[#8B7355]/10 rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-[#8B7355]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5 4.5V21m-4.5-4.5H21" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>


        @php
                        // Retrieve the first role name assigned to the authenticated user
                        $role = auth()->user()->getRoleNames()->first();
                    @endphp

         @if($role)
                        <p class="mt-4">
                            {{ __("You're logged in as") }}
                            <strong class="text-green-600">{{ ucfirst($role) }}</strong>!
                        </p>
                    @else
                        <p class="mt-4 text-red-600">
                            {{ __("You're logged in but no role has been assigned.") }}
                        </p>
                    @endif

    </div>
</x-app-layout>
