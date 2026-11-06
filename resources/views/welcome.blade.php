<!DOCTYPE html>
<html lang="en">

<head>
    @include('partials.landing.head', [
        'entries' => ['resources/css/app.css', 'resources/css/landing.css', 'resources/js/app.js', 'resources/js/welcome.js'],
    ])
</head>

@php
    // Guarded: a plain function declaration crashes the second render in one
    // PHP process (tests, queue workers). Not used anywhere at the moment.
    if (! function_exists('getImageUrl')) {
    function getImageUrl($path)
    {
        if (empty($path)) {
            return \App\Models\BranchProfile::fallbackPhotoUrl();
        }

        // If already has branch_profiles in path
        if (str_contains($path, 'branch_profiles/')) {
            return asset('storage/' . $path);
        }

        // Add branch_profiles to the path
        return asset('storage/branch_profiles/' . $path);
    }
    }
@endphp

{{-- data-fallback-image lets welcome.js read the asset URL without needing inline Blade --}}

<body
    class="bg-[#F6EFE6] dark:bg-gray-900 text-gray-800 dark:text-gray-100 selection:bg-[#D2A85B]/30 selection:text-[#3C2F23]"
    data-fallback-image="{{ \App\Models\BranchProfile::fallbackPhotoUrl() }}">

    @include('partials.landing.nav')

    <main class="pt-20">
        <section class="relative overflow-hidden">
            <div class="absolute inset-0">
                <img src="{{ asset('images/heads.png') }}" class="object-cover w-full h-full" alt="Hero">
                <div class="absolute inset-0 bg-black/45"></div>
                <div
                    class="absolute inset-0 bg-gradient-to-b from-black/30 via-black/35 to-[#F6EFE6] dark:to-gray-900">
                </div>
            </div>

            <div class="relative px-6 py-24 mx-auto text-center max-w-7xl">
                <div class="max-w-3xl mx-auto">
                    <p
                        class="inline-flex items-center gap-2 px-4 py-2 text-xs tracking-[0.2em] uppercase text-white/90 bg-white/10 rounded-full ring-1 ring-white/10">
                        <i class="fa-solid fa-spa text-white/80"></i>
                        Wellness • Relaxation • Care
                    </p>
                    <h1
                        class="mt-6 text-4xl md:text-6xl font-['Playfair_Display'] text-white font-semibold leading-tight">
                        Find and Book the Best Spa
                    </h1>
                    <p class="mt-4 text-lg md:text-xl text-white/90">
                        Relaxation, wellness, and pampering — made easy.
                    </p>
                </div>

                <form action="{{ url('/') }}" method="GET" id="spaSearchForm"
                    class="flex items-stretch max-w-2xl gap-1 p-2 mx-auto mt-10 bg-white rounded-full shadow-2xl dark:bg-gray-800 ring-1 ring-black/5 dark:ring-white/10">
                    <div class="relative flex-1 px-5 py-2 text-left search-segment" id="placeSegment">
                        <label
                            class="block text-[10px] font-bold uppercase tracking-wide text-[#8B7355] dark:text-[#C4A97D]">Place</label>
                        <input type="text" id="placeInput" name="place" value="{{ $place ?? '' }}"
                            placeholder="Anywhere in Cavite" autocomplete="off"
                            class="w-full text-sm text-gray-800 bg-transparent border-0 focus:ring-0 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500">
                        <div id="placeDropdown" class="search-dropdown">
                            <p class="search-dropdown-label">Cities &amp; municipalities</p>
                            <div id="placeChips" class="flex flex-wrap gap-2"></div>
                        </div>
                    </div>

                    <div class="w-px my-2 bg-black/10 dark:bg-white/10"></div>

                    <div class="relative flex-1 px-5 py-2 text-left search-segment" id="treatmentSegment">
                        <label
                            class="block text-[10px] font-bold uppercase tracking-wide text-[#8B7355] dark:text-[#C4A97D]">Treatment</label>
                        <input type="text" id="treatmentInput" name="treatment" value="{{ $treatment ?? '' }}"
                            placeholder="Any treatment" autocomplete="off"
                            class="w-full text-sm text-gray-800 bg-transparent border-0 focus:ring-0 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500">
                        <div id="treatmentDropdown" class="search-dropdown">
                            <p class="search-dropdown-label">Suggested treatments</p>
                            <div id="treatmentSuggestionList"></div>
                        </div>
                    </div>

                    <button type="submit" title="Search"
                        class="booking-btn flex-shrink-0 flex items-center justify-center w-11 h-11 self-center text-white rounded-full shadow-lg hover:opacity-95 transition active:translate-y-0.5">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                </form>

                {{-- Treatment suggestions are real data (names actually offered today); embedded once
                 so the pill can filter them client-side with zero network calls per keystroke. --}}
                <script id="treatmentSuggestionsData" type="application/json">{!! json_encode($treatmentSuggestions ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>
            </div>

            <div class="h-10 bg-gradient-to-b from-transparent to-[#F6EFE6]"></div>
        </section>

        <!-- Profile window (UI rework Phase 5) =========================================
             Lives outside <nav>: the nav's backdrop-filter makes it the containing
             block for position:fixed, which squeezed the old window into the bar.
             Same shell as Booking details: a sheet on phones, a dialog from sm up.
             Opens read-only; each row (name, phone, home location) has its own
             editor that replaces the main view, with Back and its own Save. The
             home location is only changed in its editor, and GPS never writes to
             it on its own. Filled and saved by the "PROFILE WINDOW" block in
             welcome.js; every class it toggles is written out here. -->
        @auth
            @php
                $pfUser  = auth()->user();
                $pfPhoto = $pfUser->profilePhotoUrl();
                $pfData  = [
                    'first_name'  => $pfUser->first_name,
                    'middle_name' => $pfUser->middle_name,
                    'last_name'   => $pfUser->last_name,
                    'suffix'      => $pfUser->suffix,
                    'name'        => $pfUser->name,
                    'phone'       => $pfUser->phone,
                    'address'     => $pfUser->address,
                    'latitude'    => $pfUser->latitude !== null ? (float) $pfUser->latitude : null,
                    'longitude'   => $pfUser->longitude !== null ? (float) $pfUser->longitude : null,
                    'save_url'    => route('customer.profile.update'),
                ] + \App\Http\Controllers\ProfileController::nameRule($pfUser);
                $pfInput = 'w-full mt-1.5 min-h-[44px] rounded-xl border-black/15 text-sm text-[#3C2F23] placeholder:text-gray-400 dark:border-white/15 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-500 focus:border-[#8B7355] focus:ring-2 focus:ring-[#8B7355]/30 dark:focus:border-[#C4A97D] dark:focus:ring-[#C4A97D]/30';
                $pfLabel = 'block text-sm font-medium text-[#3C2F23] dark:text-gray-100';
                $pfError = 'hidden mt-1 text-xs text-red-700 dark:text-red-400';
            @endphp
            <script id="pfData" type="application/json">{!! json_encode($pfData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>

            <div id="profileModal" class="fixed inset-0 z-[130] hidden" role="dialog" aria-modal="true" aria-labelledby="pfTitle">
                <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="pfBackdropClick()"></div>
                <div class="absolute inset-x-0 bottom-0 pointer-events-none sm:inset-0 sm:flex sm:items-center sm:justify-center sm:p-4">
                    <div id="pfPanel"
                        class="bd-panel pointer-events-auto flex flex-col w-full sm:max-w-lg overflow-hidden bg-white dark:bg-gray-800 shadow-2xl rounded-t-3xl sm:rounded-3xl ring-1 ring-black/10 dark:ring-white/10">
                        {{-- overflow-hidden: the brown header must follow the panel's rounded corners. --}}

                        <!-- Editor bar (hidden in the main view, which has the warm header) -->
                        <div id="pfBar" class="hidden items-center gap-2 px-4 py-3 border-b shrink-0 sm:px-6 border-black/5 dark:border-white/10">
                            <button type="button" id="pfBackBtn" onclick="pfShowView('main')" aria-label="Back to your profile"
                                class="hidden items-center justify-center -ml-2 w-11 h-11 shrink-0 transition rounded-xl text-gray-700 dark:text-gray-300 hover:bg-black/5 dark:hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] dark:focus-visible:ring-[#C4A97D]">
                                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                            </button>
                            <h2 id="pfTitle" tabindex="-1" class="flex-1 text-lg font-semibold text-[#3C2F23] dark:text-white focus:outline-none">Your profile</h2>
                            <button type="button" onclick="closeProfileModal()" aria-label="Close"
                                class="flex items-center justify-center -mr-2 w-11 h-11 shrink-0 transition rounded-xl text-gray-700 dark:text-gray-300 hover:bg-black/5 dark:hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] dark:focus-visible:ring-[#C4A97D]">
                                <i class="text-lg fa-solid fa-xmark" aria-hidden="true"></i>
                            </button>
                        </div>

                        <div id="pfBody" class="flex-1 min-h-0 overflow-y-auto overscroll-contain">

                            <!-- ===== Main view: read-only, warm header (option B) ===== -->
                            <div id="pfMain">
                                <div class="pf-hero relative px-4 pt-7 pb-6 text-center text-white sm:px-6">
                                    <button type="button" onclick="closeProfileModal()" aria-label="Close"
                                        class="absolute flex items-center justify-center transition top-2 right-2 w-11 h-11 rounded-xl bg-white/10 hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                                        <i class="text-lg fa-solid fa-xmark" aria-hidden="true"></i>
                                    </button>

                                    <div class="relative mx-auto w-[76px] h-[76px]">
                                        @if ($pfPhoto)
                                            <img src="{{ $pfPhoto }}" alt="Your profile photo" class="object-cover w-[76px] h-[76px] rounded-full ring-2 ring-white/40">
                                        @else
                                            <span aria-hidden="true" class="flex items-center justify-center w-[76px] h-[76px] text-3xl font-semibold rounded-full bg-white/20 ring-2 ring-inset ring-white/35">{{ strtoupper(substr($pfUser->name ?? 'C', 0, 1)) }}</span>
                                        @endif
                                        {{-- Photo forms post as before (the page reloads and this window reopens). --}}
                                        <form method="POST" action="{{ route('profile.photo.update') }}" enctype="multipart/form-data" id="customerPhotoForm">
                                            @csrf
                                            <label title="{{ $pfPhoto ? 'Change photo' : 'Add photo' }}"
                                                class="absolute flex items-center justify-center w-8 h-8 transition bg-white rounded-full shadow cursor-pointer -right-1 -bottom-1 text-[#6F5430] hover:bg-[#F6EFE6] focus-within:ring-2 focus-within:ring-white focus-within:ring-offset-2 focus-within:ring-offset-[#8B7355]">
                                                <i class="text-xs fa-solid fa-camera" aria-hidden="true"></i>
                                                <span id="customerPhotoLabel" class="sr-only">{{ $pfPhoto ? 'Change photo' : 'Add photo' }}</span>
                                                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only"
                                                    data-max-mb="{{ (int) round(config('identity.upload.max_kb', 5120) / 1024) }}"
                                                    onchange="submitCustomerPhoto(this)">
                                            </label>
                                        </form>
                                    </div>

                                    <p id="pfNameHeading" tabindex="-1" class="mt-3 text-xl font-semibold leading-snug font-['Playfair_Display'] break-words focus:outline-none">{{ $pfUser->name }}</p>
                                    @if ($pfUser->hasVerifiedEmail())
                                        <p class="inline-flex items-center gap-1.5 px-2.5 py-1 mt-2.5 text-xs font-semibold rounded-full bg-white/15">
                                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i> Email verified
                                        </p>
                                    @else
                                        <p class="inline-flex items-center gap-1.5 px-2.5 py-1 mt-2.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-900">
                                            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> Email not verified
                                        </p>
                                    @endif
                                    <p class="mt-2.5 text-xs text-white/85">Photo is optional. Spas and therapists don't see it.</p>
                                    @if ($pfPhoto)
                                        <form method="POST" action="{{ route('profile.photo.destroy') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center min-h-[44px] -mb-3 px-2 text-xs font-semibold underline underline-offset-2 text-white/90 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white rounded-lg">Remove photo</button>
                                        </form>
                                    @endif
                                </div>

                                @if ($errors->hasBag('photo') && $errors->getBag('photo')->any())
                                    <div role="alert" class="flex items-start gap-2 p-3 mx-4 mt-4 text-sm text-red-700 rounded-2xl bg-red-50 ring-1 ring-red-200 sm:mx-6 dark:bg-red-900/20 dark:ring-red-800 dark:text-red-300">
                                        <i class="mt-0.5 fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                                        <span>Your photo wasn't saved. {{ $errors->getBag('photo')->first() }}</span>
                                    </div>
                                @endif

                                <!-- Personal information -->
                                <section aria-labelledby="pfInfoTitle">
                                    <h3 id="pfInfoTitle" class="pf-section-title">
                                        <i class="fa-solid fa-user" aria-hidden="true"></i> Personal information
                                    </h3>
                                    <dl class="divide-y divide-black/5 dark:divide-white/10">
                                        <div class="flex items-start gap-3 px-4 py-3 sm:px-6">
                                            <div class="flex-1 min-w-0">
                                                <dt class="text-xs text-gray-600 dark:text-gray-400">Name</dt>
                                                <dd id="pfRowName" class="mt-0.5 text-sm font-medium text-[#3C2F23] dark:text-white break-words"></dd>
                                                {{-- Once every 30 days, with 24 hours to fix a typo; filled by welcome.js. --}}
                                                <dd id="pfNameNote" class="hidden mt-0.5 text-xs text-gray-600 dark:text-gray-400"></dd>
                                            </div>
                                            <button type="button" id="pfNameEdit" onclick="pfShowView('name')" aria-label="Edit name" class="pf-edit">Edit</button>
                                            <span id="pfNameLock" class="pf-locked hidden" title="You can't change your name yet">
                                                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                                <span class="sr-only">Can't be changed yet</span>
                                            </span>
                                        </div>
                                        <div class="flex items-start gap-3 px-4 py-3 sm:px-6">
                                            <div class="flex-1 min-w-0">
                                                <dt class="text-xs text-gray-600 dark:text-gray-400">Email</dt>
                                                <dd class="mt-0.5 text-sm font-medium text-[#3C2F23] dark:text-white break-all">{{ $pfUser->email }}</dd>
                                                <dd class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">To change your email, contact Levictas support.</dd>
                                            </div>
                                            {{-- Not a button: the email can't be edited here. --}}
                                            <span class="pf-locked" title="Your email can't be changed here">
                                                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                                <span class="sr-only">Can't be changed here</span>
                                            </span>
                                        </div>
                                        <div class="flex items-start gap-3 px-4 py-3 sm:px-6">
                                            <div class="flex-1 min-w-0">
                                                <dt class="text-xs text-gray-600 dark:text-gray-400">Phone</dt>
                                                <dd id="pfRowPhone" class="mt-0.5 text-sm font-medium text-[#3C2F23] dark:text-white"></dd>
                                            </div>
                                            <button type="button" onclick="pfShowView('phone')" aria-label="Edit phone" class="pf-edit">Edit</button>
                                        </div>
                                    </dl>
                                </section>

                                <!-- Home location -->
                                <section aria-labelledby="pfLocTitle" class="border-t border-black/5 dark:border-white/10">
                                    <h3 id="pfLocTitle" class="pf-section-title">
                                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i> Home location
                                    </h3>
                                    <div class="px-4 pt-1 pb-4 sm:px-6">
                                        {{-- Preview only: it can't be clicked or dragged. --}}
                                        <div id="pfMapPreviewWrap" class="hidden mb-3">
                                            <div id="pfMapPreview" aria-hidden="true" class="pf-map-preview"></div>
                                        </div>
                                        <div class="flex items-start gap-3">
                                            <div class="flex-1 min-w-0">
                                                <p id="pfRowLocation" class="text-sm font-medium text-[#3C2F23] dark:text-white break-words"></p>
                                                <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">Fills in the address when you book a home service.</p>
                                            </div>
                                            <button type="button" onclick="pfShowView('location')" aria-label="Edit home location" class="pf-edit">Edit</button>
                                        </div>
                                    </div>
                                </section>

                                <!-- Find spas near (this device) -->
                                <section aria-labelledby="pfNearbyTitle" class="px-4 pt-4 pb-5 border-t sm:px-6 border-black/5 dark:border-white/10">
                                    <h3 id="pfNearbyTitle" class="text-sm font-semibold text-[#3C2F23] dark:text-white">Find spas near</h3>
                                    <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">This choice is remembered on this device only. Your home location above is saved to your account.</p>
                                    <div class="pf-seg mt-2" role="radiogroup" aria-labelledby="pfNearbyTitle">
                                        <label class="pf-seg-opt">
                                            <input type="radio" name="pfNearbyFrom" value="home" class="sr-only" onchange="pfSetNearbyFrom('home')">
                                            <i class="fa-solid fa-house" aria-hidden="true"></i><span>My home</span>
                                        </label>
                                        <label class="pf-seg-opt">
                                            <input type="radio" name="pfNearbyFrom" value="here" class="sr-only" onchange="pfSetNearbyFrom('here')">
                                            <i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i><span>Where I am now</span>
                                        </label>
                                    </div>
                                    <p id="pfNearbyHomeHint" class="mt-2 text-xs text-gray-600 dark:text-gray-400"></p>
                                    <p id="pfNearbyStatus" class="hidden mt-1 text-xs" role="status"></p>
                                </section>
                            </div>

                            <!-- ===== Editor: name ===== -->
                            <div id="pfEditName" class="hidden px-4 py-5 space-y-4 sm:px-6">
                                <p id="pfNameRuleHint" class="flex items-start gap-2 p-3 text-xs text-gray-700 rounded-2xl bg-[#F6EFE6]/70 dark:bg-gray-900/40 dark:text-gray-300">
                                    <i class="mt-0.5 fa-solid fa-circle-info text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                                    <span id="pfNameRuleText"></span>
                                </p>
                                <div>
                                    <label for="pfFirstName" class="{{ $pfLabel }}">First name</label>
                                    <input type="text" id="pfFirstName" autocomplete="given-name" maxlength="255" class="{{ $pfInput }}" aria-describedby="pfErr-first_name">
                                    <p id="pfErr-first_name" class="{{ $pfError }}"></p>
                                </div>
                                <div>
                                    <label for="pfMiddleName" class="{{ $pfLabel }}">Middle name <span class="font-normal text-gray-600 dark:text-gray-400">(optional)</span></label>
                                    <input type="text" id="pfMiddleName" autocomplete="additional-name" maxlength="255" class="{{ $pfInput }}" aria-describedby="pfErr-middle_name">
                                    <p id="pfErr-middle_name" class="{{ $pfError }}"></p>
                                </div>
                                <div>
                                    <label for="pfLastName" class="{{ $pfLabel }}">Last name</label>
                                    <input type="text" id="pfLastName" autocomplete="family-name" maxlength="255" class="{{ $pfInput }}" aria-describedby="pfErr-last_name">
                                    <p id="pfErr-last_name" class="{{ $pfError }}"></p>
                                </div>
                                <div>
                                    <label for="pfSuffix" class="{{ $pfLabel }}">Suffix <span class="font-normal text-gray-600 dark:text-gray-400">(optional)</span></label>
                                    <input type="text" id="pfSuffix" autocomplete="honorific-suffix" maxlength="20" placeholder="Jr., Sr., III" class="{{ $pfInput }}" aria-describedby="pfErr-suffix">
                                    <p id="pfErr-suffix" class="{{ $pfError }}"></p>
                                </div>
                            </div>

                            <!-- ===== Editor: phone ===== -->
                            <div id="pfEditPhone" class="hidden px-4 py-5 space-y-2 sm:px-6">
                                <label for="pfPhone" class="{{ $pfLabel }}">Mobile number</label>
                                <input type="tel" id="pfPhone" inputmode="numeric" autocomplete="tel" maxlength="11" placeholder="09171234567"
                                    class="{{ $pfInput }}" aria-describedby="pfPhoneHint pfErr-phone">
                                <p id="pfErr-phone" class="{{ $pfError }}"></p>
                                <p id="pfPhoneHint" class="text-xs text-gray-600 dark:text-gray-400">11 digits starting with 09. Used for booking updates and to speed up GCash/Maya checkout.</p>
                            </div>

                            <!-- ===== Editor: home location ===== -->
                            <div id="pfEditLocation" class="hidden px-4 py-5 space-y-4 sm:px-6">
                                <button type="button" id="pfUseHere" onclick="pfUseCurrentLocation()"
                                    class="inline-flex items-center justify-center w-full gap-2 min-h-[44px] px-4 text-sm font-semibold transition border rounded-xl text-[#3C2F23] border-black/15 hover:bg-[#F6EFE6]/70 dark:text-white dark:border-white/15 dark:hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] dark:focus-visible:ring-[#C4A97D]">
                                    <i class="fa-solid fa-location-crosshairs text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                                    <span>Use my current location</span>
                                </button>

                                <div class="flex items-center gap-3 text-xs text-gray-600 dark:text-gray-400" aria-hidden="true">
                                    <span class="flex-1 h-px bg-black/10 dark:bg-white/10"></span>or<span class="flex-1 h-px bg-black/10 dark:bg-white/10"></span>
                                </div>

                                <div>
                                    <label for="address" class="{{ $pfLabel }}">Address</label>
                                    <div class="flex gap-2 mt-1.5">
                                        <input type="text" id="address" autocomplete="street-address" maxlength="255"
                                            placeholder="Barangay, subdivision or street"
                                            class="{{ str_replace('mt-1.5 ', '', $pfInput) }}" aria-describedby="pfAddressHint pfErr-address">
                                        <button type="button" id="pfSearchBtn" onclick="pfSearchAddress()"
                                            class="inline-flex items-center justify-center gap-1.5 min-h-[44px] px-4 text-sm font-semibold transition border shrink-0 rounded-xl text-[#3C2F23] border-black/15 hover:bg-[#F6EFE6]/70 dark:text-white dark:border-white/15 dark:hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] dark:focus-visible:ring-[#C4A97D] disabled:opacity-60">
                                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Search</span>
                                        </button>
                                    </div>
                                    <p id="pfErr-address" class="{{ $pfError }}"></p>
                                    <p id="pfAddressHint" class="mt-1 text-xs text-gray-600 dark:text-gray-400">Search, then confirm the pin. You can add a house number or landmark after.</p>
                                    <ul id="pfSearchResults" class="hidden mt-2 overflow-hidden border divide-y rounded-xl border-black/10 divide-black/5 dark:border-white/10 dark:divide-white/10" aria-label="Search results"></ul>
                                </div>

                                <div>
                                    <div class="relative">
                                        <div id="map" class="pf-map-edit" aria-label="Map. Tap to place your pin, or drag it to adjust."></div>
                                    </div>
                                    <p id="pfLocStatus" class="mt-2 text-xs text-gray-600 dark:text-gray-400" role="status">Tap the map to place your pin, then drag it to fine-tune. Cavite only.</p>
                                    <p id="pfErr-latitude" class="{{ $pfError }}"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Footer: only in an editor -->
                        <div id="pfFooter" class="hidden bd-footer px-4 pt-3 border-t shrink-0 sm:px-6 border-black/5 dark:border-white/10">
                            <div class="flex gap-2">
                                <button type="button" onclick="pfShowView('main')"
                                    class="inline-flex items-center justify-center flex-1 min-h-[48px] px-4 text-sm font-semibold transition bg-white border rounded-xl text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] dark:focus-visible:ring-[#C4A97D]">
                                    Cancel
                                </button>
                                <button type="button" id="pfSaveBtn" onclick="pfSave()"
                                    class="inline-flex items-center justify-center flex-[2] gap-2 min-h-[48px] px-4 text-sm font-semibold text-white transition shadow-md rounded-xl booking-btn hover:shadow-lg disabled:opacity-60 disabled:cursor-not-allowed focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B7355] dark:focus-visible:ring-[#C4A97D] dark:focus-visible:ring-offset-gray-800">
                                    <span id="pfSaveLabel">Save</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endauth

        <!-- ================= MY APPOINTMENTS (sheet on phones, dialog from sm) =================
             UI rework Phase 2. Same shell as Booking details: header, tabs and a
             scrolling list. Rows are built in welcome.js (renderTab) and styled by
             the .apt-* rules in landing.css, because welcome.js isn't scanned by
             Tailwind. Tab state is styled off aria-selected (design language §8). -->
        <div id="appointmentsModal" class="fixed inset-0 z-[120] hidden" role="dialog" aria-modal="true"
            aria-labelledby="apTitle">
            <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeAppointmentsModal()"></div>
            <div class="absolute inset-x-0 bottom-0 pointer-events-none sm:inset-0 sm:flex sm:items-center sm:justify-center sm:p-4">
                <div id="apPanel"
                    class="ap-panel pointer-events-auto flex flex-col w-full sm:max-w-xl bg-white dark:bg-gray-800 shadow-2xl rounded-t-3xl sm:rounded-3xl ring-1 ring-black/10 dark:ring-white/10">
                    <div class="flex items-center justify-between gap-2 px-4 py-3 border-b shrink-0 sm:px-6 border-black/5 dark:border-white/10">
                        <h2 id="apTitle" tabindex="-1" class="text-lg font-semibold text-[#3C2F23] dark:text-white focus:outline-none">My appointments</h2>
                        <button type="button" onclick="closeAppointmentsModal()" aria-label="Close"
                            class="flex items-center justify-center -mr-2 w-11 h-11 shrink-0 transition rounded-xl text-gray-700 dark:text-gray-300 hover:bg-black/5 dark:hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] dark:focus-visible:ring-[#C4A97D]">
                            <i class="text-lg fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="flex px-2 border-b shrink-0 sm:px-4 border-black/5 dark:border-white/10" role="tablist" aria-label="Appointments">
                        @foreach (['upcoming' => 'Upcoming', 'past' => 'Past', 'cancelled' => 'Cancelled'] as $key => $label)
                            <button type="button" role="tab" id="tab-{{ $key }}" aria-controls="appointmentsContent"
                                aria-selected="{{ $key === 'upcoming' ? 'true' : 'false' }}" tabindex="{{ $key === 'upcoming' ? '0' : '-1' }}"
                                onclick="switchTab('{{ $key }}')" class="ap-tab">
                                {{ $label }}
                                <span id="tab-count-{{ $key }}" class="ap-tab-count hidden">0</span>
                            </button>
                        @endforeach
                    </div>
                    <div id="appointmentsContent" role="tabpanel" aria-labelledby="tab-upcoming"
                        class="ap-body flex-1 min-h-0 overflow-y-auto overscroll-contain">
                        <div class="flex items-center justify-center py-16">
                            <i class="text-2xl text-gray-300 dark:text-gray-600 fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                            <span class="sr-only">Loading your appointments</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= MY SCHEDULE (sheet on phones, dialog from sm) =================
             UI rework Phase 4. Same shell and rows as My appointments; this one is
             about the month: a list grouped by day, or a calendar with the chosen
             day's appointments under it. Built in welcome.js (renderSchedule) and
             styled by the .apt-* / .sc-* rules in landing.css. -->
        <div id="scheduleModal" class="fixed inset-0 z-[120] hidden" role="dialog" aria-modal="true"
            aria-labelledby="scTitle">
            <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeScheduleModal()"></div>
            <div class="absolute inset-x-0 bottom-0 pointer-events-none sm:inset-0 sm:flex sm:items-center sm:justify-center sm:p-4">
                <div id="scPanel"
                    class="ap-panel pointer-events-auto flex flex-col w-full sm:max-w-xl bg-white dark:bg-gray-800 shadow-2xl rounded-t-3xl sm:rounded-3xl ring-1 ring-black/10 dark:ring-white/10">
                    <div class="flex items-center justify-between gap-2 px-4 pt-3 shrink-0 sm:px-6">
                        <h2 id="scTitle" tabindex="-1" class="text-lg font-semibold text-[#3C2F23] dark:text-white focus:outline-none">My schedule</h2>
                        <button type="button" onclick="closeScheduleModal()" aria-label="Close"
                            class="flex items-center justify-center -mr-2 w-11 h-11 shrink-0 transition rounded-xl text-gray-700 dark:text-gray-300 hover:bg-black/5 dark:hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] dark:focus-visible:ring-[#C4A97D]">
                            <i class="text-lg fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>

                    <!-- Month and view controls -->
                    <div class="flex items-center justify-between gap-3 px-2 pb-3 border-b shrink-0 sm:px-4 border-black/5 dark:border-white/10">
                        <div class="flex items-center">
                            <button type="button" id="scPrevMonth" onclick="changeMonth(-1)" aria-label="Previous month" class="sc-icon-btn">
                                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                            </button>
                            <p id="calendarTitle" class="min-w-[8.5rem] text-center text-sm font-semibold text-[#3C2F23] dark:text-white" aria-live="polite"></p>
                            <button type="button" id="scNextMonth" onclick="changeMonth(1)" aria-label="Next month" class="sc-icon-btn">
                                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="sc-seg" role="group" aria-label="View">
                            <button type="button" id="scViewList" class="sc-seg-btn" aria-pressed="true" onclick="setScheduleView('list')">
                                <i class="fa-solid fa-list" aria-hidden="true"></i><span>List</span>
                            </button>
                            <button type="button" id="scViewCalendar" class="sc-seg-btn" aria-pressed="false" onclick="setScheduleView('calendar')">
                                <i class="fa-solid fa-calendar-days" aria-hidden="true"></i><span>Calendar</span>
                            </button>
                        </div>
                    </div>

                    <div id="scBody" class="ap-body flex-1 min-h-0 overflow-y-auto overscroll-contain">
                        <div id="scheduleListView">
                            <div id="scheduleListContent">
                                <div class="flex items-center justify-center py-16">
                                    <i class="text-2xl text-gray-300 dark:text-gray-600 fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                                    <span class="sr-only">Loading your schedule</span>
                                </div>
                            </div>
                        </div>
                        <div id="scheduleCalendarView" class="hidden">
                            <div class="sc-cal">
                                <div class="sc-weekdays" aria-hidden="true">
                                    @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                                        <span>{{ $day }}</span>
                                    @endforeach
                                </div>
                                <div id="calendarGrid" class="sc-grid"></div>
                            </div>
                            <div id="selectedDayBookings"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('partials.landing.logout-modal')

        <!-- ================= BOOKING DETAILS (sheet on phones, dialog from sm) =================
             One panel, two views: details and the reschedule form (UI rework Phase 1).
             The form used to be a second modal stacked on top; it now replaces the
             details view so sheets never stack. Filled by openBookingDetailsModal()
             in welcome.js. Every class welcome.js toggles is written out here so
             Tailwind generates it (welcome.js is not in Tailwind's content globs). -->
        <div id="bookingDetailsModal" class="fixed inset-0 z-[125] hidden" role="dialog" aria-modal="true"
            aria-labelledby="bdTitle">
            <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="bdBackdropClick()"></div>
            <div class="bd-frame absolute inset-x-0 bottom-0 sm:inset-0 sm:flex sm:items-center sm:justify-center sm:p-4 pointer-events-none">
                <div id="bdPanel"
                    class="bd-panel pointer-events-auto flex flex-col w-full sm:max-w-lg bg-white dark:bg-gray-800 shadow-2xl rounded-t-3xl sm:rounded-3xl ring-1 ring-black/10 dark:ring-white/10">

                    <!-- Header -->
                    <div class="flex items-center gap-2 px-4 py-3 border-b shrink-0 sm:px-6 border-black/5 dark:border-white/10">
                        <button type="button" id="bdBackBtn" onclick="closeRescheduleModal()" aria-label="Back to booking details"
                            class="hidden items-center justify-center -ml-2 w-11 h-11 shrink-0 transition rounded-xl text-gray-700 dark:text-gray-300 hover:bg-black/5 dark:hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] dark:focus-visible:ring-[#C4A97D]">
                            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                        </button>
                        <div class="flex flex-wrap items-center flex-1 min-w-0 gap-x-2 gap-y-1">
                            <h2 id="bdTitle" tabindex="-1" class="text-lg font-semibold text-[#3C2F23] dark:text-white focus:outline-none">Booking details</h2>
                            <span id="detailStatus" class="apt-pill"></span>
                        </div>
                        <button type="button" onclick="closeBookingDetailsModal()" aria-label="Close"
                            class="flex items-center justify-center -mr-2 w-11 h-11 shrink-0 transition rounded-xl text-gray-700 dark:text-gray-300 hover:bg-black/5 dark:hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] dark:focus-visible:ring-[#C4A97D]">
                            <i class="text-lg fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>

                    <!-- Scrolling body -->
                    <div id="bdBody" class="flex-1 min-h-0 overflow-y-auto overscroll-contain">

                        <!-- ===== View 1: details ===== -->
                        <div id="bdDetailsView" class="px-4 py-5 space-y-5 sm:px-6">

                            <!-- Summary: what, when, where -->
                            <div>
                                <p id="detailTreatment"
                                    class="text-2xl font-semibold leading-snug text-[#3C2F23] dark:text-white font-['Playfair_Display']"></p>
                                <p class="flex items-start gap-2 mt-2 text-sm font-medium text-[#3C2F23] dark:text-gray-100">
                                    <i class="fa-regular fa-calendar mt-0.5 text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                                    <span><span id="detailDate"></span><span aria-hidden="true"> · </span><span id="detailTime"></span></span>
                                </p>
                                <p class="flex items-start gap-2 mt-1.5 text-sm text-gray-600 dark:text-gray-300">
                                    <i class="fa-solid fa-location-dot mt-0.5 text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                                    <span id="detailSpaName"></span>
                                </p>
                                <p id="detailServiceRow" class="hidden items-start gap-2 mt-1.5 text-sm text-gray-600 dark:text-gray-300">
                                    <i class="fa-solid fa-house mt-0.5 text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                                    <span id="detailServiceType"></span>
                                </p>
                            </div>

                            <!-- Reschedule request state (shown when a request exists) -->
                            <div id="detailRescheduleStatus" class="hidden items-start gap-3 p-3 rounded-2xl ring-1">
                                <i id="detailRescheduleIcon" class="mt-0.5 fa-solid" aria-hidden="true"></i>
                                <p id="detailRescheduleStatusText" class="text-sm"></p>
                            </div>

                            <!-- Home service: check the person, then give the code (staff verification D6, D7) -->
                            <section id="detailArrival" aria-labelledby="detailArrivalTitle"
                                class="hidden p-4 rounded-2xl bg-[#F6EFE6]/70 ring-1 ring-[#8B7355]/15 dark:bg-gray-900/40 dark:ring-white/10">
                                <h3 id="detailArrivalTitle" class="text-sm font-semibold text-[#3C2F23] dark:text-white">When your therapist arrives</h3>

                                <ol class="mt-3 space-y-4">
                                    <li class="flex gap-3">
                                        <span aria-hidden="true"
                                            class="flex items-center justify-center w-6 h-6 text-xs font-semibold text-white rounded-full shrink-0 bg-[#7A6348] dark:bg-[#C4A97D] dark:text-gray-900">1</span>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-[#3C2F23] dark:text-gray-100">Check that it's the person in this photo</p>
                                            <div class="flex items-center gap-3 mt-2.5">
                                                <img id="detailTherapistPhoto" alt=""
                                                    class="hidden object-cover w-16 h-16 rounded-full shrink-0 ring-2 ring-white dark:ring-gray-800">
                                                <span id="detailTherapistInitials" aria-hidden="true"
                                                    class="hidden items-center justify-center w-16 h-16 text-xl font-semibold text-white rounded-full shrink-0 bg-[#7A6348]"></span>
                                                <span id="detailTherapistIcon" aria-hidden="true"
                                                    class="hidden items-center justify-center w-16 h-16 rounded-full shrink-0 bg-white text-gray-400 ring-1 ring-black/5 dark:bg-gray-800 dark:text-gray-500 dark:ring-white/10">
                                                    <i class="text-xl fa-solid fa-user"></i>
                                                </span>
                                                <div class="min-w-0">
                                                    <p id="detailTherapist" class="text-sm font-semibold text-[#3C2F23] dark:text-white"></p>
                                                    <span id="detailTherapistVerified"
                                                        class="hidden mt-1 items-center gap-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Identity verified
                                                    </span>
                                                    <p id="detailTherapistNote" class="hidden mt-1 text-xs text-gray-600 dark:text-gray-400"></p>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                    <li id="detailArrivalCodeWrap" class="hidden gap-3">
                                        <span aria-hidden="true"
                                            class="flex items-center justify-center w-6 h-6 text-xs font-semibold text-white rounded-full shrink-0 bg-[#7A6348] dark:bg-[#C4A97D] dark:text-gray-900">2</span>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-[#3C2F23] dark:text-gray-100">Then tell them your arrival code</p>
                                            {{-- Screen readers get the code once, digit by digit; the boxes are visual only. --}}
                                            <p id="detailArrivalCodeSr" class="sr-only"></p>
                                            <div id="detailArrivalCode" class="flex gap-2 mt-2.5" aria-hidden="true">
                                                <span data-code-digit class="flex items-center justify-center w-12 h-14 text-2xl font-semibold tabular-nums rounded-xl bg-white text-[#3C2F23] ring-1 ring-[#8B7355]/25 dark:bg-gray-800 dark:text-white dark:ring-white/15"></span>
                                                <span data-code-digit class="flex items-center justify-center w-12 h-14 text-2xl font-semibold tabular-nums rounded-xl bg-white text-[#3C2F23] ring-1 ring-[#8B7355]/25 dark:bg-gray-800 dark:text-white dark:ring-white/15"></span>
                                                <span data-code-digit class="flex items-center justify-center w-12 h-14 text-2xl font-semibold tabular-nums rounded-xl bg-white text-[#3C2F23] ring-1 ring-[#8B7355]/25 dark:bg-gray-800 dark:text-white dark:ring-white/15"></span>
                                                <span data-code-digit class="flex items-center justify-center w-12 h-14 text-2xl font-semibold tabular-nums rounded-xl bg-white text-[#3C2F23] ring-1 ring-[#8B7355]/25 dark:bg-gray-800 dark:text-white dark:ring-white/15"></span>
                                            </div>
                                            <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">Only you have this code.</p>
                                        </div>
                                    </li>
                                </ol>

                                <p id="detailArrivalDone"
                                    class="hidden items-center gap-2 mt-4 text-sm font-medium text-emerald-700 dark:text-emerald-300">
                                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i><span id="detailArrivalDoneText"></span>
                                </p>

                                <div id="detailArrivalSafety"
                                    class="flex flex-wrap items-center justify-between pt-3 mt-4 border-t gap-x-3 gap-y-1 border-[#8B7355]/15 dark:border-white/10">
                                    <p class="text-xs text-amber-800 dark:text-amber-300">
                                        <i class="mr-1 fa-solid fa-triangle-exclamation" aria-hidden="true"></i>Not the right person? Don't let them in.
                                    </p>
                                    <a id="detailArrivalCall" href="#"
                                        class="hidden items-center gap-1.5 min-h-[44px] -my-2 px-2 -mr-2 text-sm font-semibold rounded-xl text-[#6F5430] hover:bg-[#8B7355]/10 dark:text-[#C4A97D] dark:hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] dark:focus-visible:ring-[#C4A97D]">
                                        <i class="fa-solid fa-phone" aria-hidden="true"></i> Call the branch
                                    </a>
                                </div>
                            </section>

                            <!-- Therapist row for everything except an active home visit -->
                            <div id="detailTherapistRow" class="hidden pt-4 border-t border-black/5 dark:border-white/10">
                                <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Therapist</p>
                                <p id="detailTherapistPlain" class="mt-0.5 text-sm font-semibold text-[#3C2F23] dark:text-white"></p>
                            </div>

                            <!-- Started or ended: say how to change it instead of offering a button -->
                            <p id="detailRescheduleClosed" class="hidden text-sm text-gray-600 dark:text-gray-300">
                                Need to change this appointment? Please call the branch<span id="detailRescheduleClosedPhone"></span>.
                            </p>
                        </div>

                        <!-- ===== View 2: reschedule form ===== -->
                        <div id="bdRescheduleView" class="hidden px-4 py-5 space-y-5 sm:px-6">
                            <input type="hidden" id="rescheduleBookingId">

                            <div class="p-3 rounded-2xl bg-[#F6EFE6]/70 dark:bg-gray-900/40">
                                <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Current schedule</p>
                                <p id="rescheduleCurrentSchedule" class="mt-0.5 text-sm font-semibold text-[#3C2F23] dark:text-white"></p>
                            </div>

                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                Pick a new date and time. The branch will review your request and let you know.
                            </p>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="rescheduleDate" class="block text-sm font-medium text-[#3C2F23] dark:text-gray-100">New date</label>
                                    <input type="date" id="rescheduleDate" required
                                        class="w-full mt-1.5 min-h-[44px] rounded-xl border-black/15 text-[#3C2F23] dark:border-white/15 dark:bg-gray-700 dark:text-white focus:border-[#8B7355] focus:ring-2 focus:ring-[#8B7355]/30 dark:focus:border-[#C4A97D] dark:focus:ring-[#C4A97D]/30">
                                </div>
                                <div>
                                    <label for="rescheduleTime" class="block text-sm font-medium text-[#3C2F23] dark:text-gray-100">New time</label>
                                    <input type="time" id="rescheduleTime" required aria-describedby="rescheduleTimeError"
                                        class="w-full mt-1.5 min-h-[44px] rounded-xl border-black/15 text-[#3C2F23] dark:border-white/15 dark:bg-gray-700 dark:text-white focus:border-[#8B7355] focus:ring-2 focus:ring-[#8B7355]/30 dark:focus:border-[#C4A97D] dark:focus:ring-[#C4A97D]/30 disabled:opacity-60">
                                </div>
                            </div>
                            <p id="rescheduleTimeError" class="hidden -mt-3 text-xs text-red-700 dark:text-red-400" role="alert">
                                <i class="mr-1 fa-solid fa-circle-exclamation" aria-hidden="true"></i><span id="rescheduleTimeErrorText"></span>
                            </p>

                            <div>
                                <label for="rescheduleReason" class="block text-sm font-medium text-[#3C2F23] dark:text-gray-100">Reason</label>
                                <textarea id="rescheduleReason" rows="4" maxlength="1000" required aria-describedby="rescheduleReasonCount"
                                    placeholder="I have a work meeting that morning."
                                    class="w-full mt-1.5 text-sm resize-none rounded-xl border-black/15 text-[#3C2F23] placeholder:text-gray-400 dark:border-white/15 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-500 focus:border-[#8B7355] focus:ring-2 focus:ring-[#8B7355]/30 dark:focus:border-[#C4A97D] dark:focus:ring-[#C4A97D]/30"></textarea>
                                <p id="rescheduleReasonCount" class="mt-1 text-xs text-gray-600 dark:text-gray-400">At least 10 characters · 0 / 1000</p>
                            </div>

                            <div id="rescheduleError" role="alert"
                                class="hidden p-3 text-sm text-red-700 dark:text-red-300 rounded-2xl bg-red-50 dark:bg-red-900/20 ring-1 ring-red-200 dark:ring-red-800">
                                <i class="mr-1 fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                                <span id="rescheduleErrorText"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer: one action per view -->
                    <div id="bdFooter" class="bd-footer px-4 pt-3 border-t shrink-0 sm:px-6 border-black/5 dark:border-white/10">
                        <button type="button" id="openRescheduleBtn" onclick="openRescheduleModal()"
                            class="inline-flex items-center justify-center w-full gap-2 min-h-[48px] px-4 text-sm font-semibold text-white transition shadow-md rounded-xl booking-btn hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B7355] dark:focus-visible:ring-[#C4A97D] dark:focus-visible:ring-offset-gray-800">
                            <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                            Request reschedule
                        </button>
                        <button type="button" id="rescheduleSubmitBtn" onclick="submitRescheduleRequest()"
                            class="hidden items-center justify-center w-full gap-2 min-h-[48px] px-4 text-sm font-semibold text-white transition shadow-md rounded-xl booking-btn hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B7355] dark:focus-visible:ring-[#C4A97D] dark:focus-visible:ring-offset-gray-800">
                            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                            <span>Send request</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= SPAS NEAR YOU ================= -->
        @auth
            @role('customer')
                <section id="nearbySection" class="hidden py-5">
                    <div class="px-6 mx-auto mt-5 max-w-7xl">
                        <div class="text-center">
                            <div class="flex items-center justify-center gap-6">
                                <span
                                    class="h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                                <h2 class="text-4xl font-['Playfair_Display'] text-[#3C2F23] dark:text-white font-semibold">
                                    Spas Near You</h2>
                                <span
                                    class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                            </div>
                            <p id="nearbyLocationText" class="mt-3 text-sm text-gray-600 dark:text-gray-400">Finding spas near you...</p>
                        </div>
                        <div id="nearbyGrid" class="grid grid-cols-1 gap-6 mt-5 sm:grid-cols-2 lg:grid-cols-4">
                            {{-- filled by JS --}}
                        </div>
                    </div>
                </section>
            @endrole
        @endauth

        <!-- ================= FEATURED SPAS ================= -->
        <div id="browseSections" class="{{ $isSearching ? 'hidden' : '' }}">
            <section class="py-5">
                <div class="px-6 mx-auto mt-5 max-w-7xl">
                    <div class="text-center">
                        <div class="flex items-center justify-center gap-6">
                            <span
                                class="h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                            <h2
                                class="text-4xl font-['Playfair_Display'] text-[#3C2F23] dark:text-white font-semibold">
                                Featured Spas</h2>
                            <span
                                class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                        </div>
                        <p id="featuredSpasSubtitle" class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                            @if (!empty($search))
                                Showing results for "<span
                                    class="font-semibold text-[#8B7355] dark:text-[#C4A97D]">{{ $search }}</span>"
                            @else
                                Curated picks for a premium relaxation experience.
                            @endif
                        </p>
                    </div>

                    <div id="featuredSpasGrid" class="grid grid-cols-1 gap-6 mt-5 sm:grid-cols-2 lg:grid-cols-4">
                        @php $featuredCount = 0; @endphp
                        @forelse($spas as $spa)
                            @foreach ($spa->branches as $branch)
                                @if ($spa->verification_status === 'verified' && $branch->profile?->is_listed)
                                    @php
                                        $featuredCount++;
                                        $lowestPrice = \App\Models\Treatment::withoutGlobalScopes()
                                            ->where('spa_id', $spa->id)
                                            ->where('branch_id', $branch->id)
                                            ->min('price');

                                        $profile = $branch->profile;

                                        // Canonical photo payload — real uploads only,
                                        // never padded, each entry ['url', 'caption'].
                                        // See BranchProfile::photoPayload().
                                        $photos = \App\Models\BranchProfile::photoPayload($profile);
                                        $thumb = \App\Models\BranchProfile::thumbnailFor($profile);

                                        $activePromo = \App\Models\Promo::withoutGlobalScopes()
                                            ->where('spa_id', $spa->id)
                                            ->where('branch_id', $branch->id)
                                            ->activeToday()
                                            ->first();

                                        // Spa rating aggregate — avg of ratings.spa_rating for this
                                        // spa/branch, joined through bookings since ratings has no
                                        // spa_id/branch_id of its own.
                                        $ratingAgg = \App\Models\Rating::query()
                                            ->join('bookings', 'bookings.id', '=', 'ratings.booking_id')
                                            ->where('bookings.spa_id', $spa->id)
                                            ->where('bookings.branch_id', $branch->id)
                                            ->whereNotNull('ratings.spa_rating')
                                            ->selectRaw(
                                                'AVG(ratings.spa_rating) as avg_rating, COUNT(*) as rating_count',
                                            )
                                            ->first();

                                        $branchTreatments = \App\Models\Treatment::withoutGlobalScope('spa_branch')
                                            ->where('branch_id', $branch->id)
                                            ->where('spa_id', $spa->id)
                                            ->with([
                                                'promos' => fn($q) => $q
                                                    ->withoutGlobalScope('spa_branch')
                                                    ->activeToday(),
                                            ])
                                            ->get();

                                        $branchPackages = \App\Models\Package::withoutGlobalScope('spa_branch')
                                            ->where('branch_id', $branch->id)
                                            ->where('spa_id', $spa->id)
                                            ->with([
                                                'promos' => fn($q) => $q
                                                    ->withoutGlobalScope('spa_branch')
                                                    ->activeToday(),
                                            ])
                                            ->get();

                                        $spaPayload = [
                                            'id' => $spa->id,
                                            'name' => $spa->name,
                                            'owner_name' => $spa->ownerDisplayName(),
                                            'business_verified' => $spa->isBusinessVerified(),
                                            'tag' => 'Featured Spa',
                                            'branch_id' => $branch->id,
                                            'branch_name' => $branch->name,
                                            'branch_location' => $branch->location ?? '',
                                            'desc' => $profile->description ?? '',
                                            'price_note' => $lowestPrice ? number_format($lowestPrice, 2) : null,
                                            'photos' => $photos,
                                            'address' =>
                                                $profile->address ?? ($branch->location ?? 'Location unavailable'),
                                            'phone' => $profile->phone ?? '',
                                            'lat' => $profile->latitude,
                                            'lng' => $profile->longitude,
                                            'treatments' => $branchTreatments,
                                            'packages' => $branchPackages,
                                            'has_promo' => (bool) $activePromo,
                                            'promo_label' => $activePromo?->name,
                                            'amenities' => $profile->amenities ?? [],
                                            'is_hiring' => $profile->is_hiring ?? false,
                                            'hiring_note' => $profile->hiring_note ?? null,
                                            'rating_avg' => $ratingAgg->avg_rating
                                                ? round($ratingAgg->avg_rating, 1)
                                                : null,
                                            'rating_count' => (int) ($ratingAgg->rating_count ?? 0),
                                        ];
                                    @endphp

                                    <button type="button"
                                        class="w-full overflow-hidden text-left transition bg-white shadow-sm dark:bg-gray-800 group rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-2xl"
                                        data-open-spa-modal data-spa='@json($spaPayload)'>
                                        <div class="relative overflow-hidden">
                                            <img src="{{ $thumb }}"
                                                class="h-56 w-full object-cover transition duration-500 group-hover:scale-[1.04]"
                                                alt="{{ $spa->name }}">
                                            <div
                                                class="absolute inset-0 bg-gradient-to-t from-black/40 via-black/0 to-transparent">
                                            </div>
                                            <div
                                                class="absolute top-3 left-3 flex items-center gap-1 px-2.5 py-1 rounded-full bg-[#6F5430]/90 text-white text-[11px] font-semibold backdrop-blur-sm">
                                                <i class="fa-solid fa-star text-[#F5C842] text-[10px]"></i>
                                                Featured
                                            </div>
                                            @if ($profile->is_hiring ?? false)
                                                <span
                                                    onclick="event.stopPropagation(); openApplicationModal({{ $spa->id }}, {{ $branch->id }}, '{{ addslashes($spa->name) }}')"
                                                    class="absolute top-3 right-3 flex items-center gap-1 px-2.5 py-1 rounded-full bg-red-500 hover:bg-red-700 text-white text-[11px] font-semibold backdrop-blur-sm transition cursor-pointer">
                                                    <i class="fa-solid fa-briefcase text-[10px]"></i>
                                                    We're Hiring · <span class="underline underline-offset-2">Apply
                                                        Now</span>
                                                </span>
                                            @endif
                                            @if ($activePromo)
                                                <div
                                                    class="absolute {{ $profile->is_hiring ?? false ? 'top-12' : 'top-3' }} right-3 flex items-center gap-1 px-2.5 py-1 rounded-full bg-red-600 text-white text-[11px] font-semibold backdrop-blur-sm">
                                                    <i class="fa-solid fa-tag text-[10px]"></i>
                                                    {{ $activePromo->name }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="p-5">
                                            <h3
                                                class="text-[15px] font-semibold text-[#3C2F23] dark:text-white leading-tight">
                                                {{ $spa->name }}</h3>
                                            @if ($spaPayload['rating_avg'])
                                                <div class="flex items-center gap-1 mt-1">
                                                    <i class="fa-solid fa-star text-[#D2A85B] text-xs"></i>
                                                    <span
                                                        class="text-xs font-semibold text-[#3C2F23] dark:text-white">{{ $spaPayload['rating_avg'] }}</span>
                                                    <span
                                                        class="text-xs text-gray-400">({{ $spaPayload['rating_count'] }})</span>
                                                </div>
                                            @endif
                                            @php
                                                $addr = $spaPayload['address'] ?? '';
                                                $cleaned = preg_replace(
                                                    '/,?\s*(Philippines|Calabarzon|\d{4})\s*/i',
                                                    '',
                                                    $addr,
                                                );
                                                $parts = array_values(
                                                    array_filter(array_map('trim', explode(',', $cleaned))),
                                                );
                                                $addrSummary =
                                                    count($parts) >= 2
                                                        ? implode(', ', array_slice($parts, -2))
                                                        : (implode(', ', $parts) ?:
                                                        'Location unavailable');
                                            @endphp
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                {{ $addrSummary }}</p>
                                            @if ($lowestPrice)
                                                <p class="mt-2 text-xs font-medium text-[#8B7355] dark:text-[#C4A97D]">
                                                    Starts at ₱{{ number_format($lowestPrice, 2) }}
                                                </p>
                                            @endif
                                            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                                {{ $spaPayload['desc'] ?? 'No description yet.' }}</p>
                                        </div>
                                    </button>
                                @endif
                            @endforeach
                        @empty
                        @endforelse

                        @if ($featuredCount === 0)
                            <div class="py-16 text-center col-span-full">
                                <div
                                    class="flex items-center justify-center w-14 h-14 mx-auto mb-4 rounded-2xl bg-[#F6EFE6] dark:bg-gray-800 ring-1 ring-black/5 dark:ring-white/10">
                                    <i class="fa-solid fa-star text-xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                                </div>
                                <p class="font-semibold text-[#3C2F23] dark:text-white">No featured spas found</p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    @if (!empty($search))
                                        No featured spas match "{{ $search }}". Try a different name or location.
                                    @else
                                        No featured spas available yet.
                                    @endif
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- ================= LISTED SPAS ================= -->
                <section class="pb-10 mt-10 ">
                    <div class="px-6 mx-auto max-w-7xl">
                        <div class="text-center">
                            <div class="flex items-center justify-center gap-6">
                                <span
                                    class="h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                                <h2
                                    class="text-4xl font-['Playfair_Display'] text-[#3C2F23] dark:text-white font-semibold">
                                    Other Spas in Cavite</h2>
                                <span
                                    class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                            </div>
                            <p id="otherSpasSubtitle" class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                                @if (!empty($search))
                                    Showing results for "<span
                                        class="font-semibold text-[#8B7355] dark:text-[#C4A97D]">{{ $search }}</span>"
                                @else
                                    Explore more verified wellness destinations.
                                @endif
                            </p>
                        </div>

                        @php
                            $hasListedBasic = $basicSpas->flatMap->branches->contains(
                                fn($b) => $b->profile?->is_listed,
                            );
                        @endphp

                        <div id="otherSpasContainer">
                            @if ($hasListedBasic)
                                <div class="grid grid-cols-1 gap-5 mt-12 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                    @foreach ($basicSpas as $spa)
                                        @foreach ($spa->branches as $branch)
                                            @if ($branch->profile?->is_listed)
                                                @php
                                                    $lowestPrice = \App\Models\Treatment::withoutGlobalScopes()
                                                        ->where('spa_id', $spa->id)
                                                        ->where('branch_id', $branch->id)
                                                        ->min('price');

                                                    $profile = $branch->profile;

                                                    // Same canonical payload as the Featured
                                                    // Spas loop above — see BranchProfile.
                                                    $photos = \App\Models\BranchProfile::photoPayload($profile);
                                                    $coverPhoto = \App\Models\BranchProfile::thumbnailFor($profile);

                                                    $branchTreatments = \App\Models\Treatment::withoutGlobalScope(
                                                        'spa_branch',
                                                    )
                                                        ->where('branch_id', $branch->id)
                                                        ->where('spa_id', $spa->id)
                                                        ->with([
                                                            'promos' => fn($q) => $q
                                                                ->withoutGlobalScope('spa_branch')
                                                                ->activeToday(),
                                                        ])
                                                        ->get();

                                                    $branchPackages = \App\Models\Package::withoutGlobalScope(
                                                        'spa_branch',
                                                    )
                                                        ->where('branch_id', $branch->id)
                                                        ->where('spa_id', $spa->id)
                                                        ->with([
                                                            'promos' => fn($q) => $q
                                                                ->withoutGlobalScope('spa_branch')
                                                                ->activeToday(),
                                                        ])
                                                        ->get();

                                                    $activePromo = \App\Models\Promo::withoutGlobalScopes()
                                                        ->where('spa_id', $spa->id)
                                                        ->where('branch_id', $branch->id)
                                                        ->activeToday()
                                                        ->first();

                                                    // Spa rating aggregate — same pattern as Featured Spas above.
                                                    $ratingAgg = \App\Models\Rating::query()
                                                        ->join('bookings', 'bookings.id', '=', 'ratings.booking_id')
                                                        ->where('bookings.spa_id', $spa->id)
                                                        ->where('bookings.branch_id', $branch->id)
                                                        ->whereNotNull('ratings.spa_rating')
                                                        ->selectRaw(
                                                            'AVG(ratings.spa_rating) as avg_rating, COUNT(*) as rating_count',
                                                        )
                                                        ->first();

                                                    $spaPayload = [
                                                        'id' => $spa->id,
                                                        'name' => $spa->name,
                                                        'owner_name' => $spa->ownerDisplayName(),
                                                        'business_verified' => $spa->isBusinessVerified(),
                                                        'tag' => 'Listed Spa',
                                                        'branch_id' => $branch->id,
                                                        'branch_name' => $branch->name,
                                                        'branch_location' => $branch->location ?? '',
                                                        'desc' => $profile->description ?? '',
                                                        'price_note' => $lowestPrice
                                                            ? number_format($lowestPrice, 2)
                                                            : null,
                                                        'photos' => $photos,
                                                        'address' =>
                                                            $profile->address ??
                                                            ($branch->location ?? 'Location unavailable'),
                                                        'phone' => $profile->phone ?? '',
                                                        'lat' => $profile->latitude,
                                                        'lng' => $profile->longitude,
                                                        'treatments' => $branchTreatments,
                                                        'packages' => $branchPackages,
                                                        'has_promo' => (bool) $activePromo,
                                                        'promo_label' => $activePromo?->name,
                                                        'amenities' => $profile->amenities ?? [],
                                                        'is_hiring' => $profile->is_hiring ?? false,
                                                        'hiring_note' => $profile->hiring_note ?? null,
                                                        'rating_avg' => $ratingAgg->avg_rating
                                                            ? round($ratingAgg->avg_rating, 1)
                                                            : null,
                                                        'rating_count' => (int) ($ratingAgg->rating_count ?? 0),
                                                    ];
                                                @endphp

                                                <button type="button"
                                                    class="w-full overflow-hidden text-left transition bg-white shadow-sm dark:bg-gray-800 group rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl"
                                                    data-open-spa-modal data-spa='@json($spaPayload)'>
                                                    <div class="relative overflow-hidden">
                                                        <img src="{{ $coverPhoto }}"
                                                            class="h-48 w-full object-cover transition duration-500 group-hover:scale-[1.04]"
                                                            alt="{{ $spa->name }}">
                                                        <div
                                                            class="absolute inset-0 bg-gradient-to-t from-black/35 via-black/0 to-transparent">
                                                        </div>
                                                        <div
                                                            class="absolute top-3 left-3 flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/80 dark:bg-gray-900/70 text-[#6F5430] dark:text-[#C4A97D] text-[11px] font-semibold backdrop-blur-sm ring-1 ring-black/5 dark:ring-white/10">
                                                            <i
                                                                class="fa-solid fa-spa text-[#8B7355] dark:text-[#C4A97D] text-[10px]"></i>
                                                            Verified
                                                        </div>
                                                        @if ($profile->is_hiring ?? false)
                                                            <span
                                                                onclick="event.stopPropagation(); openApplicationModal({{ $spa->id }}, {{ $branch->id }}, '{{ addslashes($spa->name) }}')"
                                                                class="absolute top-3 right-3 flex items-center gap-1 px-2.5 py-1 rounded-full bg-red-500 hover:bg-red-700 text-white text-[11px] font-semibold backdrop-blur-sm transition cursor-pointer">
                                                                <i class="fa-solid fa-briefcase text-[10px]"></i>
                                                                We're Hiring · <span
                                                                    class="underline underline-offset-2">Apply
                                                                    Now</span>
                                                            </span>
                                                        @endif
                                                        @if ($activePromo)
                                                            <div
                                                                class="absolute {{ $profile->is_hiring ?? false ? 'top-12' : 'top-3' }} right-3 flex items-center gap-1 px-2.5 py-1 rounded-full bg-red-600 text-white text-[11px] font-semibold backdrop-blur-sm">
                                                                <i class="fa-solid fa-tag text-[10px]"></i>
                                                                {{ $activePromo->name }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <div class="p-4">
                                                        <h3
                                                            class="text-[15px] font-semibold text-[#3C2F23] dark:text-white leading-tight">
                                                            {{ $spa->name }}</h3>
                                                        @if ($spaPayload['rating_avg'])
                                                            <div class="flex items-center gap-1 mt-1">
                                                                <i class="fa-solid fa-star text-[#D2A85B] text-xs"></i>
                                                                <span
                                                                    class="text-xs font-semibold text-[#3C2F23] dark:text-white">{{ $spaPayload['rating_avg'] }}</span>
                                                                <span
                                                                    class="text-xs text-gray-400">({{ $spaPayload['rating_count'] }})</span>
                                                            </div>
                                                        @endif
                                                        @php
                                                            $addr = $spaPayload['address'] ?? '';
                                                            $cleaned = preg_replace(
                                                                '/,?\s*(Philippines|Calabarzon|\d{4})\s*/i',
                                                                '',
                                                                $addr,
                                                            );
                                                            $parts = array_values(
                                                                array_filter(array_map('trim', explode(',', $cleaned))),
                                                            );
                                                            $addrSummary =
                                                                count($parts) >= 2
                                                                    ? implode(', ', array_slice($parts, -2))
                                                                    : (implode(', ', $parts) ?:
                                                                    'Location unavailable');
                                                        @endphp
                                                        <p class="mt-1 text-xs text-gray-900 dark:text-gray-300">
                                                            {{ $addrSummary }}</p>
                                                        @if ($lowestPrice)
                                                            <p
                                                                class="mt-2 text-xs font-medium text-[#8B7355] dark:text-[#C4A97D]">
                                                                Starts at ₱{{ number_format($lowestPrice, 2) }}
                                                            </p>
                                                        @endif
                                                        <p
                                                            class="mt-2 text-sm text-gray-500 dark:text-gray-400 line-clamp-2">
                                                            {{ $spaPayload['desc'] ?: 'No description yet.' }}
                                                        </p>
                                                    </div>
                                                </button>
                                            @endif
                                        @endforeach
                                    @endforeach
                                </div>
                            @else
                                <div
                                    class="flex flex-col items-center justify-center py-16 mt-12 border border-dashed border-[#C4A97D]/40 dark:border-[#C4A97D]/25 rounded-3xl bg-white/50 dark:bg-gray-800/40">
                                    <div
                                        class="flex items-center justify-center w-16 h-16 mb-5 rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                                        <i class="fa-solid fa-spa text-2xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                                    </div>
                                    @if (!empty($search))
                                        <h3
                                            class="text-lg font-semibold font-['Playfair_Display'] text-[#3C2F23] dark:text-white">
                                            No spas found for "{{ $search }}"</h3>
                                        <p class="max-w-xs mt-2 text-sm text-center text-gray-500 dark:text-gray-400">
                                            Try a different name or location, or browse all available spas.
                                        </p>
                                        <a href="{{ url('/') }}"
                                            class="inline-flex items-center gap-2 mt-6 px-6 py-2.5 text-sm font-semibold text-white rounded-xl booking-btn shadow-md hover:shadow-lg transition active:translate-y-0.5">
                                            <i class="text-xs fa-solid fa-arrow-left"></i>
                                            Browse All Spas
                                        </a>
                                    @else
                                        <h3
                                            class="text-lg font-semibold font-['Playfair_Display'] text-[#3C2F23] dark:text-white">
                                            No spas listed yet</h3>
                                        {{-- Listing a spa needs a business account, so customers get no
                                             sign-up prompt here (landing footer Phase 1, D7 / Q4-A). --}}
                                        @role('customer')
                                            <p class="max-w-xs mt-2 text-sm text-center text-gray-500 dark:text-gray-400">
                                                New spas are joining Levictas. Check back soon.
                                            </p>
                                        @else
                                            <p class="max-w-xs mt-2 text-sm text-center text-gray-500 dark:text-gray-400">
                                                Be the first to list your spa and reach customers looking for wellness
                                                experiences.
                                            </p>
                                            <a href="{{ route('register.business') }}"
                                                class="inline-flex items-center gap-2 mt-6 px-6 py-2.5 text-sm font-semibold text-white rounded-xl booking-btn shadow-md hover:shadow-lg transition active:translate-y-0.5">
                                                <i class="text-xs fa-solid fa-plus"></i>
                                                List Your Spa
                                            </a>
                                        @endrole
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </section>
            </section>
        </div>

        <!-- ================= SEARCH RESULTS (shown instead of the two sections above while searching) ================= -->
        <section id="unifiedResultsSection" class="py-5 {{ $isSearching ? '' : 'hidden' }}">
            <div class="px-6 mx-auto mt-5 max-w-7xl">
                <div class="text-center">
                    <div class="flex items-center justify-center gap-6">
                        <span
                            class="h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                        <h2 class="text-4xl font-['Playfair_Display'] text-[#3C2F23] dark:text-white font-semibold">
                            Search Results</h2>
                        <span
                            class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                    </div>
                    <p id="unifiedResultsSubtitle" class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                        @if ($place || $treatment)
                            Showing results
                            @if ($place)
                                in "<span
                                    class="font-semibold text-[#8B7355] dark:text-[#C4A97D]">{{ $place }}</span>"
                            @endif
                            @if ($treatment)
                                for "<span
                                    class="font-semibold text-[#8B7355] dark:text-[#C4A97D]">{{ $treatment }}</span>"
                            @endif
                        @else
                            Showing all spas
                        @endif
                        <button type="button" onclick="clearSpaSearch()"
                            class="ml-2 text-[#8B7355] dark:text-[#C4A97D] underline underline-offset-2">Clear
                            search</button>
                    </p>
                </div>

                <div id="unifiedResultsGrid" class="grid grid-cols-1 gap-6 mt-8 sm:grid-cols-2 lg:grid-cols-4">
                    @forelse($results ?? [] as $item)
                        @php
                            $addr = $item['address'] ?? '';
                            $cleaned = preg_replace('/,?\s*(Philippines|Calabarzon|\d{4})\s*/i', '', $addr);
                            $parts = array_values(array_filter(array_map('trim', explode(',', $cleaned))));
                            $addrSummary =
                                count($parts) >= 2
                                    ? implode(', ', array_slice($parts, -2))
                                    : (implode(', ', $parts) ?:
                                    'Location unavailable');
                        @endphp
                        <button type="button"
                            class="w-full overflow-hidden text-left transition bg-white shadow-sm dark:bg-gray-800 group rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-2xl"
                            data-open-spa-modal data-spa='@json($item)'>
                            <div class="relative overflow-hidden">
                                <img src="{{ $item['photos'][0]['url'] ?? \App\Models\BranchProfile::fallbackPhotoUrl() }}"
                                    class="h-56 w-full object-cover transition duration-500 group-hover:scale-[1.04]"
                                    alt="{{ $item['name'] }}">
                                <div
                                    class="absolute inset-0 bg-gradient-to-t from-black/40 via-black/0 to-transparent">
                                </div>
                                <div
                                    class="absolute top-3 left-3 flex items-center gap-1 px-2.5 py-1 rounded-full {{ $item['is_featured'] ? 'bg-[#6F5430]/90 text-white' : 'bg-white/80 dark:bg-gray-900/70 text-[#6F5430] dark:text-[#C4A97D] ring-1 ring-black/5 dark:ring-white/10' }} text-[11px] font-semibold backdrop-blur-sm">
                                    <i
                                        class="fa-solid {{ $item['is_featured'] ? 'fa-star text-[#F5C842]' : 'fa-spa text-[#8B7355] dark:text-[#C4A97D]' }} text-[10px]"></i>
                                    {{ $item['is_featured'] ? 'Featured' : 'Verified' }}
                                </div>
                                @if ($item['is_hiring'] ?? false)
                                    <span
                                        onclick="event.stopPropagation(); openApplicationModal({{ $item['id'] }}, {{ $item['branch_id'] }}, '{{ addslashes($item['name']) }}')"
                                        class="absolute top-3 right-3 flex items-center gap-1 px-2.5 py-1 rounded-full bg-red-500 hover:bg-red-700 text-white text-[11px] font-semibold backdrop-blur-sm transition cursor-pointer">
                                        <i class="fa-solid fa-briefcase text-[10px]"></i>
                                        We're Hiring · <span class="underline underline-offset-2">Apply Now</span>
                                    </span>
                                @endif
                            </div>
                            <div class="p-5">
                                <h3 class="text-[15px] font-semibold text-[#3C2F23] dark:text-white leading-tight">
                                    {{ $item['name'] }}</h3>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $addrSummary }}</p>
                                @if ($item['price_note'] ?? null)
                                    <p class="mt-2 text-xs font-medium text-[#8B7355] dark:text-[#C4A97D]">Starts at
                                        ₱{{ $item['price_note'] }}</p>
                                @endif
                                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                    {{ $item['desc'] ?: 'No description yet.' }}</p>
                            </div>
                        </button>
                    @empty
                        <div class="py-16 text-center col-span-full">
                            <div
                                class="flex items-center justify-center w-14 h-14 mx-auto mb-4 rounded-2xl bg-[#F6EFE6] dark:bg-gray-800 ring-1 ring-black/5 dark:ring-white/10">
                                <i class="fa-solid fa-magnifying-glass text-xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                            </div>
                            <p class="font-semibold text-[#3C2F23] dark:text-white">No spas found</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Try a different place or treatment, or
                                <button type="button" onclick="clearSpaSearch()"
                                    class="text-[#8B7355] dark:text-[#C4A97D] underline underline-offset-2">browse all
                                    spas</button>.
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <!-- ================= REVIEWS MODAL ================= -->
        <div id="reviewsModal" class="fixed inset-0 z-[145] hidden">
            <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeReviewsModal()"></div>
            <div class="relative mx-auto w-[92%] max-w-lg mt-10 sm:mt-16">
                <div
                    class="overflow-hidden bg-white shadow-2xl dark:bg-gray-800 rounded-3xl ring-1 ring-black/10 dark:ring-white/10 flex flex-col max-h-[80vh]">
                    <div
                        class="flex items-center justify-between px-6 py-4 border-b border-black/5 dark:border-white/10">
                        <div>
                            <h3 class="text-lg font-semibold text-[#3C2F23] dark:text-white">Ratings & Reviews</h3>
                            <p id="reviewsModalSpaName" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"></p>
                        </div>
                        <button type="button" onclick="closeReviewsModal()"
                            class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5 dark:hover:bg-white/10">
                            <i class="text-lg text-gray-700 dark:text-gray-300 fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <!-- Filter tabs -->
                    <div id="reviewFilterTabs"
                        class="flex items-center gap-2 px-6 py-3 overflow-x-auto border-b border-black/5 dark:border-white/10">
                        <p class="text-sm text-gray-400">Loading...</p>
                    </div>

                    <!-- List -->
                    <div id="reviewsModalList" class="flex-1 p-6 space-y-3 overflow-y-auto">
                        <p class="text-sm italic text-gray-400 dark:text-gray-500">Loading reviews...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= SPA MODAL ================= -->
        <div id="spaModal" class="fixed inset-0 z-[100] hidden">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" data-close-spa-modal></div>
            <div class="relative mx-auto w-[92%] max-w-5xl mt-8 mb-8">
                <div
                    class="overflow-hidden bg-white dark:bg-gray-800 shadow-2xl rounded-3xl flex flex-col max-h-[90vh]">
                    <div
                        class="sticky top-0 z-10 flex items-center justify-between px-6 py-4 border-b bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm border-black/5 dark:border-white/10">
                        <div>
                            <h3 id="spaModalName"
                                class="text-2xl font-['Playfair_Display'] font-bold tracking-tight text-[#3C2F23] dark:text-white">
                                Spa Name</h3>
                            <div class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 mt-1">
                                <span
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#F6EFE6] dark:bg-gray-700 text-[#6F5430] dark:text-[#C4A97D] text-xs font-semibold ring-1 ring-[#8B7355]/20 dark:ring-[#C4A97D]/20">
                                    <i class="fa-solid fa-star text-[#D2A85B] text-[10px]"></i>
                                    <span id="spaModalTag">Featured Spa</span>
                                </span>
                                <span class="text-gray-300 dark:text-gray-600">·</span>
                                <i class="fa-solid fa-location-dot text-[#8B7355] dark:text-[#C4A97D] text-xs"></i>
                                <span id="spaModalAddressSummary"
                                    class="font-medium text-[#6F5430] dark:text-[#C4A97D] underline underline-offset-2 decoration-dotted">Location</span>
                                <button type="button" id="spaModalRating" onclick="openReviewsModalFromSpa()"
                                    class="items-center hidden gap-1 hover:underline underline-offset-2">
                                    <span class="text-gray-300 dark:text-gray-600">·</span>
                                    <i class="fa-solid fa-star text-[#D2A85B] text-xs"></i>
                                    <span id="spaModalRatingValue"
                                        class="font-medium text-[#3C2F23] dark:text-white"></span>
                                    <span id="spaModalRatingCount" class="text-gray-400"></span>
                                </button>
                            </div>
                            {{-- D11: business-verified badge + owner's name (filled by openSpaModal). --}}
                            <div id="spaModalOwnerRow" class="flex-wrap items-center hidden gap-2 mt-2 text-xs text-gray-500 dark:text-gray-400">
                                <span id="spaModalBusinessVerified"
                                    title="Levictas checked this spa's business documents."
                                    class="hidden items-center gap-1 px-2 py-0.5 text-[11px] font-semibold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i> Business verified
                                </span>
                                <span id="spaModalOwner" class="hidden">
                                    Owned by <span id="spaModalOwnerName" class="font-medium text-[#3C2F23] dark:text-gray-200"></span>
                                </span>
                            </div>
                        </div>
                        <button data-close-spa-modal
                            class="flex items-center justify-center w-9 h-9 text-gray-500 dark:text-gray-400 transition rounded-xl hover:bg-[#F6EFE6] dark:hover:bg-gray-700 hover:text-[#3C2F23] dark:hover:text-white ring-1 ring-black/5 dark:ring-white/10">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div class="overflow-y-auto">
                        <div class="p-6">
                            {{-- Photo grid is built entirely by welcome.js's renderSpaPhotoGrid(),
                             because its shape depends on how many photos the branch actually
                             uploaded (1-5). Styling lives in the .spa-photo-* plain-CSS rules
                             in the <style> block below rather than Tailwind utilities, since
                             Tailwind's content scan does not reach welcome.js. --}}
                            <div id="spaModalPhotoGrid" class="spa-photo-grid" data-count="0"></div>
                        </div>
                        <div class="grid gap-8 px-6 pb-8 md:grid-cols-3">
                            <div class="space-y-7 md:col-span-2">
                                <div>
                                    <h4
                                        class="mb-2 text-xl font-['Playfair_Display'] font-semibold text-[#3C2F23] dark:text-white">
                                        About this spa</h4>
                                    <p id="spaModalDesc"
                                        class="text-sm leading-relaxed text-gray-600 dark:text-gray-400"></p>
                                </div>
                                <div id="spaModalHiring"
                                    class="hidden p-4 rounded-2xl bg-green-50 dark:bg-green-900/20 ring-1 ring-green-200 dark:ring-green-800">
                                    <div class="flex items-center gap-2">
                                        <i
                                            class="text-sm text-green-600 dark:text-green-400 fa-solid fa-briefcase"></i>
                                        <p class="text-sm font-semibold text-green-700 dark:text-green-300">We're
                                            Hiring</p>
                                    </div>
                                    <p id="spaModalHiringNote"
                                        class="mt-1 text-sm text-green-700/90 dark:text-green-300/80"></p>
                                </div>
                                <hr class="border-[#E8DDD0] dark:border-gray-700">
                                <div>
                                    <h4
                                        class="mb-4 text-xl font-['Playfair_Display'] font-semibold text-[#3C2F23] dark:text-white">
                                        What this place offers</h4>
                                    <div id="spaModalAmenities">
                                        <p class="text-sm italic text-gray-400 dark:text-gray-500">No amenities listed
                                            yet.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="md:col-span-1">
                                <div
                                    class="sticky top-4 p-5 space-y-3 border border-[#E8DDD0] dark:border-gray-700 shadow-sm rounded-2xl bg-[#FDFAF6] dark:bg-gray-900/40">
                                    <div
                                        class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/60 dark:bg-gray-700/50">
                                        <div
                                            class="flex items-center justify-center w-8 h-8 rounded-lg bg-white dark:bg-gray-800 ring-1 ring-black/5 dark:ring-white/10 flex-shrink-0 mt-0.5">
                                            <i
                                                class="fa-solid fa-location-dot text-[#8B7355] dark:text-[#C4A97D] text-sm"></i>
                                        </div>
                                        <div>
                                            <p
                                                class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">
                                                Address</p>
                                            <p id="spaModalAddress"
                                                class="mt-0.5 text-sm text-[#3C2F23] dark:text-gray-200 leading-snug">
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/60 dark:bg-gray-700/50">
                                        <div
                                            class="flex items-center justify-center w-8 h-8 rounded-lg bg-white dark:bg-gray-800 ring-1 ring-black/5 dark:ring-white/10 flex-shrink-0 mt-0.5">
                                            <i
                                                class="fa-solid fa-phone text-[#8B7355] dark:text-[#C4A97D] text-sm"></i>
                                        </div>
                                        <div>
                                            <p
                                                class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">
                                                Contact</p>
                                            <p id="spaModalPhone"
                                                class="mt-0.5 text-sm text-[#3C2F23] dark:text-gray-200"></p>
                                        </div>
                                    </div>
                                    <div
                                        class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/60 dark:bg-gray-700/50">
                                        <div
                                            class="flex items-center justify-center w-8 h-8 rounded-lg bg-white dark:bg-gray-800 ring-1 ring-black/5 dark:ring-white/10 flex-shrink-0 mt-0.5">
                                            <i class="fa-solid fa-tag text-[#8B7355] dark:text-[#C4A97D] text-sm"></i>
                                        </div>
                                        <div>
                                            <p
                                                class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">
                                                Price</p>
                                            <p id="spaModalPrice"
                                                class="mt-0.5 text-sm font-semibold text-[#6F5430] dark:text-[#C4A97D]">
                                            </p>
                                        </div>
                                    </div>
                                    {{-- Leaflet/OSM tiles can't be themed via CSS; dimming overlay
                                     keeps the map readable against the dark sidebar without
                                     blocking interaction (pointer-events-none). --}}
                                    <div class="relative">
                                        <div id="spaModalMap"
                                            class="w-full h-[170px] rounded-xl border border-[#E8DDD0] dark:border-gray-700 bg-[#F6EFE6] dark:bg-gray-700 overflow-hidden shadow-inner">
                                        </div>
                                        <div
                                            class="absolute inset-0 hidden pointer-events-none dark:block rounded-xl bg-gray-900/30 mix-blend-multiply">
                                        </div>
                                    </div>
                                    <button type="button" id="openBookingModalBtn"
                                        class="flex items-center justify-center w-full gap-2 py-3 mt-1 text-sm font-semibold text-white transition rounded-xl booking-btn shadow-md hover:shadow-lg active:translate-y-0.5">
                                        <i class="fa-solid fa-calendar-check"></i>
                                        Reserve An Appointment
                                    </button>
                                    <p id="spaModalNoBookingNote"
                                        class="hidden px-3 py-2 text-xs text-center text-gray-500 dark:text-gray-400 rounded-xl bg-[#F6EFE6]/60 dark:bg-gray-700/50">
                                        Online reservation isn't available for this spa right now. Please contact them directly to book.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= PHOTO LIGHTBOX ================= -->
        {{-- Opens on top of #spaModal (z-[100]). z-[155] puts it above every other
         overlay on this page, since it is only ever reachable from a modal.
         Chrome is deliberately dark in both colour schemes, so the internals
         need no prefers-color-scheme branch. --}}
        <div id="photoLightbox" class="fixed inset-0 z-[155] hidden" role="dialog" aria-modal="true"
            aria-labelledby="lightboxSrLabel">

            <div class="absolute inset-0 bg-black/90"></div>

            <h2 id="lightboxSrLabel" class="sr-only">Spa photo viewer</h2>

            <div class="lightbox-shell">
                <button type="button" id="lightboxClose" class="lightbox-btn lightbox-btn-close"
                    aria-label="Close photo viewer">
                    <i class="fa-solid fa-xmark"></i>
                </button>

                <button type="button" id="lightboxPrev" class="lightbox-btn lightbox-btn-prev"
                    aria-label="Previous photo">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>

                <div class="lightbox-stage" id="lightboxStage">
                    <img id="lightboxImage" class="lightbox-image" alt="">
                    <div class="lightbox-footer" id="lightboxFooter">
                        <p class="lightbox-caption" id="lightboxCaption"></p>
                        <p class="lightbox-counter" id="lightboxCounter" aria-live="polite"></p>
                    </div>
                </div>

                <button type="button" id="lightboxNext" class="lightbox-btn lightbox-btn-next"
                    aria-label="Next photo">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        </div>

        <!-- ================= BOOKING MODAL ================= -->
        <style>
            /*
          These rules back elements that are built entirely in JavaScript
          (service cards, time slots, filter tabs). Tailwind's build only
          generates CSS for class names it can find as literal text in
          scanned files — a class that only ever exists inside a JS string
          has nothing to generate from, so it silently does nothing. Plain
          CSS here has no such dependency.
        */
            .svc-card {
                display: flex;
                flex-direction: row;
                align-items: flex-start;
                gap: 12px;
                padding: 12px 16px;
                cursor: pointer;
                border-left: 4px solid transparent;
                transition: background-color .15s ease, border-color .15s ease;
            }

            .svc-card.hidden {
                display: none !important;
            }

            .svc-card-thumb {
                flex-shrink: 0;
                width: 56px;
                height: 56px;
                border-radius: 10px;
                overflow: hidden;
                background: #F6EFE6;
                border: 1px solid rgba(0, 0, 0, 0.05);
            }

            .svc-card-thumb img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }

            .svc-card-content {
                flex: 1;
                min-width: 0;
                display: flex;
                flex-direction: column;
                gap: 4px;
            }

            .svc-card:hover {
                background-color: rgba(246, 239, 230, 0.5);
            }

            .svc-card.is-selected {
                border-left-color: #8B7355;
                background-color: rgba(246, 239, 230, 0.7);
            }

            .svc-card:focus-within {
                outline: 2px solid #8B7355;
                outline-offset: -2px;
            }

            .svc-card-top {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 8px;
            }

            .svc-card-name {
                font-size: 13px;
                font-weight: 500;
                color: #3C2F23;
            }

            .svc-card-badge {
                margin-left: 6px;
                font-size: 10px;
                font-weight: 600;
                color: #6F5430;
                background: #F6EFE6;
                border: 1px solid rgba(0, 0, 0, 0.05);
                border-radius: 999px;
                padding: 1px 8px;
            }

            .svc-card-price {
                font-size: 13px;
                font-weight: 600;
                color: #6F5430;
                flex-shrink: 0;
            }

            .svc-card-desc {
                font-size: 12px;
                color: #6b7280;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .svc-card-meta {
                font-size: 11px;
                color: #8B7355;
            }

            .svc-section-header {
                padding: 10px 16px 4px;
                font-size: 10px;
                font-weight: 600;
                letter-spacing: .05em;
                text-transform: uppercase;
                color: #9ca3af;
                background: #fafafa;
            }

            /* Filter Tabs */
            .svc-filter-tab {
                padding: 6px 12px;
                font-size: 12px;
                font-weight: 500;
                border-radius: 8px;
                color: #6b7280;
                background: transparent;
                transition: background-color .15s ease, color .15s ease;
            }

            .svc-filter-tab.is-active {
                background: #8B7355;
                color: #fff;
            }

            /* Slot Button */
            .slot-btn {
                padding: 8px 4px;
                font-size: 12px;
                font-weight: 500;
                border-radius: 10px;
                border: 1px solid rgba(0, 0, 0, 0.1);
                color: #3C2F23;
                background: #fff;
                transition: border-color .15s ease, background-color .15s ease, color .15s ease;
            }

            .slot-btn:hover:not(:disabled) {
                border-color: #8B7355;
                background-color: rgba(246, 239, 230, 0.5);
            }

            .slot-btn.is-selected {
                border-color: #8B7355;
                background-color: #8B7355;
                color: #fff;
            }

            .slot-btn:disabled {
                cursor: not-allowed;
                background-color: #f9fafb;
                color: #d1d5db;
                border-color: #f3f4f6;
            }

            .slot-btn.is-past-closing:disabled {
                border-style: dashed;
                border-color: #d1d5db;
            }

            /* Segmented Search Pill */
            .search-segment {
                border-radius: 999px;
                transition: box-shadow .15s ease;
            }

            .search-segment.active {
                box-shadow: 0 1px 8px rgba(0, 0, 0, 0.12);
            }

            .search-dropdown {
                position: fixed;
                z-index: 200;
                display: none;
                width: 320px;
                max-width: calc(100vw - 32px);
                padding: 14px;
                overflow-y: auto;
                background: #fff;
                border-radius: 18px;
                box-shadow: 0 16px 40px rgba(0, 0, 0, 0.18), 0 0 0 1px rgba(0, 0, 0, 0.06);
                max-height: 320px;
            }

            .search-dropdown.open {
                display: block;
            }

            .search-dropdown-label {
                margin-bottom: 8px;
                font-size: 10px;
                font-weight: 700;
                letter-spacing: .05em;
                text-transform: uppercase;
                color: #9a8f80;
            }

            .search-chip {
                padding: 6px 14px;
                border-radius: 999px;
                background: #F6EFE6;
                font-size: 13px;
                cursor: pointer;
                border: 1px solid rgba(0, 0, 0, 0.05);
                color: #3C2F23;
            }

            .search-chip:hover {
                background: #efe0cd;
            }

            .search-suggestion-row {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 7px 10px;
                border-radius: 10px;
                cursor: pointer;
                font-size: 13.5px;
                color: #3C2F23;
            }

            .search-suggestion-row:hover {
                background: #F6EFE6;
            }

            .search-empty-note {
                padding: 8px 4px;
                font-size: 12px;
                color: #9a8f80;
            }

            /* Amenity chips (built in welcome.js's openSpaModal) - same rationale
           as svc-card/slot-btn above: JS template strings aren't scanned by
           Tailwind, so arbitrary-value dark: classes there would be silently
           dropped. Plain CSS class instead. */
            .amenity-chip {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 10px 12px;
                border-radius: 12px;
                background: rgba(246, 239, 230, 0.7);
                border: 1px solid rgba(139, 115, 85, 0.10);
            }

            .amenity-chip-icon {
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                width: 28px;
                height: 28px;
                border-radius: 8px;
                background: #fff;
                border: 1px solid rgba(0, 0, 0, 0.05);
                color: #8B7355;
                font-size: 11px;
            }

            .amenity-chip-label {
                font-size: 12px;
                font-weight: 500;
                color: #3C2F23;
            }

            /* Booking modal step indicator - welcome.js's showBookingStep()
           overwrites circle/label/bar className wholesale on every step
           change, so dark: utility classes here would be wiped on the very
           first click. Plain CSS with state classes survives that. */
            .step-circle {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 2rem;
                height: 2rem;
                font-size: 0.75rem;
                font-weight: 600;
                border-radius: 9999px;
                transition: background-color .15s ease, color .15s ease;
            }

            .step-circle.is-pending {
                color: #9ca3af;
                background: #e5e7eb;
            }

            .step-circle.is-active,
            .step-circle.is-done {
                color: #fff;
                background: #8B7355;
            }

            .step-label {
                margin-left: 0.5rem;
                font-size: 0.75rem;
                transition: color .15s ease;
            }

            .step-label.is-pending {
                font-weight: 500;
                color: #9ca3af;
            }

            .step-label.is-active {
                font-weight: 600;
                color: #3C2F23;
            }

            .step-bar {
                width: 2.5rem;
                height: 2px;
                margin: 0 0.75rem;
                border-radius: 9999px;
                transition: background-color .15s ease;
            }

            @media (min-width: 640px) {
                .step-bar {
                    width: 4rem;
                }
            }

            .step-bar.is-pending {
                background: #e5e7eb;
            }

            .step-bar.is-done {
                background: #8B7355;
            }

            /* Spa modal photo grid — built by welcome.js's renderSpaPhotoGrid().
           The grid template depends on how many photos the branch actually
           uploaded, so the shape is driven off a data-count attribute rather
           than swapped class names (same reasoning as schedule.blade.php's
           [aria-pressed] styling: one attribute, no className churn). */
            .spa-photo-grid {
                display: grid;
                gap: 8px;
                border-radius: 16px;
                overflow: hidden;
                grid-template-columns: 1fr;
                grid-template-rows: 1fr;
                aspect-ratio: 4 / 3;
            }

            .spa-photo-cell {
                position: relative;
                display: block;
                width: 100%;
                height: 100%;
                padding: 0;
                margin: 0;
                border: 0;
                overflow: hidden;
                background: #f3f4f6;
                cursor: pointer;
            }

            .spa-photo-cell img {
                display: block;
                width: 100%;
                height: 100%;
                object-fit: cover;
                transition: transform .4s ease;
            }

            .spa-photo-cell:hover img {
                transform: scale(1.04);
            }

            .spa-photo-cell:focus-visible {
                outline: 2px solid #8B7355;
                outline-offset: -2px;
            }

            .spa-photo-cell::after {
                content: '';
                position: absolute;
                inset: 0;
                background: linear-gradient(to top, rgba(0, 0, 0, .22), transparent 55%);
                opacity: 0;
                transition: opacity .2s ease;
            }

            .spa-photo-cell:hover::after {
                opacity: 1;
            }

            /* Zero real uploads: the placeholder is shown but carries no gallery
           affordance at all — not clickable, no hover, no counter. Opening a
           viewer onto a known placeholder is a dead-end interaction. */
            .spa-photo-cell.is-empty {
                cursor: default;
            }

            .spa-photo-cell.is-empty:hover img {
                transform: none;
            }

            .spa-photo-cell.is-empty::after {
                display: none;
            }

            .spa-photo-empty-label {
                position: absolute;
                left: 50%;
                top: 50%;
                transform: translate(-50%, -50%);
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 14px;
                border-radius: 999px;
                background: rgba(0, 0, 0, .5);
                color: #fff;
                font-size: 12px;
                font-weight: 600;
            }

            /* Photo-count pill on the hero cell. Below sm this is the only entry
           point into the viewer, since the grid collapses to the hero alone. */
            .spa-photo-chip {
                position: absolute;
                left: 12px;
                bottom: 12px;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 12px;
                border-radius: 999px;
                background: rgba(0, 0, 0, .55);
                backdrop-filter: blur(2px);
                -webkit-backdrop-filter: blur(2px);
                color: #fff;
                font-size: 12px;
                font-weight: 600;
                pointer-events: none;
            }

            /* Below sm the four small cells would each be roughly 90px wide inside
           the Median wrapper, so only the hero renders; the rest stay reachable
           by swiping in the viewer. */
            .spa-photo-grid .spa-photo-cell:not(:first-child) {
                display: none;
            }

            @media (min-width: 640px) {
                .spa-photo-grid {
                    aspect-ratio: auto;
                    height: 380px;
                }

                .spa-photo-grid .spa-photo-cell:not(:first-child) {
                    display: block;
                }

                .spa-photo-grid[data-count="2"] {
                    grid-template-columns: repeat(3, 1fr);
                    grid-template-rows: 1fr;
                }

                .spa-photo-grid[data-count="2"] .spa-photo-cell:first-child {
                    grid-column: span 2;
                }

                .spa-photo-grid[data-count="3"] {
                    grid-template-columns: repeat(3, 1fr);
                    grid-template-rows: repeat(2, 1fr);
                }

                .spa-photo-grid[data-count="3"] .spa-photo-cell:first-child {
                    grid-column: span 2;
                    grid-row: span 2;
                }

                .spa-photo-grid[data-count="4"] {
                    grid-template-columns: repeat(4, 1fr);
                    grid-template-rows: repeat(2, 1fr);
                }

                .spa-photo-grid[data-count="4"] .spa-photo-cell:first-child {
                    grid-column: span 2;
                    grid-row: span 2;
                }

                .spa-photo-grid[data-count="4"] .spa-photo-cell:nth-child(2) {
                    grid-column: span 2;
                }

                .spa-photo-grid[data-count="5"] {
                    grid-template-columns: repeat(4, 1fr);
                    grid-template-rows: repeat(2, 1fr);
                }

                .spa-photo-grid[data-count="5"] .spa-photo-cell:first-child {
                    grid-column: span 2;
                    grid-row: span 2;
                }
            }

            /* Photo lightbox. Chrome is dark in both colour schemes by design, so
           there is no prefers-color-scheme branch for these rules. */
            /* Unlike the other modals this one is truly full-bleed (inset-0 with no
           top margin), so the close button would sit under the status bar in
           the Median shell. --safe-top is declared in landing.css and resolves
           to 0px everywhere else. */
            .lightbox-shell {
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                height: 100%;
                padding: calc(72px + var(--safe-top, 0px)) 16px 24px;
            }

            .lightbox-stage {
                position: relative;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                width: 100%;
                max-width: 1100px;
                max-height: 100%;
            }

            .lightbox-image {
                display: block;
                max-width: 100%;
                max-height: calc(100vh - 200px);
                object-fit: contain;
                border-radius: 12px;
                user-select: none;
                -webkit-user-drag: none;
                transition: opacity .18s ease;
            }

            @supports (height: 100dvh) {
                .lightbox-image {
                    max-height: calc(100dvh - 200px);
                }
            }

            .lightbox-image.is-swapping {
                opacity: 0;
            }

            .lightbox-footer {
                width: 100%;
                max-width: 720px;
                margin-top: 14px;
                text-align: center;
            }

            .lightbox-caption {
                font-size: 13px;
                font-weight: 500;
                line-height: 1.45;
                color: #fff;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .lightbox-caption.is-hidden {
                display: none;
            }

            .lightbox-counter {
                margin-top: 6px;
                font-size: 11px;
                letter-spacing: .04em;
                color: rgba(255, 255, 255, .7);
            }

            /* z-index is load-bearing: .lightbox-stage is also positioned, and the
           prev button precedes it in the DOM while the next button follows it.
           Without this the stage paints over prev but under next, so on narrow
           screens (where the stage fills the shell) only the next arrow shows. */
            .lightbox-btn {
                position: absolute;
                z-index: 2;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 44px;
                height: 44px;
                border: 0;
                border-radius: 9999px;
                background: rgba(255, 255, 255, .12);
                color: #fff;
                font-size: 16px;
                cursor: pointer;
                transition: background-color .15s ease;
            }

            .lightbox-btn:hover {
                background: rgba(255, 255, 255, .24);
            }

            .lightbox-btn:focus-visible {
                outline: 2px solid #C4A97D;
                outline-offset: 2px;
            }

            .lightbox-btn[hidden] {
                display: none;
            }

            .lightbox-btn-close {
                top: 16px;
                right: 16px;
            }

            .lightbox-btn-prev {
                left: 16px;
                top: 50%;
                transform: translateY(-50%);
            }

            .lightbox-btn-next {
                right: 16px;
                top: 50%;
                transform: translateY(-50%);
            }

            @media (max-width: 639px) {
                .lightbox-shell {
                    padding: calc(64px + var(--safe-top, 0px)) 8px 20px;
                }

                .lightbox-btn-prev {
                    left: 8px;
                }

                .lightbox-btn-next {
                    right: 8px;
                }

                /* At this width the stage fills the shell, so the arrows sit on top
               of the photo itself and need real contrast against it. */
                .lightbox-btn-prev,
                .lightbox-btn-next {
                    background: rgba(0, 0, 0, .55);
                }

                .lightbox-btn-prev:hover,
                .lightbox-btn-next:hover {
                    background: rgba(0, 0, 0, .7);
                }
            }

            @media (prefers-reduced-motion: reduce) {

                .spa-photo-cell img,
                .lightbox-image {
                    transition: none;
                }

                .spa-photo-cell:hover img {
                    transform: none;
                }
            }

            /* Dark mode (prefers-color-scheme) for the hero search dropdown.
           Plain CSS, not dark: utilities - no .dark class is ever added. */
            @media (prefers-color-scheme: dark) {
                .spa-photo-cell {
                    background: #374151;
                }

                .spa-photo-cell:focus-visible {
                    outline-color: #C4A97D;
                }

                .search-dropdown {
                    background: #1f2937;
                    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.08);
                }

                .search-dropdown-label {
                    color: #9ca3af;
                }

                .search-chip {
                    background: #374151;
                    color: #e5e7eb;
                    border-color: rgba(255, 255, 255, 0.08);
                }

                .search-chip:hover {
                    background: #4b5563;
                }

                .search-suggestion-row {
                    color: #e5e7eb;
                }

                .search-suggestion-row:hover {
                    background: #374151;
                }

                .search-empty-note {
                    color: #9ca3af;
                }

                .amenity-chip {
                    background: rgba(55, 65, 81, 0.5);
                    border-color: rgba(196, 169, 125, 0.15);
                }

                .amenity-chip-icon {
                    background: #1f2937;
                    border-color: rgba(255, 255, 255, 0.08);
                    color: #C4A97D;
                }

                .amenity-chip-label {
                    color: #f3f4f6;
                }

                .svc-card-thumb {
                    background: #374151;
                    border-color: rgba(255, 255, 255, 0.06);
                }

                .svc-card:hover {
                    background-color: rgba(55, 65, 81, 0.5);
                }

                .svc-card.is-selected {
                    border-left-color: #C4A97D;
                    background-color: rgba(55, 65, 81, 0.7);
                }

                .svc-card:focus-within {
                    outline-color: #C4A97D;
                }

                .svc-card-name {
                    color: #f3f4f6;
                }

                .svc-card-badge {
                    color: #C4A97D;
                    background: #374151;
                    border-color: rgba(255, 255, 255, 0.08);
                }

                .svc-card-price {
                    color: #C4A97D;
                }

                .svc-card-desc {
                    color: #9ca3af;
                }

                .svc-card-meta {
                    color: #C4A97D;
                }

                .svc-section-header {
                    color: #9ca3af;
                    background: #1f2937;
                }

                .svc-filter-tab {
                    color: #9ca3af;
                }

                .svc-filter-tab.is-active {
                    background: #8B7355;
                    color: #fff;
                }

                .slot-btn {
                    color: #f3f4f6;
                    background: #374151;
                    border-color: rgba(255, 255, 255, 0.08);
                }

                .slot-btn:hover:not(:disabled) {
                    border-color: #C4A97D;
                    background-color: rgba(55, 65, 81, 0.8);
                }

                .slot-btn.is-selected {
                    border-color: #C4A97D;
                    background-color: #8B7355;
                    color: #fff;
                }

                .slot-btn:disabled {
                    background-color: #1f2937;
                    color: #4b5563;
                    border-color: #374151;
                }

                .slot-btn.is-past-closing:disabled {
                    border-color: #4b5563;
                }

                .step-circle.is-pending {
                    background: #374151;
                    color: #9ca3af;
                }

                .step-circle.is-active,
                .step-circle.is-done {
                    background: #8B7355;
                    color: #fff;
                }

                .step-label.is-active {
                    color: #f3f4f6;
                }

                .step-label.is-pending {
                    color: #6b7280;
                }

                .step-bar.is-pending {
                    background: #374151;
                }

                .step-bar.is-done {
                    background: #C4A97D;
                }
            }
        </style>
        <div id="bookingModal" class="fixed inset-0 z-[110] hidden">
            <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" data-close-booking-modal></div>
            <div class="relative mx-auto w-[92%] max-w-2xl mt-10 sm:mt-16">
                <div
                    class="overflow-hidden bg-white shadow-2xl dark:bg-gray-800 rounded-3xl ring-1 ring-black/10 dark:ring-white/10">
                    <div
                        class="flex items-center justify-between px-6 py-4 border-b border-black/5 dark:border-white/10">
                        <div>
                            <h3 class="text-lg font-semibold text-[#3C2F23] dark:text-white">Make a Reservation</h3>
                            <p id="bookingSpaMeta" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Spa • Branch
                            </p>
                        </div>
                        <button type="button"
                            class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5 dark:hover:bg-white/10"
                            data-close-booking-modal aria-label="Close">
                            <i class="text-lg text-gray-700 dark:text-gray-300 fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    {{-- Step indicator — plain CSS state classes (.is-pending/.is-active/.is-done),
                     since welcome.js's showBookingStep() rewrites className wholesale on every
                     step change and isn't scanned by Tailwind's content config. --}}
                    @auth
                        <div class="px-6 pt-5">
                            <div class="flex items-center justify-center overflow-x-auto">
                                <div class="flex items-center min-w-max">
                                    <div class="flex items-center">
                                        <div data-step-circle="1" class="step-circle is-active">1</div>
                                        <span data-step-label="1" class="step-label is-active">Service</span>
                                    </div>
                                    <div data-step-bar="1" class="step-bar is-pending"></div>
                                    <div class="flex items-center">
                                        <div data-step-circle="2" class="step-circle is-pending">2</div>
                                        <span data-step-label="2" class="step-label is-pending">Date &amp; Time</span>
                                    </div>
                                    <div data-step-bar="2" class="step-bar is-pending"></div>
                                    <div class="flex items-center">
                                        <div data-step-circle="3" class="step-circle is-pending">3</div>
                                        <span data-step-label="3" class="step-label is-pending">Confirm</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endauth

                    <div class="overflow-y-auto max-h-[65vh] p-6">
                        @auth
                            <form method="POST" action="{{ route('bookings.online.checkout') }}" id="bookingForm"
                                class="space-y-4">
                                @csrf
                                <input type="hidden" name="spa_id" id="bookingSpaIdInput">
                                <input type="hidden" name="branch_id" id="bookingBranchIdInput">

                                {{-- ============ STEP 1 — SERVICE ============ --}}
                                <div data-booking-step="1">
                                    <div>
                                        <div class="flex items-center justify-between gap-2">
                                            <label
                                                class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Treatment
                                                / Package</label>
                                            <div class="inline-flex gap-1 p-1 bg-gray-100 rounded-lg dark:bg-gray-700"
                                                id="bookingServiceFilterTabs">
                                                <button type="button" class="svc-filter-tab is-active"
                                                    data-service-filter="all">All</button>
                                                <button type="button" class="svc-filter-tab"
                                                    data-service-filter="treatment">Treatments</button>
                                                <button type="button" class="svc-filter-tab"
                                                    data-service-filter="package">Packages</button>
                                            </div>
                                        </div>
                                        <div class="relative mt-2">
                                            <i
                                                class="absolute text-xs text-gray-300 -translate-y-1/2 pointer-events-none dark:text-gray-500 fa-solid fa-magnifying-glass left-3 top-1/2"></i>
                                            <input type="text" id="bookingServiceSearch"
                                                placeholder="Search services…" autocomplete="off"
                                                class="w-full py-2 pl-9 pr-3 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400 ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                        </div>
                                        <div id="bookingServiceList"
                                            class="mt-2 overflow-y-auto bg-white border divide-y dark:bg-gray-800 max-h-60 rounded-xl border-black/10 dark:border-white/10 ring-1 ring-black/5 dark:ring-white/10 divide-black/5 dark:divide-white/10">
                                            <p class="px-4 py-6 text-sm text-center text-gray-400 dark:text-gray-500">
                                                Select a spa to see its services.</p>
                                        </div>
                                        <p id="bookingTreatmentError"
                                            class="hidden mt-1 text-[11px] text-red-500 dark:text-red-400">

                                            <i class="fa-solid fa-circle-exclamation"></i>
                                            Please select a treatment or package.
                                        </p>
                                    </div>

                                    <div class="mt-4">
                                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Service
                                            Type</label>
                                        <select name="service_type" id="bookingServiceType"
                                            class="w-full mt-1 rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40 disabled:bg-gray-100 dark:disabled:bg-gray-800 disabled:text-gray-400 dark:disabled:text-gray-500 disabled:cursor-not-allowed disabled:opacity-80">
                                            <option value="">Select service type</option>
                                        </select>
                                        <p id="bookingServiceTypeHint"
                                            class="mt-1 text-[11px] text-gray-500 dark:text-gray-400"></p>
                                    </div>

                                    <div id="addressWrapper" class="hidden mt-4">
                                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                            Home Address <span class="text-red-500">*</span>
                                        </label>
                                        {{-- Prefilled from the saved home location (UI rework Phase 5); still editable per booking. --}}
                                        <input type="text" name="customer_address" id="bookingAddressInput"
                                            data-default-address="{{ auth()->user()?->address }}"
                                            value="{{ auth()->user()?->address }}"
                                            class="w-full mt-1 rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40"
                                            placeholder="Enter your full address">
                                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Required for home
                                            service bookings.</p>
                                    </div>
                                </div>

                                {{-- Day tabs and time rows are built in welcome.js, so they are
                                     styled with plain CSS here, not Tailwind classes. --}}
                                <style>
                                    .bk-cal-nav {
                                        display: flex; align-items: center; justify-content: center;
                                        width: 44px; height: 44px; border-radius: 12px; color: #374151;
                                    }
                                    .bk-cal-nav:hover:not(:disabled) { background: rgba(0, 0, 0, .05); }
                                    .bk-cal-nav:disabled { opacity: .3; cursor: not-allowed; }

                                    .bk-cal-msg { grid-column: 1 / -1; padding: 14px 0; text-align: center; font-size: 13px; color: #6b7280; }

                                    .bk-cal-day {
                                        position: relative; display: flex; align-items: center; justify-content: center;
                                        height: 40px; border-radius: 12px; font-size: 14px; color: #374151;
                                        transition: background-color .15s ease;
                                    }
                                    .bk-cal-day:hover:not(:disabled) { background: #F6EFE6; }
                                    .bk-cal-day:disabled { color: #d1d5db; cursor: default; }
                                    .bk-cal-day[aria-pressed="true"] { background: #8B7355; color: #fff; font-weight: 700; }
                                    .bk-cal-dot {
                                        position: absolute; bottom: 4px; width: 4px; height: 4px;
                                        border-radius: 9999px; background: #8B7355;
                                    }
                                    .bk-cal-day[aria-pressed="true"] .bk-cal-dot { background: #fff; }

                                    .bk-timeline { display: flex; flex-direction: column; gap: 6px; max-height: 300px; overflow-y: auto; padding-right: 2px; }
                                    .slot-btn.bk-slot {
                                        display: flex; align-items: center; justify-content: space-between; gap: 12px;
                                        width: 100%; min-height: 44px; padding: 8px 14px; text-align: left;
                                    }
                                    .slot-btn.bk-slot:disabled { color: #6b7280; }
                                    .bk-slot-time { font-weight: 600; white-space: nowrap; }
                                    .bk-slot-note { font-size: 11px; text-align: right; }

                                    @media (prefers-color-scheme: dark) {
                                        .bk-cal-nav { color: #d1d5db; }
                                        .bk-cal-nav:hover:not(:disabled) { background: rgba(255, 255, 255, .1); }
                                        .bk-cal-msg { color: #9ca3af; }
                                        .bk-cal-day { color: #d1d5db; }
                                        .bk-cal-day:hover:not(:disabled) { background: #374151; }
                                        .bk-cal-day:disabled { color: #4b5563; }
                                        .bk-cal-day[aria-pressed="true"] { background: #C4A97D; color: #1f2937; }
                                        .bk-cal-dot { background: #C4A97D; }
                                        .bk-cal-day[aria-pressed="true"] .bk-cal-dot { background: #1f2937; }
                                        .slot-btn.bk-slot:disabled { color: #9ca3af; }
                                    }
                                </style>

                                {{-- ============ STEP 2 — DATE & TIME ============ --}}
                                <div data-booking-step="2" class="hidden">
                                    <div>
                                        <span class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Appointment
                                            Date</span>

                                        <div class="flex items-center justify-between mt-2">
                                            <button type="button" id="bookingCalPrev" class="bk-cal-nav"
                                                aria-label="Previous month" disabled>
                                                <i class="text-sm fa-solid fa-chevron-left" aria-hidden="true"></i>
                                            </button>
                                            <p id="bookingCalTitle"
                                                class="text-sm font-semibold text-[#3C2F23] dark:text-white"></p>
                                            <button type="button" id="bookingCalNext" class="bk-cal-nav"
                                                aria-label="Next month">
                                                <i class="text-sm fa-solid fa-chevron-right" aria-hidden="true"></i>
                                            </button>
                                        </div>

                                        <div class="grid grid-cols-7 mt-1 mb-2">
                                            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                                                <div
                                                    class="py-2 text-xs font-semibold text-center text-gray-400 dark:text-gray-500">
                                                    {{ $day }}</div>
                                            @endforeach
                                        </div>
                                        <div id="bookingCalGrid" class="grid grid-cols-7 gap-1"></div>

                                        <p class="flex items-center gap-1.5 mt-3 text-[11px] text-gray-500 dark:text-gray-400">
                                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-[#8B7355] dark:bg-[#C4A97D]"></span>
                                            You already have an appointment on this day. Greyed-out days are closed
                                            or outside the 30-day booking window.
                                        </p>

                                        <input type="hidden" name="appointment_date" id="bookingDateInput">
                                    </div>

                                    <div class="mt-4">
                                        <div class="flex items-center justify-between">
                                            <label
                                                class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Available
                                                Times</label>
                                            <span id="bookingSlotsLoading"
                                                class="hidden text-[11px] text-gray-400 dark:text-gray-500">
                                                <i class="fa-solid fa-spinner fa-spin"></i> Checking availability…
                                            </span>
                                        </div>
                                        <div id="bookingSlotGrid" class="mt-2 bk-timeline">
                                            <p
                                                class="col-span-3 py-6 text-sm text-center text-gray-400 dark:text-gray-500 sm:col-span-4">
                                                Pick a date to see available times.</p>
                                        </div>
                                        <div id="bookingSlotLegend"
                                            class="hidden flex-wrap gap-3 mt-3 text-[11px] text-gray-500 dark:text-gray-400">
                                            <span class="flex items-center gap-1.5"><span
                                                    class="inline-block border border-gray-200 dark:border-gray-600 rounded w-2.5 h-2.5 bg-gray-50 dark:bg-gray-800"></span>
                                                Fully booked</span>
                                            <span class="flex items-center gap-1.5"><span
                                                    class="inline-block border border-gray-300 dark:border-gray-500 border-dashed rounded w-2.5 h-2.5"></span>
                                                Not enough time before closing</span>
                                        </div>
                                        <input type="hidden" name="start_time" id="bookingTimeInput">
                                        <p id="bookingTimeError"
                                            class="hidden mt-2 text-[11px] text-red-500 dark:text-red-400">
                                            <i class="fa-solid fa-circle-exclamation"></i>
                                            <span id="bookingTimeErrorText">Please select an available time.</span>
                                        </p>
                                    </div>
                                </div>

                                {{-- ============ STEP 3 — YOUR DETAILS & CONFIRM ============ --}}
                                <div data-booking-step="3" class="hidden">
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label
                                                class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Full
                                                Name</label>
                                            <input type="text" name="customer_name" id="bookingCustomerName"
                                                value="{{ auth()->user()->name }}" readonly
                                                class="w-full mt-1 text-gray-700 bg-gray-100 dark:text-gray-300 dark:bg-gray-700 rounded-xl border-black/10 dark:border-white/10 ring-1 ring-black/5 dark:ring-white/10">
                                        </div>
                                        <div>
                                            <label
                                                class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Email</label>
                                            <input type="email" name="customer_email" id="bookingCustomerEmail"
                                                value="{{ auth()->user()->email }}" readonly
                                                class="w-full mt-1 text-gray-700 bg-gray-100 dark:text-gray-300 dark:bg-gray-700 rounded-xl border-black/10 dark:border-white/10 ring-1 ring-black/5 dark:ring-white/10">
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Phone
                                            Number</label>
                                        <input type="text" name="customer_phone" id="bookingCustomerPhone"
                                            value="{{ auth()->user()->phone }}"
                                            data-default-phone="{{ auth()->user()->phone }}" placeholder="09xxxxxxxxx"
                                            maxlength="11"
                                            class="w-full mt-1 rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">We'll use this to
                                            reach you about your booking and for GCash/Maya payment.</p>
                                    </div>

                                    <div
                                        class="p-4 mt-4 space-y-2 text-sm border border-[#E8DDD0] dark:border-gray-700 rounded-xl bg-[#FDFAF6] dark:bg-gray-900/40">
                                        <p class="text-[10px] font-semibold tracking-wider text-gray-400 uppercase">Booking
                                            Summary</p>
                                        <div class="flex justify-between gap-3">
                                            <span class="text-gray-500 dark:text-gray-400">Service</span>
                                            <span id="recapService"
                                                class="font-medium text-[#3C2F23] dark:text-white text-right"></span>
                                        </div>
                                        <div class="flex justify-between gap-3">
                                            <span class="text-gray-500 dark:text-gray-400">Type</span>
                                            <span id="recapServiceType"
                                                class="font-medium text-[#3C2F23] dark:text-white"></span>
                                        </div>
                                        <div id="recapAddressRow" class="justify-between hidden gap-3">
                                            <span class="text-gray-500 dark:text-gray-400">Address</span>
                                            <span id="recapAddress"
                                                class="font-medium text-[#3C2F23] dark:text-white text-right"></span>
                                        </div>
                                        <div class="flex justify-between gap-3">
                                            <span class="text-gray-500 dark:text-gray-400">Date &amp; Time</span>
                                            <span id="recapDateTime"
                                                class="font-medium text-[#3C2F23] dark:text-white text-right"></span>
                                        </div>
                                        <div
                                            class="flex justify-between gap-3 pt-2 mt-2 border-t border-black/5 dark:border-white/10">
                                            <span class="text-gray-500 dark:text-gray-400">Total Service Price</span>
                                            <span id="recapTotalPrice"
                                                class="font-medium text-[#3C2F23] dark:text-white"></span>
                                        </div>
                                        <fieldset class="pt-2 mt-2 space-y-2 border-t border-black/5 dark:border-white/10">
                                            <legend class="pt-2 text-[10px] font-semibold tracking-wider text-gray-400 uppercase">
                                                How would you like to pay?</legend>

                                            <label
                                                class="flex items-center gap-3 p-3 border border-[#E8DDD0] dark:border-gray-700 rounded-xl cursor-pointer">
                                                <input type="radio" name="payment_option" value="downpayment" checked
                                                    class="w-4 h-4 accent-[#8B7355] cursor-pointer flex-shrink-0">
                                                <span class="flex-1">
                                                    <span class="block font-medium text-[#3C2F23] dark:text-white">Reservation
                                                        fee (20%)</span>
                                                    <span class="block text-xs text-gray-500 dark:text-gray-400">Pay the
                                                        rest at the spa.</span>
                                                </span>
                                                <span id="payOptDownAmount"
                                                    class="font-semibold text-[#6F5430] dark:text-[#C4A97D]"></span>
                                            </label>

                                            <label
                                                class="flex items-center gap-3 p-3 border border-[#E8DDD0] dark:border-gray-700 rounded-xl cursor-pointer">
                                                <input type="radio" name="payment_option" value="full"
                                                    class="w-4 h-4 accent-[#8B7355] cursor-pointer flex-shrink-0">
                                                <span class="flex-1">
                                                    <span class="block font-medium text-[#3C2F23] dark:text-white">Full
                                                        payment</span>
                                                    <span class="block text-xs text-gray-500 dark:text-gray-400">Nothing
                                                        to pay at the spa.</span>
                                                </span>
                                                <span id="payOptFullAmount"
                                                    class="font-semibold text-[#6F5430] dark:text-[#C4A97D]"></span>
                                            </label>
                                        </fieldset>

                                        <div
                                            class="flex justify-between gap-3 pt-2 mt-2 border-t border-black/5 dark:border-white/10">
                                            <span class="text-gray-500 dark:text-gray-400">Pay now</span>
                                            <span id="recapPayNow"
                                                class="font-semibold text-[#6F5430] dark:text-[#C4A97D]"></span>
                                        </div>
                                        <div class="flex justify-between gap-3">
                                            <span class="text-gray-500 dark:text-gray-400">Balance at the spa</span>
                                            <span id="recapBalance"
                                                class="font-medium text-[#3C2F23] dark:text-white"></span>
                                        </div>
                                    </div>

                                    <!-- Terms & Agreements -->
                                    <div
                                        class="p-4 mt-4 border border-[#E8DDD0] dark:border-gray-700 rounded-xl bg-[#FDFAF6] dark:bg-gray-900/40 space-y-3">
                                        <label class="flex items-center gap-3 pt-1 cursor-pointer group">
                                            <input type="checkbox" id="bookingTermsCheckbox" name="terms_agreed"
                                                value="1"
                                                class="w-4 h-4 rounded accent-[#8B7355] cursor-pointer flex-shrink-0">
                                            <span
                                                class="text-xs font-medium text-gray-700 dark:text-gray-300 group-hover:text-[#6F5430] dark:group-hover:text-[#C4A97D] transition">
                                                I have read and agree to the
                                                <button type="button" onclick="openTermsModal()"
                                                    class="text-[#8B7355] dark:text-[#C4A97D] underline underline-offset-2 hover:text-[#6F5430] dark:hover:text-white transition font-semibold">
                                                    terms and conditions
                                                </button>.
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                {{-- ============ STEP NAVIGATION ============ --}}
                                <div class="flex items-center justify-between pt-2">
                                    <button type="button" id="bookingBackBtn"
                                        class="hidden px-5 py-2.5 text-sm font-semibold text-gray-600 dark:text-gray-300 transition rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <i class="mr-1 fa-solid fa-arrow-left"></i> Back
                                    </button>
                                    <div class="flex-1"></div>
                                    <button type="button" id="bookingNextBtn"
                                        class="booking-btn text-white px-6 py-2.5 rounded-xl text-sm font-semibold shadow-md hover:shadow-lg transition active:translate-y-0.5">
                                        Continue <i class="ml-1 fa-solid fa-arrow-right"></i>
                                    </button>
                                    <button type="submit" id="bookingSubmitBtn"
                                        class="hidden booking-btn text-white px-6 py-2.5 rounded-xl text-sm font-semibold shadow-md hover:shadow-lg transition active:translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed disabled:active:translate-y-0">
                                        Reserve An Appointment
                                    </button>
                                </div>
                            </form>
                        @else
                            <div
                                class="p-4 rounded-2xl bg-[#F6EFE6]/70 dark:bg-gray-700/50 ring-1 ring-black/5 dark:ring-white/10">
                                <p class="text-sm text-gray-700 dark:text-gray-300">Please log in to book an appointment.
                                </p>
                                <a href="{{ route('login') }}"
                                    class="block mt-4 text-center booking-btn text-white py-3 rounded-xl text-sm font-semibold shadow-md hover:shadow-lg transition active:translate-y-0.5">
                                    Login to Continue
                                </a>
                            </div>
                        @endauth
                    </div>
                </div>
                <div class="h-10"></div>
            </div>
        </div>

        <!-- ================= JOB APPLICATION MODAL ================= -->
        <div id="applicationModal" class="fixed inset-0 z-[115] hidden">
            <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeApplicationModal()"></div>
            <div class="relative mx-auto w-[92%] max-w-3xl mt-8 mb-8">
                <div
                    class="overflow-hidden bg-white dark:bg-gray-800 shadow-2xl rounded-3xl ring-1 ring-black/10 dark:ring-white/10 flex flex-col max-h-[90vh]">
                    <div
                        class="flex items-center justify-between px-6 py-4 border-b border-black/5 dark:border-white/10">
                        <div>
                            <h3 class="text-lg font-semibold text-[#3C2F23] dark:text-white">Apply for a Position</h3>
                            <p id="applicationSpaMeta" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Spa
                                Name</p>
                        </div>
                        <button type="button" onclick="closeApplicationModal()"
                            class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5 dark:hover:bg-white/10">
                            <i class="text-lg text-gray-700 dark:text-gray-300 fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div class="p-6 overflow-y-auto">
                        <form id="applicationForm" method="POST" class="space-y-6" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="spa_id" id="applicationSpaId">
                            <input type="hidden" name="branch_id" id="applicationBranchId">
                            <input type="hidden" name="source" value="website">

                            {{-- PERSONAL INFORMATION --}}
                            <div>
                                <h4
                                    class="flex items-center gap-2 mb-3 text-xs font-bold tracking-widest text-[#8B7355] dark:text-[#C4A97D] uppercase">
                                    <i class="fa-solid fa-user"></i> Personal Information
                                </h4>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <label
                                            class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Full
                                            Name <span class="text-red-500">*</span></label>
                                        <input type="text" name="full_name" required
                                            class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                    </div>
                                    <div>
                                        <label
                                            class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Email
                                            <span class="text-red-500">*</span></label>
                                        <input type="email" name="email" required
                                            class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                    </div>
                                    <div>
                                        <label
                                            class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Phone
                                            <span class="text-red-500">*</span></label>
                                        <input type="text" name="phone" required maxlength="11"
                                            pattern="^09\d{9}$" inputmode="numeric" placeholder="09xxxxxxxxx"
                                            title="Phone number must be 11 digits starting with 09"
                                            class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                    </div>
                                    <div>
                                        <label
                                            class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Gender</label>
                                        <select name="gender"
                                            class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                            <option value="">Select</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Date
                                            of Birth</label>
                                        <input type="date" name="date_of_birth"
                                            class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                    </div>
                                    <div>
                                        <label
                                            class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Civil
                                            Status</label>
                                        <select name="civil_status"
                                            class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                            <option value="">Select</option>
                                            <option value="single">Single</option>
                                            <option value="married">Married</option>
                                            <option value="widowed">Widowed</option>
                                            <option value="separated">Separated</option>
                                        </select>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label
                                            class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Address
                                            <span class="text-red-500">*</span></label>
                                        <input type="text" name="address" required
                                            placeholder="Street, Barangay, City"
                                            class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                    </div>
                                </div>
                            </div>

                            <hr class="border-[#E8DDD0] dark:border-gray-700">

                            {{-- POSITION DETAILS --}}
                            <div>
                                <h4
                                    class="flex items-center gap-2 mb-3 text-xs font-bold tracking-widest text-[#8B7355] dark:text-[#C4A97D] uppercase">
                                    <i class="fa-solid fa-briefcase"></i> Position Details
                                </h4>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <label
                                            class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Applying
                                            For <span class="text-red-500">*</span></label>
                                        <select name="position_applied" required
                                            class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                            <option value="">Select</option>
                                            <option value="therapist">Therapist</option>
                                            <option value="receptionist">Receptionist</option>
                                            <option value="manager">Manager</option>
                                            <option value="hr">HR</option>
                                            <option value="finance">Finance</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Shift
                                            Availability</label>
                                        <select name="availability"
                                            class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                            <option value="">Select</option>
                                            <option value="full_time">Full Time</option>
                                            <option value="part_time">Part Time</option>
                                            <option value="weekdays">Weekdays Only</option>
                                            <option value="weekends">Weekends Only</option>
                                            <option value="shifting">Shifting</option>
                                            <option value="flexible">Flexible</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Expected
                                            Start Date</label>
                                        <input type="date" name="expected_start_date"
                                            class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                    </div>
                                    <div>
                                        <label
                                            class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Educational
                                            Attainment</label>
                                        <select name="education"
                                            class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                            <option value="">Select</option>
                                            <option value="high_school">High School</option>
                                            <option value="vocational">Vocational</option>
                                            <option value="undergraduate">Undergraduate</option>
                                            <option value="college">College Graduate</option>
                                            <option value="postgrad">Post Graduate</option>
                                        </select>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label
                                            class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">
                                            Resume / CV <span class="text-red-500">*</span>
                                        </label>

                                        <input
                                            type="file"
                                            name="resume"
                                            accept=".pdf,.doc,.docx"
                                            required
                                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        >

                                        <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                                            Required for online applications. PDF, DOC, or DOCX, maximum 5MB.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <hr class="border-[#E8DDD0] dark:border-gray-700">

                            {{-- EMERGENCY CONTACT --}}
                            <div>
                                <h4
                                    class="flex items-center gap-2 mb-3 text-xs font-bold tracking-widest text-[#8B7355] dark:text-[#C4A97D] uppercase">
                                    <i class="fa-solid fa-phone-volume"></i> Emergency Contact
                                </h4>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div>
                                        <label
                                            class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Contact
                                            Person</label>
                                        <input type="text" name="emergency_contact_name"
                                            class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                    </div>
                                    <div>
                                        <label
                                            class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Relationship</label>
                                        <input type="text" name="emergency_contact_relation"
                                            placeholder="e.g. Mother"
                                            class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                    </div>
                                    <div>
                                        <label
                                            class="block mb-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Contact
                                            Number</label>
                                        <input type="text" name="emergency_contact_phone" maxlength="11"
                                            pattern="^09\d{9}$" inputmode="numeric" placeholder="09xxxxxxxxx"
                                            class="w-full px-3 py-2 text-sm rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40">
                                    </div>
                                </div>
                            </div>

                            {{-- RESUME UPLOAD STATUS --}}
                            <div id="resumeUploadStatus"
                                class="hidden p-3 rounded-xl bg-[#F6EFE6]/60 dark:bg-gray-700/50 ring-1 ring-black/5 dark:ring-white/10">
                                <div class="flex items-center gap-3">
                                    <i class="fa-solid fa-file-pdf text-[#8B7355] dark:text-[#C4A97D] text-lg"></i>
                                    <div class="flex-1">
                                        <p id="resumeFileName"
                                            class="text-sm font-medium text-[#3C2F23] dark:text-white"></p>
                                        <p id="resumeFileSize" class="text-xs text-gray-500 dark:text-gray-400"></p>
                                    </div>
                                    <button type="button" onclick="clearResumeUpload()"
                                        class="text-red-500 transition hover:text-red-700">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </div>

                            <div id="applicationError"
                                class="hidden p-3 text-sm text-red-600 dark:text-red-300 rounded-xl bg-red-50 dark:bg-red-900/20 ring-1 ring-red-200 dark:ring-red-800">
                                <i class="mr-1 fa-solid fa-circle-exclamation"></i>
                                <span id="applicationErrorText"></span>
                            </div>

                            <button type="submit" id="applicationSubmitBtn"
                                class="w-full booking-btn text-white py-3 rounded-xl text-sm font-semibold shadow-md hover:shadow-lg transition active:translate-y-0.5">
                                <i class="mr-2 fa-solid fa-paper-plane"></i>
                                Submit Application
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TERMS MODAL ================= -->
        <div id="termsModal" class="fixed inset-0 z-[120] hidden">
            <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeTermsModal()"></div>
            <div class="relative mx-auto w-[92%] max-w-lg mt-10 sm:mt-16">
                <div
                    class="overflow-hidden bg-white shadow-2xl dark:bg-gray-800 rounded-3xl ring-1 ring-black/10 dark:ring-white/10">
                    <div
                        class="flex items-center justify-between px-6 py-4 border-b border-black/5 dark:border-white/10">
                        <h3 class="text-lg font-semibold text-[#3C2F23] dark:text-white">Terms & Conditions</h3>
                        <button type="button" onclick="closeTermsModal()"
                            class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5 dark:hover:bg-white/10">
                            <i class="text-lg text-gray-700 dark:text-gray-300 fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div class="overflow-y-auto max-h-[60vh] p-6">
                        <ul class="space-y-4 text-xs leading-relaxed text-gray-600 dark:text-gray-400">
                            <li class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/50 dark:bg-gray-700/40">
                                <i
                                    class="fa-solid fa-circle-check text-[#8B7355] dark:text-[#C4A97D] mt-0.5 flex-shrink-0 text-sm"></i>
                                <div>
                                    <p class="font-semibold text-[#3C2F23] dark:text-white mb-1">Downpayment</p>
                                    <p>A 20% non-refundable downpayment is required to confirm your reservation. You
                                        may pay the remaining 80% at the spa on the day of your appointment, or pay
                                        the full amount online when you book.</p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/50 dark:bg-gray-700/40">
                                <i
                                    class="fa-solid fa-circle-check text-[#8B7355] dark:text-[#C4A97D] mt-0.5 flex-shrink-0 text-sm"></i>
                                <div>
                                    <p class="font-semibold text-[#3C2F23] dark:text-white mb-1">Cancellation</p>
                                    <p>Cancellations must be made at least 24 hours before your appointment. The 20%
                                        downpayment is non-refundable regardless of cancellation timing. If you paid
                                        in full, the remaining 80% is refunded by the spa directly, at the branch or
                                        by contacting the spa's phone number. Rescheduling keeps your payment.</p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3 p-3 rounded-xl bg-[#F6EFE6]/50 dark:bg-gray-700/40">
                                <i
                                    class="fa-solid fa-circle-check text-[#8B7355] dark:text-[#C4A97D] mt-0.5 flex-shrink-0 text-sm"></i>
                                <div>
                                    <p class="font-semibold text-[#3C2F23] dark:text-white mb-1">No-Show Policy</p>
                                    <p>Failure to arrive without prior notice will forfeit your 20% downpayment and may
                                        result in restricted future bookings on this platform. If you paid in full,
                                        the remaining 80% is still refundable by the spa directly.</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <div class="px-6 pt-2 pb-6">
                        <button type="button" onclick="closeTermsModal()"
                            class="w-full py-3 rounded-xl text-sm font-semibold text-white booking-btn shadow-md hover:shadow-lg transition active:translate-y-0.5">
                            I Understand
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= RATING MODAL ================= -->
        <div id="ratingModal" class="fixed inset-0 z-[140] hidden">
            <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeRatingModal()"></div>
            <div class="relative mx-auto w-[92%] max-w-lg mt-10 sm:mt-16">
                <div
                    class="overflow-hidden bg-white shadow-2xl dark:bg-gray-800 rounded-3xl ring-1 ring-black/10 dark:ring-white/10">
                    <div
                        class="flex items-center justify-between px-6 py-4 border-b border-black/5 dark:border-white/10">
                        <div>
                            <h3 class="text-lg font-semibold text-[#3C2F23] dark:text-white">Rate Your Experience</h3>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Help us improve our service
                                quality</p>
                        </div>
                        <button type="button" onclick="closeRatingModal()"
                            class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5 dark:hover:bg-white/10">
                            <i class="text-lg text-gray-700 dark:text-gray-300 fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div class="overflow-y-auto max-h-[65vh] p-6 space-y-6">
                        <input type="hidden" id="ratingBookingId">
                        <input type="hidden" id="selectedRating" value="0">
                        <input type="hidden" id="selectedSpaRating" value="0">

                        <!-- ── SPA SECTION ── -->
                        <div
                            class="p-4 rounded-xl bg-[#F6EFE6]/60 dark:bg-gray-700/50 ring-1 ring-black/5 dark:ring-white/10 space-y-4">
                            <div>
                                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Spa</p>
                                <p id="ratingSpaName"
                                    class="mt-1 text-base font-semibold text-[#3C2F23] dark:text-white"></p>
                                <div class="flex items-center gap-1 mt-1">
                                    <i
                                        class="fa-solid fa-location-dot text-[#8B7355] dark:text-[#C4A97D] text-[10px]"></i>
                                    <p id="ratingSpaBranchLocation"
                                        class="text-xs text-gray-500 dark:text-gray-400"></p>
                                </div>
                            </div>

                            <div>
                                <label class="block mb-2 text-xs font-semibold text-gray-600 dark:text-gray-300">
                                    Overall Spa Rating <span class="text-red-500">*</span>
                                </label>
                                <div class="flex items-center gap-2">
                                    <div class="flex gap-1">
                                        {!! implode(
                                            '',
                                            array_map(
                                                fn($i) => '<button type="button" onclick="setSpaRating(' .
                                                    $i .
                                                    ')"
                                                                                    class="transition focus:outline-none hover:scale-110">
                                                                                    <i id="spa-star-' .
                                                    $i .
                                                    '" class="text-2xl text-gray-300 dark:text-gray-600 fa-solid fa-star"></i>
                                                                                </button>',
                                                range(1, 5),
                                            ),
                                        ) !!}
                                    </div>
                                    <span id="spaRatingLabel"
                                        class="ml-2 text-sm text-gray-500 dark:text-gray-400">Select rating</span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Comment
                                    (optional)</label>
                                <textarea id="spaComment" rows="2" maxlength="500"
                                    placeholder="e.g., Clean facilities, relaxing ambiance..."
                                    class="w-full mt-1 rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40 text-sm resize-none"></textarea>
                                <p class="mt-1 text-right text-[10px] text-gray-400 dark:text-gray-500"><span
                                        id="spaCommentCount">0</span>/500</p>
                            </div>
                            {{-- PHOTO UPLOAD --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                    Add Photos <span class="font-normal text-gray-400">(optional — up to 3, 5MB
                                        each)</span>
                                </label>
                                <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                                    Photos of the facility, cleanliness, or your experience help other customers see
                                    what to expect — good or bad.
                                </p>

                                <input type="file" id="spaRatingPhotoInput" accept="image/*" multiple
                                    class="hidden">

                                <button type="button"
                                    onclick="document.getElementById('spaRatingPhotoInput').click()"
                                    class="mt-2 inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-[#8B7355] dark:text-[#C4A97D] border border-dashed border-[#8B7355]/40 dark:border-[#C4A97D]/40 rounded-xl hover:bg-[#F6EFE6] dark:hover:bg-gray-700 transition">
                                    <i class="fa-solid fa-camera"></i>
                                    Upload Photos
                                </button>

                                <div id="spaRatingPhotoPreview" class="grid grid-cols-3 gap-2 mt-3"></div>

                                <p id="spaRatingPhotoError"
                                    class="hidden mt-1 text-[11px] text-red-500 dark:text-red-400">
                                    <i class="fa-solid fa-circle-exclamation"></i>
                                    <span id="spaRatingPhotoErrorText"></span>
                                </p>
                            </div>
                        </div>

                        <hr class="border-[#E8DDD0] dark:border-gray-700">

                        <!-- ── THERAPIST SECTION ── -->
                        <div
                            class="p-4 rounded-xl bg-[#F6EFE6]/60 dark:bg-gray-700/50 ring-1 ring-black/5 dark:ring-white/10 space-y-4">
                            <div>
                                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Therapist
                                </p>
                                <p id="ratingTherapistName"
                                    class="mt-1 text-base font-semibold text-[#3C2F23] dark:text-white"></p>
                                <div class="flex items-center gap-1 mt-1">
                                    <i
                                        class="fa-solid fa-location-dot text-[#8B7355] dark:text-[#C4A97D] text-[10px]"></i>
                                    <p id="ratingBranchLocation" class="text-xs text-gray-500 dark:text-gray-400">
                                    </p>
                                </div>
                            </div>

                            <div>
                                <label class="block mb-2 text-xs font-semibold text-gray-600 dark:text-gray-300">
                                    Your Rating <span class="text-red-500">*</span>
                                </label>
                                <div class="flex items-center gap-2">
                                    <div class="flex gap-1">
                                        {!! implode(
                                            '',
                                            array_map(
                                                fn($i) => '<button type="button" onclick="setRating(' .
                                                    $i .
                                                    ')"
                                                                                    class="transition focus:outline-none hover:scale-110">
                                                                                    <i id="star-' .
                                                    $i .
                                                    '" class="text-2xl text-gray-300 dark:text-gray-600 fa-solid fa-star"></i>
                                                                                </button>',
                                                range(1, 5),
                                            ),
                                        ) !!}
                                    </div>
                                    <span id="ratingLabel"
                                        class="ml-2 text-sm text-gray-500 dark:text-gray-400">Select rating</span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">What went
                                    well?</label>
                                <textarea id="ratingComment" rows="2" maxlength="500"
                                    placeholder="e.g., Great massage, very professional..."
                                    class="w-full mt-1 rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40 text-sm resize-none"></textarea>
                                <p class="mt-1 text-right text-[10px] text-gray-400 dark:text-gray-500"><span
                                        id="ratingCommentCount">0</span>/500</p>
                            </div>

                            <div>
                                <label
                                    class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Suggestions
                                    for improvement (optional)</label>
                                <textarea id="ratingFeedback" rows="2" maxlength="1000"
                                    placeholder="e.g., Could be more attentive, room was cold..."
                                    class="w-full mt-1 rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40 text-sm resize-none"></textarea>
                                <p class="mt-1 text-right text-[10px] text-gray-400 dark:text-gray-500"><span
                                        id="ratingFeedbackCount">0</span>/1000</p>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 pb-6 space-y-2">
                        <button type="button" id="ratingSubmitBtn" onclick="submitRating()"
                            class="w-full py-3 rounded-xl text-sm font-semibold text-white booking-btn shadow-md hover:shadow-lg transition active:translate-y-0.5">
                            <i class="mr-2 fa-solid fa-paper-plane"></i>
                            Submit Rating
                        </button>
                        <button type="button" onclick="closeRatingModal()"
                            class="w-full py-3 rounded-xl text-sm font-semibold text-[#8B7355] dark:text-[#C4A97D] border border-[#8B7355] dark:border-[#C4A97D] hover:bg-[#F6EFE6] dark:hover:bg-gray-700 transition">
                            Maybe Later
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= RATE REMINDER MODAL ================= -->
        @auth
            @role('customer')
                <div id="rateReminderModal" class="fixed inset-0 z-[135] hidden">
                    <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="dismissRateReminder()"></div>
                    <div class="relative mx-auto w-[92%] max-w-lg mt-10 sm:mt-16">
                        <div
                            class="overflow-hidden bg-white shadow-2xl dark:bg-gray-800 rounded-3xl ring-1 ring-black/10 dark:ring-white/10">
                            <div
                                class="flex items-center justify-between px-6 py-4 border-b border-black/5 dark:border-white/10">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex items-center justify-center w-10 h-10 bg-[#F6EFE6] dark:bg-gray-700 rounded-2xl ring-1 ring-black/5 dark:ring-white/10">
                                        <i class="fa-solid fa-star text-[#D2A85B]"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-[#3C2F23] dark:text-white">How was your visit?
                                        </h3>
                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">You have unrated
                                            appointments</p>
                                    </div>
                                </div>
                                <button type="button" onclick="dismissRateReminder()"
                                    class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5 dark:hover:bg-white/10">
                                    <i class="text-lg text-gray-700 dark:text-gray-300 fa-solid fa-xmark"></i>
                                </button>
                            </div>

                            <div id="rateReminderContent" class="overflow-y-auto max-h-[55vh] p-6 space-y-3">
                                {{-- filled by JS --}}
                            </div>

                            <div class="px-6 pt-2 pb-6">
                                <button type="button" onclick="dismissRateReminder()"
                                    class="w-full py-3 rounded-xl text-sm font-semibold text-[#8B7355] dark:text-[#C4A97D] border border-[#8B7355] dark:border-[#C4A97D] hover:bg-[#F6EFE6] dark:hover:bg-gray-700 transition">
                                    Maybe Later
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endrole
        @endauth

        <!-- ================= HOW IT WORKS ================= -->
        <section class="bg-[#EFE3D6] dark:bg-gray-800 py-20">
            <div class="px-6 mx-auto max-w-7xl">
                <div class="text-center">
                    <div class="flex items-center justify-center gap-6">
                        <span
                            class="h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                        <h2 class="text-4xl font-['Playfair_Display'] text-[#3C2F23] dark:text-white font-semibold">
                            How It Works</h2>
                        <span
                            class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                    </div>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">Book in minutes with a simple flow.</p>
                </div>
                <div class="grid gap-6 mt-12 md:grid-cols-3">
                    <div
                        class="p-8 text-center transition shadow-sm bg-white/70 dark:bg-gray-900/40 rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-lg">
                        <div
                            class="flex items-center justify-center w-14 h-14 mx-auto rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                            <i class="fa-solid fa-location-dot text-2xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                        </div>
                        <h3 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">Find Your Spa</h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Browse verified spas near you.</p>
                    </div>
                    <div
                        class="p-8 text-center transition shadow-sm bg-white/70 dark:bg-gray-900/40 rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-lg">
                        <div
                            class="flex items-center justify-center w-14 h-14 mx-auto rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                            <i class="fa-solid fa-list-check text-2xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                        </div>
                        <h3 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">Choose Service</h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Select the service you want.</p>
                    </div>
                    <div
                        class="p-8 text-center transition shadow-sm bg-white/70 dark:bg-gray-900/40 rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-lg">
                        <div
                            class="flex items-center justify-center w-14 h-14 mx-auto rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                            <i class="fa-solid fa-spa text-2xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                        </div>
                        <h3 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">Relax & Enjoy</h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Arrive and unwind.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= WHY BOOK ================= -->
        <section class="py-20">
            <div class="px-6 mx-auto max-w-7xl">
                <div class="text-center">
                    <div class="flex items-center justify-center gap-6">
                        <span
                            class="h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                        <h2 class="text-4xl font-['Playfair_Display'] text-[#3C2F23] dark:text-white font-semibold">
                            Why Book With Us</h2>
                        <span
                            class="h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                    </div>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">Built for convenience and trust.</p>
                </div>
                <div class="grid gap-6 mt-12 md:grid-cols-4">
                    <div
                        class="transition bg-white shadow-sm dark:bg-gray-800 p-7 rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl">
                        <div
                            class="flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                            <i class="fa-solid fa-check text-xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                        </div>
                        <h4 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">Verified Spas</h4>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Only trusted listings appear on the
                            platform.</p>
                    </div>
                    <div
                        class="transition bg-white shadow-sm dark:bg-gray-800 p-7 rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl">
                        <div
                            class="flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                            <i class="fa-solid fa-calendar-check text-xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                        </div>
                        <h4 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">Easy Booking</h4>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Reserve quickly with clear
                            scheduling.</p>
                    </div>
                    <div
                        class="transition bg-white shadow-sm dark:bg-gray-800 p-7 rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl">
                        <div
                            class="flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                            <i class="fa-solid fa-user-nurse text-xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                        </div>
                        <h4 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">Expert Therapists</h4>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Quality care from professional
                            practitioners.</p>
                    </div>
                    <div
                        class="transition bg-white shadow-sm dark:bg-gray-800 p-7 rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-xl">
                        <div
                            class="flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                            <i class="fa-solid fa-lock text-xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                        </div>
                        <h4 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">Secure Payments</h4>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Safe checkout experience and
                            privacy-focused flow.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= CTA ================= -->
        {{-- Hidden for customers: listing a spa needs a business account
             (landing footer Phase 1, D7). Only guests and customers reach /. --}}
        @unlessrole('customer')
            <section class="py-16">
                <div class="px-6 mx-auto max-w-7xl">
                    <div
                        class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-[#6F5430] to-[#8B7355] p-10 md:p-14 text-center text-white shadow-2xl">
                        <div class="absolute inset-0 opacity-20"
                            style="background-image: radial-gradient(circle at 20% 20%, rgba(255,255,255,.35) 0, transparent 35%),
                                                radial-gradient(circle at 80% 30%, rgba(255,255,255,.25) 0, transparent 40%),
                                                radial-gradient(circle at 50% 90%, rgba(255,255,255,.18) 0, transparent 45%);">
                        </div>
                        <div class="relative">
                            <h2 class="text-3xl md:text-4xl font-['Playfair_Display'] font-semibold">
                                Own a Spa? List Your Business with Us!
                            </h2>
                            <p class="mt-3 text-sm text-white/90 md:text-base">
                                Reach more customers and manage bookings easily.
                            </p>
                            <a href="{{ route('register.business') }}"
                                class="inline-flex items-center justify-center gap-2 mt-7 bg-white text-[#6F5430] px-8 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transition active:translate-y-0.5">
                                <i class="fa-solid fa-arrow-right"></i>
                                Get Started
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        @endunlessrole
    </main>

    @include('partials.landing.footer')

    {{-- These stay inline because they use Blade/Laravel session syntax --}}
    @if (session('booking_error'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showSpaToast(@json(session('booking_error')), 'error');
            });
        </script>
    @endif

    @if (session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showSpaToast(@json(session('success')), 'success');
            });
        </script>
    @endif

    @if (($errors->hasBag('customerProfile') && $errors->getBag('customerProfile')->any())
            || ($errors->hasBag('photo') && $errors->getBag('photo')->any())
            || session('open_profile_modal'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                openProfileModal();
            });
        </script>
    @endif

    @if (session('error'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showSpaToast(@json(session('error')), 'error');
            });
        </script>
    @endif

</body>

</html>
