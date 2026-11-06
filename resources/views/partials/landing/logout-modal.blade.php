<!-- ================= LOGOUT CONFIRMATION MODAL ================= -->
<div id="logoutModal" class="fixed inset-0 z-[145] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeLogoutModal()"></div>
    <div class="relative w-[80%] max-w-sm">
        <div
            class="overflow-hidden bg-white shadow-2xl dark:bg-gray-800 rounded-3xl ring-1 ring-black/10 dark:ring-white/10">
            <div
                class="flex items-center justify-between px-6 py-4 border-b border-black/5 dark:border-white/10">
                <h3 class="text-lg font-semibold text-[#3C2F23] dark:text-white">Confirm Logout</h3>
                <button type="button" onclick="closeLogoutModal()"
                    class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5 dark:hover:bg-white/10">
                    <i class="text-lg text-gray-700 dark:text-gray-300 fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div
                        class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-red-100 rounded-full dark:bg-red-900/30">
                        <i class="text-xl text-red-600 dark:text-red-400 fa-solid fa-right-from-bracket"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-[#3C2F23] dark:text-white">Are you sure?</h4>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            You will be logged out of your account and redirected to the home page.
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex gap-2 px-6 pb-6">
                <button type="button" onclick="closeLogoutModal()"
                    class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-[#8B7355] dark:text-[#C4A97D] border border-[#8B7355] dark:border-[#C4A97D] hover:bg-[#F6EFE6] dark:hover:bg-gray-700 transition">
                    Cancel
                </button>
                <form method="POST" action="{{ route('logout') }}" class="flex-1">
                    @csrf
                    <button type="submit"
                        class="w-full py-2.5 rounded-xl text-sm font-semibold text-white bg-red-600 hover:bg-red-700 transition">
                        Yes, Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
