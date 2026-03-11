    <div id="spaModal" class="fixed inset-0 z-[100] hidden">

        <!-- Overlay -->
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" data-close-spa-modal></div>

        <!-- Modal container -->
        <div class="relative mx-auto w-[92%] max-w-5xl mt-8 mb-8">
            <div class="overflow-hidden bg-white shadow-2xl rounded-3xl flex flex-col max-h-[90vh]">

                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b bg-white sticky top-0 z-10">
                    <div>
                        <h3 id="spaModalName" class="text-2xl font-bold text-gray-900 tracking-tight">Spa Name</h3>
                        <div class="flex items-center gap-1 text-sm text-gray-500 mt-0.5">
                            <span id="spaModalTag">New Branch</span>
                            <span class="mx-1">·</span>
                            <span id="spaModalAddressSummary" class="underline font-medium">Location</span>
                        </div>
                    </div>

                    <button data-close-spa-modal class="p-2 hover:bg-gray-100 rounded-full transition-colors text-gray-900">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="overflow-y-auto">

                    <!-- Photo gallery -->
                    <div class="p-6">
                        <div class="grid grid-cols-4 grid-rows-2 gap-2 h-[400px] rounded-2xl overflow-hidden">
                            <div class="col-span-2 row-span-2 bg-gray-200">
                                <img id="spaModalMainPhoto" src="" class="w-full h-full object-cover hover:opacity-90 transition-opacity cursor-pointer">
                            </div>
                            <div class="col-span-1 row-span-1 bg-gray-200">
                                <img id="gallery_1" class="w-full h-full object-cover hover:opacity-90 transition-opacity">
                            </div>
                            <div class="col-span-1 row-span-1 bg-gray-200">
                                <img id="gallery_2" class="w-full h-full object-cover hover:opacity-90 transition-opacity">
                            </div>
                            <div class="col-span-1 row-span-1 bg-gray-200">
                                <img id="gallery_3" class="w-full h-full object-cover hover:opacity-90 transition-opacity">
                            </div>
                            <div class="col-span-1 row-span-1 bg-gray-200 relative">
                                <img id="gallery_4" class="w-full h-full object-cover hover:opacity-90 transition-opacity">
                                <div id="spaModalGalleryCount" class="absolute inset-0 bg-black/40 flex items-center justify-center text-white font-semibold hidden">
                                    + View All
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="px-6 pb-6 grid md:grid-cols-3 gap-8">

                        <!-- Left / Main content -->
                        <div class="md:col-span-2 space-y-8">

                            <!-- About -->
                            <div>
                                <h4 class="text-xl font-semibold text-gray-900 mb-3">About this spa</h4>
                                <p id="spaModalDesc" class="text-gray-600 leading-relaxed"></p>
                            </div>

                            <hr class="border-gray-200">

                            <!-- Amenities -->
                            <div>
                                <h4 class="text-xl font-semibold text-gray-900 mb-4">What this place offers</h4>
                                <div id="spaModalAmenities" class="grid grid-cols-2 gap-y-4 text-gray-700"></div>
                            </div>

                        </div>

                        <!-- Right / Sidebar -->
                        <div class="md:col-span-1">
                            <div class="border rounded-2xl p-6 shadow-sm sticky top-4 space-y-4">

                                <!-- Address -->
                                <div class="flex items-start gap-3">
                                    <i class="fa-solid fa-location-dot mt-1 text-gray-900"></i>
                                    <div>
                                        <p class="font-semibold">Address</p>
                                        <p id="spaModalAddress" class="text-sm text-gray-500"></p>
                                    </div>
                                </div>

                                <!-- Contact -->
                                <div class="flex items-start gap-3">
                                    <i class="fa-solid fa-phone mt-1 text-gray-900"></i>
                                    <div>
                                        <p class="font-semibold">Contact</p>
                                        <p id="spaModalPhone" class="text-sm text-gray-500"></p>
                                    </div>
                                </div>

                                <!-- Map -->
                                <div id="spaModalMap" class="w-full h-[180px] rounded-xl border bg-gray-50 overflow-hidden"></div>

                                <!-- Reserve button -->
                                <button id="spaModalReserveBtn" class="w-full py-3.5 bg-[#8B7355] hover:bg-[#7A6145] text-white font-bold rounded-xl transition-colors shadow-md">
                                    Reserve Appointment
                                </button>

                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>


    OLD



    <div id="spaModal" class="fixed inset-0 z-[100] hidden">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" data-close-spa-modal></div>

        <!-- Modal Panel -->
        <div class="relative mx-auto w-[92%] max-w-4xl mt-10 sm:mt-16">
            <div class="overflow-hidden bg-white shadow-2xl rounded-3xl ring-1 ring-black/10">
                <!-- Header -->
                <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-black/5">
                <div class="min-w-0">
                    <h3 id="spaModalName" class="text-lg font-semibold text-[#3C2F23] truncate">Spa Name</h3>

                    <div class="flex flex-wrap items-center mt-1 text-xs text-gray-500 gap-x-2 gap-y-1">
                        <span id="spaModalMeta" class="truncate">Location • Rating</span>

                        <span class="text-gray-300">•</span>

                        <!-- ✅ Branch pill -->
                        <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-[#F6EFE6] ring-1 ring-black/5">
                            <i class="fa-solid fa-location-dot text-[#8B7355] text-xs"></i>

                            <!-- This will show only if selected branch is main -->
                            <span id="spaModalBranchBadge"
                                class="hidden text-[10px] font-semibold tracking-[0.18em] uppercase text-[#6F5430]">
                                Main
                            </span>

                            <!-- ✅ Select wrapper (hide native arrow, use our chevron) -->
                            <div class="relative">
                                <select
                                    id="spaModalBranchSelect"
                                    class="appearance-none bg-transparent border-0 p-0 pr-6 text-xs font-semibold text-[#6F5430] focus:ring-0 focus:outline-none cursor-pointer"
                                >
                                    <option value="">Select branch</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button"
                        class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5"
                        data-close-spa-modal aria-label="Close">
                    <i class="text-lg text-gray-700 fa-solid fa-xmark"></i>
                </button>
            </div>

                <!-- Content -->
                <div class="grid gap-0 md:grid-cols-12">
                    <!-- Photos (Top/Left) -->
                    <div class="md:col-span-7 bg-black/5">
                        <div class="relative">
                            <img id="spaModalMainPhoto" src="" alt="Spa photo" class="w-full h-[320px] md:h-[420px] object-cover">

                            <!-- Prev/Next -->
                            <button type="button" id="spaPrevPhoto"
                                    class="absolute flex items-center justify-center w-10 h-10 transition -translate-y-1/2 rounded-full shadow left-3 top-1/2 bg-white/90 ring-1 ring-black/10 hover:bg-white">
                                <i class="text-gray-800 fa-solid fa-chevron-left"></i>
                            </button>

                            <button type="button" id="spaNextPhoto"
                                    class="absolute flex items-center justify-center w-10 h-10 transition -translate-y-1/2 rounded-full shadow right-3 top-1/2 bg-white/90 ring-1 ring-black/10 hover:bg-white">
                                <i class="text-gray-800 fa-solid fa-chevron-right"></i>
                            </button>

                            <!-- Counter -->
                            <div class="absolute bottom-3 right-3 px-3 py-1.5 text-xs font-semibold text-white bg-black/45 rounded-full ring-1 ring-white/10">
                                <span id="spaPhotoCounter">1 / 1</span>
                            </div>
                        </div>

                        <!-- Thumbnails -->
                        <div id="spaModalThumbs" class="flex gap-2 p-4 overflow-x-auto bg-white border-t border-black/5">
                            <!-- injected by JS -->
                        </div>
                    </div>

                    <!-- Info (Bottom/Right) -->
                    <div class="p-6 md:col-span-5">
                        <div class="flex items-center justify-between">
                            <div class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-[#6F5430] bg-[#F6EFE6] rounded-full ring-1 ring-black/5">
                                <i class="fa-solid fa-spa"></i>
                                <span id="spaModalTag">Featured</span>
                            </div>

                            <div class="text-sm text-[#3C2F23]">
                                <i class="fa-solid fa-spa  text-[#D2A85B]"></i>
                                <span id="spaModalRating" class="font-semibold">4.8</span>
                                <span id="spaModalReviews" class="text-gray-500">(0)</span>
                            </div>
                        </div>

                        <p id="spaModalDesc" class="mt-4 text-sm leading-relaxed text-gray-600">
                            Description here...
                        </p>

                        <div class="mt-6 p-4 rounded-2xl bg-[#F6EFE6]/70 ring-1 ring-black/5">
                            <p class="text-sm text-gray-700">
                                <span id="spaModalPrice" class="font-semibold text-[#3C2F23]">From ₱0</span>
                                <span class="text-gray-500"> / session</span>
                            </p>

                            <button
                            type="button"
                            id="openBookingModalBtn"
                            class="block w-full mt-4 text-center booking-btn text-white py-3 rounded-xl text-sm font-semibold shadow-md hover:shadow-lg transition active:translate-y-0.5">
                            Reserve An Appointment
                            </button>
                        </div>

                        <!-- Optional: amenities placeholders (layout only) -->
                        <div class="mt-6">
                            <p class="text-xs font-semibold tracking-wide text-gray-700 uppercase">What this spa offers</p>
                            <div class="grid grid-cols-2 gap-3 mt-3 text-sm text-gray-600">
                                <div class="flex items-center gap-2"><i class="fa-solid fa-bath text-[#8B7355]"></i> Clean Rooms</div>
                                <div class="flex items-center gap-2"><i class="fa-solid fa-user-nurse text-[#8B7355]"></i> Pro Therapists</div>
                                <div class="flex items-center gap-2"><i class="fa-solid fa-mug-hot text-[#8B7355]"></i> Welcome Tea</div>
                                <div class="flex items-center gap-2"><i class="fa-solid fa-lock text-[#8B7355]"></i> Safe & Private</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile safe bottom spacing -->
            <div class="h-10"></div>
        </div>
    </div>