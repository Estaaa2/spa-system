const btn = document.getElementById('mobile-menu-button');
const menu = document.getElementById('mobile-menu');
btn?.addEventListener('click', () => menu.classList.toggle('hidden'));

const nav = document.getElementById('topNav');
window.addEventListener('scroll', () => {
    if (window.scrollY > 10) nav?.classList.add('nav-scrolled');
    else nav?.classList.remove('nav-scrolled');
});

let selectedSpa = null;
let spaMap      = null;

// ── Customer profile: shared Leaflet marker + last-saved snapshot ──────────
// (`marker` used to only be declared with `let` inside the map-init
// DOMContentLoaded callback further down, so openProfileModal() reading it
// from a different function threw "marker is not defined" the first time a
// customer with an already-saved pin opened their profile.)
let profileMapMarker     = null;
let profileSavedLocation = { address: '', lat: null, lng: null };

// Cavite bounding box — plain numbers here (Leaflet's L.latLngBounds is only
// constructed later, inside the map-init block, so this doesn't depend on
// Leaflet having parsed yet). Must match ProfileController's server-side
// CAVITE_LAT_MIN/MAX etc., and the box used on the branch-profile map, so
// client and server always agree on what counts as "in Cavite."
const PROFILE_CAVITE_LAT_MIN = 14.020;
const PROFILE_CAVITE_LAT_MAX = 14.520;
const PROFILE_CAVITE_LNG_MIN = 120.620;
const PROFILE_CAVITE_LNG_MAX = 121.100;
const PROFILE_CAVITE_VIEWBOX = `${PROFILE_CAVITE_LNG_MIN},${PROFILE_CAVITE_LAT_MAX},${PROFILE_CAVITE_LNG_MAX},${PROFILE_CAVITE_LAT_MIN}`; // left,top,right,bottom — for Nominatim

const profileDropdownBtn  = document.getElementById('profileDropdownBtn');
const profileDropdownMenu = document.getElementById('profileDropdownMenu');
const profileChevron      = document.getElementById('profileChevron');

// =====================================================
// FIX: Use local date instead of UTC
// =====================================================
function getTodayLocal() {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

// Combines date_raw + end_time (falls back to start_time) into a real
// Date and checks if that moment has already passed. Date-only comparisons
// (b.date_raw >= today) can't tell 7:06 PM apart from 9:00 AM on the same day.
function isBookingPastNow(b) {
    if (!b.date_raw) return false;
    const timePart = (b.end_time || b.start_time || '23:59:59').slice(0, 8);
    const appointmentEnd = new Date(`${b.date_raw}T${timePart}`);
    return appointmentEnd.getTime() <= Date.now();
}

// =====================================================
// PROFILE DROPDOWN
// =====================================================

function closeProfileDropdown() {
    profileDropdownMenu?.classList.add('hidden');
    profileChevron?.classList.remove('rotate-180');
}

profileDropdownBtn?.addEventListener('click', function (e) {
    e.stopPropagation();
    const isHidden = profileDropdownMenu.classList.contains('hidden');
    if (isHidden) {
        profileDropdownMenu.classList.remove('hidden');
        profileChevron?.classList.add('rotate-180');
    } else {
        closeProfileDropdown();
    }
});

document.addEventListener('click', function (e) {
    const wrapper = document.getElementById('profileDropdownWrapper');
    if (wrapper && !wrapper.contains(e.target)) closeProfileDropdown();
});

function openProfileModal() {
    const profileModalEl = document.getElementById('profileModal');
    if (!profileModalEl) {
        console.warn('openProfileModal: #profileModal is not in the DOM (guest session?) — skipping.');
        return;
    }

    profileModalEl.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');

    // Snapshot the DB-saved location now, before anything on the map or in
    // the autocomplete can touch the inputs — this is what "Reset to saved
    // location" restores, regardless of what happens while the modal is open.
    const addressField = document.getElementById('address');
    const latInput      = document.getElementById('latitude');
    const lngInput       = document.getElementById('longitude');
    profileSavedLocation = {
        address: addressField ? addressField.value : '',
        lat: latInput && latInput.value ? parseFloat(latInput.value) : null,
        lng: lngInput && lngInput.value ? parseFloat(lngInput.value) : null,
    };

    const suggestions = document.getElementById('addressSuggestions');
    if (suggestions) suggestions.classList.add('hidden');

    setTimeout(() => {
        if (window.profileMap) {
            window.profileMap.invalidateSize();
        }

        // If user already has a pinned location, show it
        if (!latInput || !lngInput) return;

        const savedLat = profileSavedLocation.lat;
        const savedLng = profileSavedLocation.lng;
        if (savedLat && savedLng && window.profileMap) {
            window.profileMap.setView([savedLat, savedLng], 15);
            if (profileMapMarker) window.profileMap.removeLayer(profileMapMarker);
            profileMapMarker = L.marker([savedLat, savedLng], { draggable: true }).addTo(window.profileMap);
            profileMapMarker.on('dragend', () => updateProfilePin(profileMapMarker.getLatLng()));
        }
    }, 300);
}

function closeProfileModal() {
    const profileModalEl = document.getElementById('profileModal');
    if (!profileModalEl) return;

    profileModalEl.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    const btn     = document.getElementById('emailToggleBtn');
    const display = document.getElementById('emailDisplay');
    const icon    = document.getElementById('emailToggleIcon');
    if (btn && display && icon) {
        display.textContent = btn.dataset.masked;
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function openLogoutModal() {
    document.getElementById('logoutModal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeLogoutModal() {
    document.getElementById('logoutModal').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

window.openLogoutModal  = openLogoutModal;
window.closeLogoutModal = closeLogoutModal;

function toggleEmail() {
    const display = document.getElementById('emailDisplay');
    const btn     = document.getElementById('emailToggleBtn');
    const icon    = document.getElementById('emailToggleIcon');
    if (!display || !btn || !icon) return;
    const isHidden = icon.classList.contains('fa-eye');
    if (isHidden) {
        display.textContent = btn.dataset.real;
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        display.textContent = btn.dataset.masked;
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// =====================================================
// SPA MODAL
// =====================================================
const spaModal     = document.getElementById('spaModal');
const closeSpaBtns = document.querySelectorAll('[data-close-spa-modal]');
let photos     = [];
let photoIndex = 0;

function openSpaModal(spaData) {
    selectedSpa = spaData;

    document.getElementById('spaModalName').textContent    = spaData.name    ?? 'Spa';
    document.getElementById('spaModalTag').textContent     = spaData.tag     ?? 'Featured Spa';
    document.getElementById('spaModalDesc').textContent    = spaData.desc    ?? '';
    document.getElementById('spaModalPhone').textContent   = spaData.phone   ?? 'No contact info';
    document.getElementById('spaModalAddress').textContent = spaData.address ?? 'Address unavailable';
    document.getElementById('spaModalPrice').textContent   = spaData.price_note
    ? (spaData.has_promo ? `Starts at ₱${spaData.price_note} — ${spaData.promo_label} running` : `Starts at ₱${spaData.price_note}`)
    : 'Prices vary per treatment';
    const hiringBlock = document.getElementById('spaModalHiring');
    const hiringNote   = document.getElementById('spaModalHiringNote');
    const ratingBlock = document.getElementById('spaModalRating');
    const ratingValue = document.getElementById('spaModalRatingValue');
    const ratingCount = document.getElementById('spaModalRatingCount');
    if (ratingBlock && ratingValue && ratingCount) {
        if (spaData.rating_avg) {
            ratingValue.textContent = spaData.rating_avg;
            ratingCount.textContent = `(${spaData.rating_count})`;
            ratingBlock.classList.remove('hidden');
            ratingBlock.classList.add('flex');
        } else {
            ratingBlock.classList.add('hidden');
            ratingBlock.classList.remove('flex');
        }
    }
    if (hiringBlock) {
        if (spaData.is_hiring) {
            hiringBlock.classList.remove('hidden');
            if (hiringNote) hiringNote.textContent = spaData.hiring_note || 'This branch is currently accepting applications.';
        } else {
            hiringBlock.classList.add('hidden');
        }
    }

    function getAddressSummary(fullAddress) {
        if (!fullAddress) return 'Location unavailable';
        const parts = fullAddress.split(',').map(p => p.trim());
        if (parts.length < 3) return fullAddress;
        const withoutZipCountry = parts.slice(0, parts.length - 2);
        return withoutZipCountry.slice(-3).join(', ');
    }
    document.getElementById('spaModalAddressSummary').textContent = getAddressSummary(spaData.address);

    const amenitiesContainer = document.getElementById('spaModalAmenities');
    if (amenitiesContainer) {
        const amenities = spaData.amenities ?? [];
        if (amenities.length) {
            amenitiesContainer.innerHTML = `
                <div class="grid grid-cols-2 gap-2">
                    ${amenities.map(a => {
                        const label = a.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                        return `
                            <div class="amenity-chip">
                                <div class="amenity-chip-icon">
                                    <i class="fa-solid fa-spa"></i>
                                </div>
                                <span class="amenity-chip-label">${label}</span>
                            </div>`;
                    }).join('')}
                </div>`;
        } else {
            amenitiesContainer.innerHTML = `<p class="text-sm italic text-gray-400 dark:text-gray-500">No amenities listed yet.</p>`;
        }
    }

    const fallbackImage = document.body.dataset.fallbackImage ?? '';
    photos = Array.isArray(spaData.photos) && spaData.photos.length
        ? spaData.photos
        : [fallbackImage, fallbackImage, fallbackImage, fallbackImage, fallbackImage];

    const elMainPhoto = document.getElementById('spaModalMainPhoto');
    if (elMainPhoto) elMainPhoto.src = photos[0] || fallbackImage;

    ['gallery_1', 'gallery_2', 'gallery_3', 'gallery_4'].forEach((id, i) => {
        const el = document.getElementById(id);
        if (el) el.src = photos[i + 1] || fallbackImage;
    });

    const galleryCount = document.getElementById('spaModalGalleryCount');
    if (galleryCount) galleryCount.classList.add('hidden');

    spaModal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');

    const elMap = document.getElementById('spaModalMap');
    if (spaMap) { spaMap.remove(); spaMap = null; }
    if (elMap && spaData.lat && spaData.lng) {
        setTimeout(() => {
            spaMap = L.map(elMap).setView([spaData.lat, spaData.lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19, attribution: '&copy; OpenStreetMap'
            }).addTo(spaMap);
            L.marker([spaData.lat, spaData.lng])
                .addTo(spaMap).bindPopup(spaData.name).openPopup();
            spaMap.invalidateSize();
        }, 300);
    }
    photoIndex = 0;
}

// =====================================================
// REVIEWS MODAL (Shopee-style star filter)
// =====================================================
let reviewsData          = { reviews: [], counts: {} };
let currentReviewFilter  = null; // null = All

function openReviewsModalFromSpa() {
    if (!selectedSpa) return;
    openReviewsModal(selectedSpa.id, selectedSpa.branch_id, selectedSpa.name);
}

function openReviewsModal(spaId, branchId, spaName) {
    if (!spaId || !branchId) return;

    const modal   = document.getElementById('reviewsModal');
    const nameEl  = document.getElementById('reviewsModalSpaName');
    const tabsEl  = document.getElementById('reviewFilterTabs');
    const listEl  = document.getElementById('reviewsModalList');

    if (nameEl) nameEl.textContent = spaName ?? '';
    if (tabsEl) tabsEl.innerHTML = `<p class="text-sm text-gray-400">Loading...</p>`;
    if (listEl) listEl.innerHTML = `<p class="text-sm italic text-gray-400 dark:text-gray-500">Loading reviews...</p>`;

    currentReviewFilter = null;

    if (modal) {
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    fetch(`/web-api/spas/${spaId}/${branchId}/reviews`)
        .then(res => res.json())
        .then(data => {
            reviewsData = {
                reviews: Array.isArray(data.reviews) ? data.reviews : [],
                counts:  data.counts ?? {},
                total:   data.total ?? 0,
            };
            renderReviewFilterTabs();
            renderReviewsList();
        })
        .catch(err => {
            console.warn('Failed to load reviews:', err);
            if (tabsEl) tabsEl.innerHTML = '';
            if (listEl) listEl.innerHTML = `<p class="text-sm italic text-gray-400 dark:text-gray-500">Unable to load reviews.</p>`;
        });
}

function closeReviewsModal() {
    const modal = document.getElementById('reviewsModal');
    if (modal) modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

function renderReviewFilterTabs() {
    const tabsEl = document.getElementById('reviewFilterTabs');
    if (!tabsEl) return;

    const total = reviewsData.total ?? reviewsData.reviews.length;

    const tabs = [
        { label: `All (${total})`, value: null },
        ...[5, 4, 3, 2, 1].map(star => ({
            label: `${star} ★ (${reviewsData.counts[star] ?? 0})`,
            value: star,
        })),
    ];

    tabsEl.innerHTML = tabs.map(t => `
        <button type="button"
            data-filter="${t.value ?? 'all'}"
            onclick="selectReviewFilter(${t.value ?? 'null'})"
            class="review-filter-tab flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold border transition
                ${currentReviewFilter === t.value
                    ? 'bg-[#8B7355] text-white border-[#8B7355]'
                    : 'bg-transparent text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:border-[#8B7355]'}">
            ${t.label}
        </button>`).join('');
}

function selectReviewFilter(star) {
    currentReviewFilter = star;
    renderReviewFilterTabs();
    renderReviewsList();
}

function renderReviewsList() {
    const listEl = document.getElementById('reviewsModalList');
    if (!listEl) return;

    const filtered = currentReviewFilter === null
        ? reviewsData.reviews
        : reviewsData.reviews.filter(r => r.rating === currentReviewFilter);

    if (!filtered.length) {
        listEl.innerHTML = `<p class="text-sm italic text-gray-400 dark:text-gray-500">No reviews${currentReviewFilter ? ` with ${currentReviewFilter} star${currentReviewFilter > 1 ? 's' : ''}` : ''} yet.</p>`;
        return;
    }

    listEl.innerHTML = filtered.map(r => `
        <div class="p-3 rounded-xl bg-[#F6EFE6]/50 dark:bg-gray-700/40 ring-1 ring-black/5 dark:ring-white/10">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-[#3C2F23] dark:text-white">${escapeHtml(r.name || 'Anonymous')}</span>
                <span class="text-xs text-gray-400">${r.date ?? ''}</span>
            </div>
            <div class="flex items-center gap-0.5 mt-1">${renderStars(r.rating)}</div>
            ${r.comment ? `<p class="mt-2 text-sm text-gray-600 dark:text-gray-400">${escapeHtml(r.comment)}</p>` : ''}
        </div>`).join('');
}

function closeSpaModal() {
    spaModal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

document.querySelectorAll('[data-open-spa-modal]').forEach(btn => {
    btn.addEventListener('click', () => {
        try {
            const data = JSON.parse(btn.getAttribute('data-spa'));
            openSpaModal(data);
        } catch (e) {
            console.error('Invalid spa data', e);
        }
    });
});

closeSpaBtns.forEach(btn => btn.addEventListener('click', closeSpaModal));

// =====================================================
// SEGMENTED SEARCH (Place + Treatment)
// =====================================================
const CAVITE_PLACES = ["Bacoor","Cavite City","Dasmariñas","General Trias","Imus","Tagaytay","Trece Martires",
    "Alfonso","Amadeo","Carmona","Gen. Emilio Aguinaldo","Gen. Mariano Alvarez","Indang","Kawit",
    "Magallanes","Maragondon","Mendez","Naic","Noveleta","Rosario","Silang","Tanza","Ternate"];

const TREATMENT_SUGGESTIONS = (function () {
    try {
        return JSON.parse(document.getElementById('treatmentSuggestionsData')?.textContent || '[]');
    } catch (e) {
        return [];
    }
}());

const placeInput      = document.getElementById('placeInput');
const placeSegment    = document.getElementById('placeSegment');
const placeDropdown   = document.getElementById('placeDropdown');
const placeChips      = document.getElementById('placeChips');

const treatmentInput      = document.getElementById('treatmentInput');
const treatmentSegment    = document.getElementById('treatmentSegment');
const treatmentDropdown   = document.getElementById('treatmentDropdown');
const treatmentSuggestBox = document.getElementById('treatmentSuggestionList');

const spaSearchForm         = document.getElementById('spaSearchForm');
const browseSections        = document.getElementById('browseSections');
const unifiedResultsSection = document.getElementById('unifiedResultsSection');
const unifiedResultsGrid    = document.getElementById('unifiedResultsGrid');
const unifiedResultsSubtitle = document.getElementById('unifiedResultsSubtitle');

function closeSearchDropdowns() {
    placeSegment?.classList.remove('active');
    treatmentSegment?.classList.remove('active');
    placeDropdown?.classList.remove('open');
    treatmentDropdown?.classList.remove('open');
}

function positionSearchDropdown(segmentEl, dropdownEl) {
    if (!segmentEl || !dropdownEl) return;
    const rect          = segmentEl.getBoundingClientRect();
    const gap            = 12;
    const viewportMargin = 16;
    const dropdownWidth  = Math.min(320, window.innerWidth - (viewportMargin * 2));
    const maxLeft         = window.innerWidth - dropdownWidth - viewportMargin;
    const left            = Math.max(viewportMargin, Math.min(rect.left, maxLeft));

    dropdownEl.style.top  = `${rect.bottom + gap}px`;
    dropdownEl.style.left = `${left}px`;
}

window.addEventListener('resize', () => {
    if (placeSegment?.classList.contains('active')) positionSearchDropdown(placeSegment, placeDropdown);
    if (treatmentSegment?.classList.contains('active')) positionSearchDropdown(treatmentSegment, treatmentDropdown);
});
window.addEventListener('scroll', () => {
    if (placeSegment?.classList.contains('active') || treatmentSegment?.classList.contains('active')) {
        closeSearchDropdowns();
    }
}, { passive: true });

function renderPlaceChips(filter) {
    if (!placeChips) return;
    const f = (filter || '').toLowerCase();
    const matches = CAVITE_PLACES.filter(p => p.toLowerCase().includes(f));
    placeChips.innerHTML = matches.length
        ? matches.map(p => `<div class="search-chip" data-value="${escapeHtml(p)}">${escapeHtml(p)}</div>`).join('')
        : `<div class="search-empty-note">No matching city — your text still searches location directly.</div>`;
    placeChips.querySelectorAll('.search-chip').forEach(el => {
        el.addEventListener('click', () => { placeInput.value = el.dataset.value; closeSearchDropdowns(); });
    });
}

function renderTreatmentSuggestions(filter) {
    if (!treatmentSuggestBox) return;
    const f = (filter || '').toLowerCase();
    const matches = TREATMENT_SUGGESTIONS.filter(t => t.toLowerCase().includes(f)).slice(0, 20);
    treatmentSuggestBox.innerHTML = matches.length
        ? matches.map(t => `<div class="search-suggestion-row" data-value="${escapeHtml(t)}">${escapeHtml(t)}</div>`).join('')
        : `<div class="search-empty-note">No suggestions match — your text still searches treatments directly.</div>`;
    treatmentSuggestBox.querySelectorAll('.search-suggestion-row').forEach(el => {
        el.addEventListener('click', () => { treatmentInput.value = el.dataset.value; closeSearchDropdowns(); });
    });
}

if (placeInput) {
    placeInput.addEventListener('focus', () => {
        closeSearchDropdowns();
        placeSegment?.classList.add('active');
        placeDropdown?.classList.add('open');
        positionSearchDropdown(placeSegment, placeDropdown);
        renderPlaceChips(placeInput.value);
    });
    placeInput.addEventListener('input', () => renderPlaceChips(placeInput.value));
}

if (treatmentInput) {
    treatmentInput.addEventListener('focus', () => {
        closeSearchDropdowns();
        treatmentSegment?.classList.add('active');
        treatmentDropdown?.classList.add('open');
        positionSearchDropdown(treatmentSegment, treatmentDropdown);
        renderTreatmentSuggestions(treatmentInput.value);
    });
    treatmentInput.addEventListener('input', () => renderTreatmentSuggestions(treatmentInput.value));
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('#placeSegment') && !e.target.closest('#treatmentSegment')) {
        closeSearchDropdowns();
    }
});

// ── Running the search / toggling browse vs. results in place ──────────────

function spaAddressSummary(address) {
    const cleaned = (address || '').replace(/,?\s*(Philippines|Calabarzon|\d{4})\s*/gi, '');
    const parts   = cleaned.split(',').map(p => p.trim()).filter(Boolean);
    return parts.length >= 2 ? parts.slice(-2).join(', ') : (parts.join(', ') || 'Location unavailable');
}

function spaHiringBadge(spa) {
    if (!spa.is_hiring) return '';
    const safeName = (spa.name ?? '').replace(/'/g, "\\'");
    return `
        <span onclick="event.stopPropagation(); openApplicationModal(${spa.id}, ${spa.branch_id}, '${safeName}')"
            class="absolute top-3 right-3 flex items-center gap-1 px-2.5 py-1 rounded-full bg-red-500 hover:bg-red-700 text-white text-[11px] font-semibold backdrop-blur-sm transition cursor-pointer">
            <i class="fa-solid fa-briefcase text-[10px]"></i>
            We're Hiring · <span class="underline underline-offset-2">Apply Now</span>
        </span>`;
}

function buildUnifiedCard(spa) {
    const thumb = spa.photos?.[0] || (document.body.dataset.fallbackImage ?? '');
    const addr  = spaAddressSummary(spa.address);
    const escaped = JSON.stringify(spa).replace(/'/g, '&#39;');
    const badgeClass = spa.is_featured ? 'bg-[#6F5430]/90 text-white' : 'bg-white/80 dark:bg-gray-900/70 text-[#6F5430] dark:text-[#C4A97D] ring-1 ring-black/5 dark:ring-white/10';
    const badgeIcon  = spa.is_featured ? 'fa-star text-[#F5C842]' : 'fa-spa text-[#8B7355] dark:text-[#C4A97D]';
    const badgeText  = spa.is_featured ? 'Featured' : 'Verified';

    return `
        <button type="button"
            class="w-full overflow-hidden text-left transition bg-white dark:bg-gray-800 shadow-sm group rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-2xl"
            data-open-spa-modal
            data-spa='${escaped}'>
            <div class="relative overflow-hidden">
                <img src="${thumb}" class="h-56 w-full object-cover transition duration-500 group-hover:scale-[1.04]" alt="${escapeHtml(spa.name)}">
                <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-black/0 to-transparent"></div>
                <div class="absolute top-3 left-3 flex items-center gap-1 px-2.5 py-1 rounded-full ${badgeClass} text-[11px] font-semibold backdrop-blur-sm">
                    <i class="fa-solid ${badgeIcon} text-[10px]"></i>
                    ${badgeText}
                </div>
                ${spaHiringBadge(spa)}
            </div>
            <div class="p-5">
                <h3 class="text-[15px] font-semibold text-[#3C2F23] dark:text-white leading-tight">${escapeHtml(spa.name)}</h3>
                ${spa.rating_avg ? `
                <div class="flex items-center gap-1 mt-1">
                    <i class="fa-solid fa-star text-[#D2A85B] text-xs"></i>
                    <span class="text-xs font-semibold text-[#3C2F23] dark:text-white">${spa.rating_avg}</span>
                    <span class="text-xs text-gray-400">(${spa.rating_count})</span>
                </div>` : ''}
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">${escapeHtml(addr)}</p>
                ${spa.price_note ? `<p class="mt-2 text-xs font-medium text-[#8B7355] dark:text-[#C4A97D]">Starts at ₱${spa.price_note}</p>` : ''}
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400 line-clamp-2">${escapeHtml(spa.desc) || 'No description yet.'}</p>
            </div>
        </button>`;
}

function attachSpaModalHandlersTo(container) {
    container?.querySelectorAll('[data-open-spa-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            try {
                openSpaModal(JSON.parse(btn.getAttribute('data-spa')));
            } catch (e) {
                console.error('Invalid spa data', e);
            }
        });
    });
}

function showBrowseSections() {
    browseSections?.classList.remove('hidden');
    unifiedResultsSection?.classList.add('hidden');
}

function showUnifiedResults(data) {
    browseSections?.classList.add('hidden');
    unifiedResultsSection?.classList.remove('hidden');

    if (unifiedResultsSubtitle) {
        const parts = [];
        if (data.place) parts.push(`in "<span class="font-semibold text-[#8B7355] dark:text-[#C4A97D]">${escapeHtml(data.place)}</span>"`);
        if (data.treatment) parts.push(`for "<span class="font-semibold text-[#8B7355] dark:text-[#C4A97D]">${escapeHtml(data.treatment)}</span>"`);
        const lead = parts.length ? `Showing results ${parts.join(' ')}` : 'Showing all spas';
        unifiedResultsSubtitle.innerHTML = `${lead} <button type="button" onclick="clearSpaSearch()" class="ml-2 text-[#8B7355] dark:text-[#C4A97D] underline underline-offset-2">Clear search</button>`;
    }

    if (!unifiedResultsGrid) return;

    if (!data.results.length) {
        unifiedResultsGrid.innerHTML = `
            <div class="py-16 text-center col-span-full">
                <div class="flex items-center justify-center w-14 h-14 mx-auto mb-4 rounded-2xl bg-[#F6EFE6] dark:bg-gray-800 ring-1 ring-black/5 dark:ring-white/10">
                    <i class="fa-solid fa-magnifying-glass text-xl text-[#8B7355] dark:text-[#C4A97D]"></i>
                </div>
                <p class="font-semibold text-[#3C2F23] dark:text-white">No spas found</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Try a different place or treatment, or
                    <button type="button" onclick="clearSpaSearch()" class="text-[#8B7355] dark:text-[#C4A97D] underline underline-offset-2">browse all spas</button>.
                </p>
            </div>`;
        return;
    }

    unifiedResultsGrid.innerHTML = data.results.map(buildUnifiedCard).join('');
    attachSpaModalHandlersTo(unifiedResultsGrid);
}

function updateSearchUrl(place, treatment) {
    const url = new URL(window.location.href);
    place ? url.searchParams.set('place', place) : url.searchParams.delete('place');
    treatment ? url.searchParams.set('treatment', treatment) : url.searchParams.delete('treatment');
    url.searchParams.delete('search');
    url.searchParams.delete('city');
    window.history.replaceState(null, '', url.toString());
}

async function runSpaSearch(place, treatment) {
    try {
        const params = new URLSearchParams();
        if (place) params.set('place', place);
        if (treatment) params.set('treatment', treatment);

        const res = await fetch(`/web-api/spas/search?${params.toString()}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();

        showUnifiedResults(data);
        updateSearchUrl(place, treatment);
    } catch (err) {
        console.error('Search failed:', err);
        showSpaToast('Search failed. Please try again.', 'error');
    }
}

// Exposed on window - referenced from onclick="" in dynamically-injected HTML
// (the "Clear search" / "Browse all spas" controls above), which always runs
// in global scope. Going back to browsing needs no network call: the browse
// sections were only hidden, never removed, so restoring them is instant.
window.clearSpaSearch = function () {
    if (placeInput) placeInput.value = '';
    if (treatmentInput) treatmentInput.value = '';
    closeSearchDropdowns();
    showBrowseSections();
    updateSearchUrl('', '');
};

spaSearchForm?.addEventListener('submit', function (e) {
    e.preventDefault();
    closeSearchDropdowns();
    const place = placeInput?.value.trim() ?? '';
    const treatment = treatmentInput?.value.trim() ?? '';

    if (!place && !treatment) {
        showBrowseSections();
        updateSearchUrl('', '');
        return;
    }

    runSpaSearch(place, treatment);
});

// =====================================================
// BOOKING MODAL
// =====================================================
const bookingModal         = document.getElementById('bookingModal');
const openBookingBtn       = document.getElementById('openBookingModalBtn');
const closeBookingBtns     = document.querySelectorAll('[data-close-booking-modal]');
const bookingSpaMeta       = document.getElementById('bookingSpaMeta');
const bookingSpaIdInput    = document.getElementById('bookingSpaIdInput');
const bookingBranchIdInput = document.getElementById('bookingBranchIdInput');
const serviceTypeSelect    = document.getElementById('bookingServiceType');
const serviceTypeHint      = document.getElementById('bookingServiceTypeHint');
const serviceListContainer = document.getElementById('bookingServiceList');
const bookingDateInput     = document.getElementById('bookingDateInput');
const bookingTimeInput     = document.getElementById('bookingTimeInput');
const addressWrapper       = document.getElementById('addressWrapper');
const addressInput         = document.getElementById('bookingAddressInput');
const bookingCustomerPhone = document.getElementById('bookingCustomerPhone');
const bookingForm          = document.querySelector('#bookingModal form');

const TOTAL_BOOKING_STEPS = 3;
let currentBookingStep    = 1;

function openTermsModal() {
    document.getElementById('termsModal').classList.remove('hidden');
}

function closeTermsModal() {
    document.getElementById('termsModal').classList.add('hidden');
}

// ── Step navigation ─────────────────────────────────────────────────────────
function showBookingStep(step) {
    currentBookingStep = step;

    document.querySelectorAll('[data-booking-step]').forEach(panel => {
        panel.classList.toggle('hidden', parseInt(panel.dataset.bookingStep, 10) !== step);
    });

    for (let i = 1; i <= TOTAL_BOOKING_STEPS; i++) {
        const circle = document.querySelector(`[data-step-circle="${i}"]`);
        const label  = document.querySelector(`[data-step-label="${i}"]`);
        const bar    = document.querySelector(`[data-step-bar="${i}"]`);

        if (circle) {
            if (i < step) {
                circle.className = 'step-circle is-done';
                circle.innerHTML = '<i class="fa-solid fa-check text-[10px]"></i>';
            } else if (i === step) {
                circle.className = 'step-circle is-active';
                circle.textContent = i;
            } else {
                circle.className = 'step-circle is-pending';
                circle.textContent = i;
            }
        }
        if (label) label.className = `step-label ${i <= step ? 'is-active' : 'is-pending'}`;
        if (bar)   bar.className   = `step-bar ${i < step ? 'is-done' : 'is-pending'}`;
    }

    const backBtn   = document.getElementById('bookingBackBtn');
    const nextBtn   = document.getElementById('bookingNextBtn');
    const submitBtn = document.getElementById('bookingSubmitBtn');
    if (backBtn)   backBtn.classList.toggle('hidden', step === 1);
    if (nextBtn)   nextBtn.classList.toggle('hidden', step === TOTAL_BOOKING_STEPS);
    if (submitBtn) submitBtn.classList.toggle('hidden', step !== TOTAL_BOOKING_STEPS);

    if (step === 3) buildBookingRecap();

    document.querySelector('#bookingModal .overflow-y-auto')?.scrollTo({ top: 0, behavior: 'smooth' });
}

function validateBookingStep(step) {
    if (step === 1) {
        const treatmentError = document.getElementById('bookingTreatmentError');
        if (!getCheckedTreatmentInput()) {
            treatmentError?.classList.remove('hidden');
            serviceListContainer?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        }
        treatmentError?.classList.add('hidden');

        if (!serviceTypeSelect?.value) {
            showSpaToast('Please select a service type.', 'error');
            return false;
        }

        if (serviceTypeSelect.value === 'in_home' && !addressInput?.value.trim()) {
            showSpaToast('Please enter your home address.', 'error');
            addressInput?.focus();
            return false;
        }

        return true;
    }

    if (step === 2) {
        const timeError = document.getElementById('bookingTimeError');
        if (!bookingDateInput?.value) {
            showSpaToast('Please select an appointment date.', 'error');
            return false;
        }
        if (!bookingTimeInput?.value) {
            timeError?.classList.remove('hidden');
            return false;
        }
        timeError?.classList.add('hidden');
        return true;
    }

    return true;
}

// ── Step 1: service cards ───────────────────────────────────────────────────
let allServiceItems      = [];
let currentServiceFilter = 'all';

function getCheckedTreatmentInput() {
    return serviceListContainer?.querySelector('input[name="treatment"]:checked') || null;
}

function formatPeso(amount) {
    return `₱${parseFloat(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

// Each card is a <label> wrapping a visually-hidden (sr-only) radio input, so
// the group keeps native keyboard navigation and label-click behavior. All
// visual states (base look, hover, selected, focus) come from the .svc-card
// rules in the <style> block above, not Tailwind utility classes — this
// content is built entirely at runtime, so it can't rely on Tailwind having
// scanned it at build time. See the note in the <style> block for why.
function buildServiceCard(item, kind) {
    const value = `${kind}_${item.id}`;

    const label = document.createElement('label');
    label.className = 'svc-card';
    label.dataset.cardFor = value;
    label.dataset.kind = kind;
    label.dataset.searchText = (item.name ?? '').toString().toLowerCase();

    // ── Resolve active promo (if any) and compute the discounted price ──
    const activePromo = Array.isArray(item.promos) && item.promos.length ? item.promos[0] : null;
    const basePrice = (item.price !== null && item.price !== undefined) ? parseFloat(item.price) : null;
    let finalPrice = basePrice;
    let discountPct = null;

    if (activePromo && basePrice !== null) {
        if (activePromo.discount_type === 'percent') {
            discountPct = parseFloat(activePromo.discount_value);
            finalPrice = basePrice * (1 - discountPct / 100);
        } else {
            finalPrice = basePrice - parseFloat(activePromo.discount_value);
            discountPct = basePrice > 0 ? Math.round((1 - finalPrice / basePrice) * 100) : 0;
        }
        finalPrice = Math.max(0, Math.round(finalPrice * 100) / 100);
    }

    const input = document.createElement('input');
    input.type = 'radio';
    input.name = 'treatment';
    input.value = value;
    input.className = 'sr-only';
    input.dataset.serviceType = item.service_type ?? 'in_branch_only';
    input.dataset.itemType    = kind;
    input.dataset.price       = finalPrice !== null ? finalPrice : '';       // discounted price drives recap + downpayment
    input.dataset.basePrice   = basePrice !== null ? basePrice : '';         // original price, for the recap breakdown
    input.dataset.promoName  = activePromo ? activePromo.name : '';
    input.dataset.promoPct   = discountPct !== null ? discountPct : '';
    input.dataset.name        = item.name ?? '';

    // Thumbnail
    const fallbackImage = document.body.dataset.fallbackImage ?? '';
    const thumbWrap = document.createElement('div');
    thumbWrap.className = 'svc-card-thumb';
    const thumbImg = document.createElement('img');
    thumbImg.src = item.image_url || fallbackImage;
    thumbImg.alt = item.name ?? '';
    thumbImg.loading = 'lazy';
    thumbImg.onerror = function () { this.onerror = null; this.src = fallbackImage; };
    thumbWrap.appendChild(thumbImg);

    // Text content wrapper
    const content = document.createElement('div');
    content.className = 'svc-card-content';

    const topRow = document.createElement('div');
    topRow.className = 'svc-card-top';

    const nameEl = document.createElement('span');
    nameEl.className = 'svc-card-name';
    nameEl.textContent = item.name ?? '';

    if (kind === 'package') {
        const badge = document.createElement('span');
        badge.className = 'svc-card-badge';
        badge.textContent = 'Package';
        nameEl.appendChild(badge);
    }

    if (activePromo) {
        const promoBadge = document.createElement('span');
        promoBadge.className = 'svc-card-badge';
        promoBadge.style.color = '#dc2626';
        promoBadge.style.background = '#fef2f2';
        promoBadge.style.borderColor = 'rgba(220,38,38,0.15)';
        promoBadge.textContent = discountPct !== null ? `${discountPct}% OFF` : activePromo.name;
        promoBadge.title = activePromo.name;
        nameEl.appendChild(promoBadge);
    }

    const priceEl = document.createElement('span');
    priceEl.className = 'svc-card-price';
    if (basePrice === null) {
        priceEl.textContent = 'Price varies';
    } else if (activePromo) {
        priceEl.innerHTML = `
            <span style="display:flex;flex-direction:column;align-items:flex-end;line-height:1.2;">
                <span style="text-decoration:line-through;color:#9ca3af;font-weight:400;font-size:11px;">${formatPeso(basePrice)}</span>
                <span style="color:#dc2626;">${formatPeso(finalPrice)}</span>
            </span>`;
    } else {
        priceEl.textContent = formatPeso(basePrice);
    }

    topRow.appendChild(nameEl);
    topRow.appendChild(priceEl);

    const descEl = document.createElement('p');
    descEl.className = 'svc-card-desc';
    const desc = (item.description ?? '').toString().trim();
    descEl.textContent = desc.length ? desc : 'No description yet.';

    content.appendChild(topRow);
    content.appendChild(descEl);

    if (activePromo) {
        const promoNote = document.createElement('div');
        promoNote.className = 'svc-card-meta';
        promoNote.style.color = '#dc2626';
        promoNote.innerHTML = `<i class="fa-solid fa-tag"></i> ${escapeHtml(activePromo.name)} — save ${formatPeso(basePrice - finalPrice)}`;
        content.appendChild(promoNote);
    }

    const duration = kind === 'package' ? (item.total_duration ?? item.duration) : item.duration;
    if (duration) {
        const metaRow = document.createElement('div');
        metaRow.className = 'svc-card-meta';
        metaRow.innerHTML = `<i class="fa-regular fa-clock"></i> ${parseInt(duration, 10)} min`;
        content.appendChild(metaRow);
    }

    label.appendChild(input);
    label.appendChild(thumbWrap);
    label.appendChild(content);

    return label;
}

function buildSectionHeader(labelText) {
    const header = document.createElement('p');
    header.className = 'svc-section-header';
    header.textContent = labelText;
    header.dataset.sectionHeader = 'true';
    return header;
}

function highlightSelectedCard() {
    serviceListContainer?.querySelectorAll('.svc-card').forEach(card => card.classList.remove('is-selected'));
    const checked = getCheckedTreatmentInput();
    const activeCard = checked && serviceListContainer?.querySelector(`.svc-card[data-card-for="${checked.value}"]`);
    activeCard?.classList.add('is-selected');
}

function setServiceFilterTab(filter) {
    currentServiceFilter = filter;
    document.querySelectorAll('.svc-filter-tab').forEach(tab => {
        tab.classList.toggle('is-active', tab.dataset.serviceFilter === filter);
    });
}

function applyServiceFilters() {
    const query = (document.getElementById('bookingServiceSearch')?.value || '').trim().toLowerCase();
    let treatmentVisible = 0;
    let packageVisible   = 0;

    serviceListContainer?.querySelectorAll('.svc-card').forEach(card => {
        const matchesFilter = currentServiceFilter === 'all' || card.dataset.kind === currentServiceFilter;
        const matchesSearch = !query || card.dataset.searchText.includes(query);
        const visible = matchesFilter && matchesSearch;
        card.classList.toggle('hidden', !visible);
        if (visible && card.dataset.kind === 'treatment') treatmentVisible++;
        if (visible && card.dataset.kind === 'package')   packageVisible++;
    });

    // Hide a section header if every card under it got filtered out.
    serviceListContainer?.querySelectorAll('[data-section-header]').forEach(header => {
        const isTreatmentHeader = header.textContent === 'Treatments';
        header.classList.toggle('hidden', isTreatmentHeader ? treatmentVisible === 0 : packageVisible === 0);
    });

    const emptyState = document.getElementById('bookingServiceEmptyState');
    if (emptyState) emptyState.classList.toggle('hidden', (treatmentVisible + packageVisible) > 0);
}

document.getElementById('bookingServiceFilterTabs')?.addEventListener('click', function (e) {
    const tabBtn = e.target.closest('[data-service-filter]');
    if (!tabBtn) return;
    setServiceFilterTab(tabBtn.dataset.serviceFilter);
    applyServiceFilters();
});

document.getElementById('bookingServiceSearch')?.addEventListener('input', applyServiceFilters);

function populateTreatmentsForSelectedBranch() {
    if (!selectedSpa || !serviceListContainer) return;

    serviceListContainer.innerHTML = '';
    allServiceItems = [];

    const treatments = selectedSpa.treatments ?? [];
    const packages    = selectedSpa.packages ?? [];

    if (!treatments.length && !packages.length) {
        serviceListContainer.innerHTML = '<p class="px-4 py-6 text-sm text-center text-gray-400">No services available for this branch.</p>';
        resetServiceType();
        return;
    }

    if (treatments.length) {
        serviceListContainer.appendChild(buildSectionHeader('Treatments'));
        treatments.forEach(t => serviceListContainer.appendChild(buildServiceCard(t, 'treatment')));
    }

    if (packages.length) {
        serviceListContainer.appendChild(buildSectionHeader('Packages'));
        packages.forEach(p => serviceListContainer.appendChild(buildServiceCard(p, 'package')));
    }

    const emptyState = document.createElement('p');
    emptyState.id = 'bookingServiceEmptyState';
    emptyState.className = 'hidden px-4 py-6 text-sm text-center text-gray-400';
    emptyState.textContent = 'No services match your search.';
    serviceListContainer.appendChild(emptyState);

    const searchInput = document.getElementById('bookingServiceSearch');
    if (searchInput) searchInput.value = '';
    setServiceFilterTab('all');
    applyServiceFilters();

    resetServiceType();
}

function resetServiceType() {
    if (!serviceTypeSelect) return;
    serviceTypeSelect.innerHTML = '<option value="">Select service type</option>';
    serviceTypeSelect.value = '';
    serviceTypeSelect.disabled = false;
    if (serviceTypeHint) serviceTypeHint.textContent = '';
    if (addressWrapper) addressWrapper.classList.add('hidden');
    if (addressInput) addressInput.required = false;
}

function populateServiceTypeOptions() {
    resetServiceType();
    if (!serviceListContainer || !serviceTypeSelect) return;

    const checked = getCheckedTreatmentInput();
    if (!checked) return;

    const serviceType = checked.dataset.serviceType || 'in_branch_only';

    if (serviceType === 'in_branch_only') {
        serviceTypeSelect.innerHTML = `<option value="in_branch">In-Branch</option>`;
        serviceTypeSelect.value = 'in_branch';
        serviceTypeSelect.disabled = true;
        if (serviceTypeHint) serviceTypeHint.textContent = 'This treatment is only offered in-branch.';
    } else if (serviceType === 'in_branch_and_home') {
        serviceTypeSelect.innerHTML = `
            <option value="">Select service type</option>
            <option value="in_branch">In-Branch</option>
            <option value="in_home">Home Service</option>
        `;
        serviceTypeSelect.disabled = false;
        if (serviceTypeHint) serviceTypeHint.textContent = 'This selection is available for both in-branch and home service.';
    }

    toggleAddressField();
}

function toggleAddressField() {
    const isHome = serviceTypeSelect && serviceTypeSelect.value === 'in_home';
    if (addressWrapper) addressWrapper.classList.toggle('hidden', !isHome);
    if (addressInput) addressInput.required = isHome;
}

// ── Step 2: fetched time-slot grid ──────────────────────────────────────────
function formatTime12Hour(time) {
    if (!time) return '';
    const [hour, minute] = time.split(':').map(Number);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const h12  = hour % 12 || 12;
    return `${h12}:${String(minute).padStart(2, '0')} ${ampm}`;
}

const SLOT_REASON_LABELS = {
    fully_booked: 'Fully booked — all therapists are assigned during this time.',
    past_closing: 'This service would end after closing.',
    past: 'This time has already passed today.',
};

function selectSlot(time, btn) {
    if (bookingTimeInput) bookingTimeInput.value = time;
    document.querySelectorAll('#bookingSlotGrid .slot-btn').forEach(b => b.classList.remove('is-selected'));
    btn.classList.add('is-selected');
    document.getElementById('bookingTimeError')?.classList.add('hidden');
}

async function loadAvailableSlots() {
    const grid    = document.getElementById('bookingSlotGrid');
    const legend  = document.getElementById('bookingSlotLegend');
    const loading = document.getElementById('bookingSlotsLoading');
    const branchId = bookingBranchIdInput?.value;
    const spaId    = bookingSpaIdInput?.value;
    const date     = bookingDateInput?.value;
    const checked  = getCheckedTreatmentInput();

    if (!grid || !branchId || !spaId || !date || !checked) return;

    if (bookingTimeInput) bookingTimeInput.value = '';
    document.getElementById('bookingTimeError')?.classList.add('hidden');

    loading?.classList.remove('hidden');
    grid.innerHTML = '';
    legend?.classList.add('hidden');
    legend?.classList.remove('flex');

    try {
        const params = new URLSearchParams({
            spa_id: spaId,
            branch_id: branchId,
            treatment: checked.value,
            appointment_date: date,
        });

        const response = await fetch(`/bookings/online/available-slots?${params.toString()}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();

        if (data.closed) {
            grid.innerHTML = '<p class="col-span-3 py-6 text-sm text-center text-gray-400 dark:text-gray-500 sm:col-span-4">This branch is closed on the selected day.</p>';
            return;
        }

        if (!data.slots?.length) {
            grid.innerHTML = '<p class="col-span-3 py-6 text-sm text-center text-gray-400 dark:text-gray-500 sm:col-span-4">No time slots available for this day.</p>';
            return;
        }

        data.slots.forEach(slot => {
            const slotBtn = document.createElement('button');
            slotBtn.type = 'button';
            slotBtn.textContent = formatTime12Hour(slot.time);
            slotBtn.dataset.slotTime = slot.time;
            slotBtn.className = 'slot-btn';

            if (slot.available) {
                slotBtn.addEventListener('click', () => selectSlot(slot.time, slotBtn));
            } else {
                slotBtn.disabled = true;
                slotBtn.title = SLOT_REASON_LABELS[slot.reason] || 'Unavailable';
                if (slot.reason === 'past_closing') slotBtn.classList.add('is-past-closing');
            }

            grid.appendChild(slotBtn);
        });

        legend?.classList.remove('hidden');
        legend?.classList.add('flex');

    } catch (err) {
        console.error('Failed to load slots:', err);
        grid.innerHTML = '<p class="col-span-3 py-6 text-sm text-center text-red-400 dark:text-red-300 sm:col-span-4">Unable to load availability. Please try again.</p>';
    } finally {
        loading?.classList.add('hidden');
    }
}

// ── Step 3: recap ────────────────────────────────────────────────────────────
function buildBookingRecap() {
    const recapServiceEl = document.getElementById('recapService');
    if (!recapServiceEl) {
        console.warn('buildBookingRecap: booking form is not in the DOM (guest session?) — skipping.');
        return;
    }

    const checked = getCheckedTreatmentInput();

    recapServiceEl.textContent = checked?.dataset.name || '—';
    document.getElementById('recapServiceType').textContent = serviceTypeSelect?.selectedOptions?.[0]?.textContent ?? '—';

    const addressRow = document.getElementById('recapAddressRow');
    if (serviceTypeSelect?.value === 'in_home') {
        addressRow?.classList.remove('hidden');
        addressRow?.classList.add('flex');
        document.getElementById('recapAddress').textContent = addressInput?.value ?? '';
    } else {
        addressRow?.classList.add('hidden');
        addressRow?.classList.remove('flex');
    }

    const dateVal = bookingDateInput?.value;
    const timeVal = bookingTimeInput?.value;
    document.getElementById('recapDateTime').textContent = (dateVal && timeVal)
        ? `${new Date(dateVal + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} at ${formatTime12Hour(timeVal)}`
        : '—';

    const price = checked ? parseFloat(checked.dataset.price || '0') : 0;
    document.getElementById('recapTotalPrice').textContent = price ? formatPeso(price) : '—';
    document.getElementById('recapDownpayment').textContent = price ? `${formatPeso(price * 0.2)}` : '—';
}

function openBookingModal() {
    if (!selectedSpa || !bookingModal) return;

    clearBookingSelections();

    if (bookingSpaIdInput)    bookingSpaIdInput.value    = selectedSpa.id ?? '';
    if (bookingBranchIdInput) bookingBranchIdInput.value = selectedSpa.branch_id ?? '';

    if (bookingSpaMeta) {
        bookingSpaMeta.textContent = selectedSpa.branch_location
            ? `${selectedSpa.name} • ${selectedSpa.branch_location} Branch`
            : `${selectedSpa.name} • ${selectedSpa.branch_name ?? ''}`;
    }

    const today = getTodayLocal();
    if (bookingDateInput) bookingDateInput.min = today;

    populateTreatmentsForSelectedBranch();
    showBookingStep(1);

    bookingModal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function clearBookingSelections() {
    if (serviceListContainer) {
        serviceListContainer.querySelectorAll('input[name="treatment"]').forEach(r => { r.checked = false; });
        highlightSelectedCard();
    }
    if (bookingBranchIdInput) bookingBranchIdInput.value = '';
    resetServiceType();
    if (bookingDateInput) bookingDateInput.value = '';
    if (bookingTimeInput) bookingTimeInput.value = '';
    const slotGridEl = document.getElementById('bookingSlotGrid');
    if (slotGridEl) slotGridEl.innerHTML = '<p class="col-span-3 py-6 text-sm text-center text-gray-400 dark:text-gray-500 sm:col-span-4">Pick a date to see available times.</p>';
    document.getElementById('bookingSlotLegend')?.classList.add('hidden');
    if (addressInput) {
        addressInput.value = '';
        addressInput.required = false;
    }
    if (addressWrapper) addressWrapper.classList.add('hidden');
    if (bookingCustomerPhone) bookingCustomerPhone.value = bookingCustomerPhone.dataset.defaultPhone || '';
    const termsCheckbox = document.getElementById('bookingTermsCheckbox');
    if (termsCheckbox) termsCheckbox.checked = false;

    const timeError = document.getElementById('bookingTimeError');
    const treatmentError = document.getElementById('bookingTreatmentError');
    const submitBtn = document.getElementById('bookingSubmitBtn');
    if (timeError) { timeError.classList.add('hidden'); }
    if (treatmentError) treatmentError.classList.add('hidden');
    if (submitBtn) submitBtn.disabled = false;
}

function openApplicationModal(spaId, branchId, spaName) {
    const modal = document.getElementById('applicationModal');
    const form  = document.getElementById('applicationForm');
    if (!modal || !form) return;

    form.reset();
    form.action = `/apply/${branchId}`;

    document.getElementById('applicationSpaId').value    = spaId;
    document.getElementById('applicationBranchId').value = branchId;
    document.getElementById('applicationSpaMeta').textContent = spaName;

    const errorEl = document.getElementById('applicationError');
    if (errorEl) errorEl.classList.add('hidden');

    hideResumeUploadStatus();
    const resumeInput = form.querySelector('input[name="resume"]');
    if (resumeInput) resumeInput.value = '';

    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeApplicationModal() {
    const modal = document.getElementById('applicationModal');
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

// =====================================================
// APPLICATION FORM SUBMISSION (with resume upload)
// =====================================================

function setupApplicationForm() {
    const form = document.getElementById('applicationForm');
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const submitBtn = document.getElementById('applicationSubmitBtn');
        const originalText = submitBtn.innerHTML;
        const errorEl = document.getElementById('applicationError');
        const errorText = document.getElementById('applicationErrorText');

        // Reset error display
        if (errorEl) errorEl.classList.add('hidden');

        // Validate required fields
        const fullName = this.querySelector('input[name="full_name"]')?.value.trim();
        const email = this.querySelector('input[name="email"]')?.value.trim();
        const phone = this.querySelector('input[name="phone"]')?.value.trim();
        const address = this.querySelector('input[name="address"]')?.value.trim();
        const position = this.querySelector('select[name="position_applied"]')?.value;
        const resume = this.querySelector('input[name="resume"]')?.files[0];

        if (!fullName || !email || !phone || !address || !position) {
            showSpaToast('Please fill in all required fields.', 'error');
            return;
        }

        // Validate phone format
        if (!/^09\d{9}$/.test(phone)) {
            showSpaToast('Please enter a valid 11-digit phone number (09xxxxxxxxx).', 'error');
            return;
        }

        // Validate email
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showSpaToast('Please enter a valid email address.', 'error');
            return;
        }

        // Validate resume is required
        if (!resume) {
            showSpaToast('Please upload your resume/CV.', 'error');
            return;
        }

        // Validate resume file type and size
        const validTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        const validExtensions = ['.pdf', '.doc', '.docx'];
        const fileName = resume.name.toLowerCase();
        const isValidExtension = validExtensions.some(ext => fileName.endsWith(ext));

        if (!validTypes.includes(resume.type) && !isValidExtension) {
            showSpaToast('Please upload a PDF, DOC, or DOCX file.', 'error');
            return;
        }

        if (resume.size > 5 * 1024 * 1024) {
            showSpaToast('File size exceeds 5MB limit. Please choose a smaller file.', 'error');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Submitting...';

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const formData = new FormData(this);

            const response = await fetch(this.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken ?? '',
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const data = await response.json();

            if (!response.ok) {
                const firstError = data.errors
                    ? Object.values(data.errors)[0][0]
                    : (data.message ?? 'Please check your application details.');
                showSpaToast(firstError, 'error');
                if (errorEl && errorText) {
                    errorText.textContent = firstError;
                    errorEl.classList.remove('hidden');
                }
                return;
            }

            // Success - close modal and show success message
            closeApplicationModal();
            showSpaToast(data.message || 'Application submitted successfully!', 'success');

            // Reset form
            this.reset();
            hideResumeUploadStatus();

        } catch (err) {
            console.error('Application submission error:', err);
            showSpaToast('Network error. Please try again.', 'error');
            if (errorEl && errorText) {
                errorText.textContent = 'Network error. Please try again.';
                errorEl.classList.remove('hidden');
            }
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
}

// Initialize the form handler
document.addEventListener('DOMContentLoaded', function() {
    setupApplicationForm();
});

// =====================================================
// RESUME UPLOAD FUNCTIONS
// =====================================================

// Track uploaded resume file
let uploadedResumeFile = null;
let uploadedResumeName = '';

// Show resume upload status
function showResumeUploadStatus(name, size) {
    const statusDiv = document.getElementById('resumeUploadStatus');
    const fileNameEl = document.getElementById('resumeFileName');
    const fileSizeEl = document.getElementById('resumeFileSize');

    if (statusDiv && fileNameEl && fileSizeEl) {
        fileNameEl.textContent = name;
        fileSizeEl.textContent = formatFileSize(size);
        statusDiv.classList.remove('hidden');
    }
}

// Hide resume upload status
function hideResumeUploadStatus() {
    const statusDiv = document.getElementById('resumeUploadStatus');
    if (statusDiv) {
        statusDiv.classList.add('hidden');
    }
    uploadedResumeFile = null;
    uploadedResumeName = '';
}

// Clear resume upload
function clearResumeUpload() {
    const form = document.getElementById('applicationForm');
    if (form) {
        const resumeInput = form.querySelector('input[name="resume"]');
        if (resumeInput) {
            resumeInput.value = '';
        }
    }
    hideResumeUploadStatus();
}

// Format file size for display
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Handle resume file selection
function handleResumeFileSelect(inputElement) {
    if (!inputElement || !inputElement.files || inputElement.files.length === 0) {
        hideResumeUploadStatus();
        return;
    }

    const file = inputElement.files[0];

    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
        showSpaToast('File size exceeds 5MB limit. Please choose a smaller file.', 'error');
        inputElement.value = '';
        hideResumeUploadStatus();
        return;
    }

    // Validate file type
    const validTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    const validExtensions = ['.pdf', '.doc', '.docx'];
    const fileName = file.name.toLowerCase();
    const isValidExtension = validExtensions.some(ext => fileName.endsWith(ext));

    if (!validTypes.includes(file.type) && !isValidExtension) {
        showSpaToast('Please upload a PDF, DOC, or DOCX file.', 'error');
        inputElement.value = '';
        hideResumeUploadStatus();
        return;
    }

    // Show uploaded file info
    uploadedResumeFile = file;
    uploadedResumeName = file.name;
    showResumeUploadStatus(file.name, file.size);
}

// Setup resume file input listener
function setupResumeUploadHandler() {
    const form = document.getElementById('applicationForm');
    if (!form) return;

    const resumeInput = form.querySelector('input[name="resume"]');
    if (!resumeInput) return;

    // Remove any existing listener to avoid duplicates
    resumeInput.removeEventListener('change', resumeInput._resumeHandler);

    // Add change listener
    resumeInput._resumeHandler = function(e) {
        handleResumeFileSelect(this);
    };
    resumeInput.addEventListener('change', resumeInput._resumeHandler);
}

// Initialize resume handler when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    setupResumeUploadHandler();
});

function closeBookingModal() {
    if (!bookingModal) return;
    bookingModal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

openBookingBtn?.addEventListener('click', () => {
    try {
        openBookingModal();
    } catch (e) {
        console.error('openBookingModal failed to open:', e);
    }
});
closeBookingBtns.forEach(btn => btn.addEventListener('click', closeBookingModal));

serviceListContainer?.addEventListener('change', function (e) {
    if (e.target?.name !== 'treatment') return;
    highlightSelectedCard();
    populateServiceTypeOptions();
    document.getElementById('bookingTreatmentError')?.classList.add('hidden');

    // Duration may have changed — a previously picked time might no longer be
    // valid, so drop it and let the next slot fetch (step 2) recompute it.
    if (bookingTimeInput) bookingTimeInput.value = '';
});

serviceTypeSelect?.addEventListener('change', toggleAddressField);
bookingDateInput?.addEventListener('change', loadAvailableSlots);

document.getElementById('bookingNextBtn')?.addEventListener('click', function () {
    if (!validateBookingStep(currentBookingStep)) return;
    if (currentBookingStep < TOTAL_BOOKING_STEPS) {
        const nextStep = currentBookingStep + 1;
        showBookingStep(nextStep);
        if (nextStep === 2 && bookingDateInput?.value) loadAvailableSlots();
    }
});

document.getElementById('bookingBackBtn')?.addEventListener('click', function () {
    if (currentBookingStep > 1) showBookingStep(currentBookingStep - 1);
});

bookingForm?.addEventListener('submit', async function (e) {
    e.preventDefault();

    if (!validateBookingStep(1) || !validateBookingStep(2)) {
        showSpaToast('Please complete all booking steps first.', 'error');
        return;
    }

    const phone = bookingCustomerPhone?.value ?? '';
    if (!/^09\d{9}$/.test(phone)) {
        showSpaToast('Please enter a valid 11-digit phone number (09xxxxxxxxx).', 'error');
        bookingCustomerPhone?.focus();
        return;
    }

    if (!document.getElementById('bookingTermsCheckbox')?.checked) {
        showSpaToast('Please agree to the terms and conditions.', 'error');
        return;
    }

    const submitBtn = document.getElementById('bookingSubmitBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Reserving...';

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const serviceTypeWasDisabled = serviceTypeSelect?.disabled;
        if (serviceTypeWasDisabled) serviceTypeSelect.disabled = false;

        const formData = new FormData(bookingForm);

        if (serviceTypeWasDisabled) serviceTypeSelect.disabled = true;

        const response = await fetch(bookingForm.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken ?? '',
                'Accept': 'application/json',
            },
            body: formData,
        });

        const data = await response.json();

        if (!response.ok) {
            const firstError = data.errors
                ? Object.values(data.errors)[0][0]
                : (data.message ?? 'Please check your booking details.');
            showSpaToast(firstError, 'error');
            return;
        }

        if (data.checkout_url) {
            window.location.href = data.checkout_url;
            return;
        }

        closeBookingModal();
        closeSpaModal();
        showSpaToast(data.message ?? 'Appointment reserved!', 'success');
        loadAppointments();
        loadSchedule();

    } catch (err) {
        showSpaToast('Network error. Please try again.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
});

bookingCustomerPhone?.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 11);
});

// =====================================================
// MY APPOINTMENTS MODAL
// =====================================================
let allAppointments = [];
let currentTab      = 'upcoming';
let _appointmentMap = {};

function openAppointmentsModal() {
    document.getElementById('appointmentsModal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    loadAppointments();
}

function closeAppointmentsModal() {
    document.getElementById('appointmentsModal').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

function loadAppointments() {
    fetch('/my-appointments')
        .then(r => r.json())
        .then(data => {
            allAppointments = data;
            updateTabCounts();
            renderTab(currentTab);

            const upcomingCount = data.filter(b =>
                ['reserved', 'pending', 'ongoing'].includes(b.status) && !isBookingPastNow(b)
            ).length;
            updateAppointmentsBadge(upcomingCount);
            checkUnratedBookings(data);
        });
}

function updateAppointmentsBadge(count) {
    [document.getElementById('myAppointmentsBadge'), document.getElementById('myAppointmentsBadgeMobile')]
        .forEach(el => {
            if (!el) return;
            if (count > 0) {
                el.textContent = count > 99 ? '99+' : count;
                el.classList.remove('hidden');
                el.classList.add('flex');
            } else {
                el.classList.add('hidden');
                el.classList.remove('flex');
            }
        });
}

function updateTabCounts() {
    document.getElementById('tab-count-upcoming').textContent =
        allAppointments.filter(b => ['reserved', 'pending', 'ongoing'].includes(b.status) && !isBookingPastNow(b)).length;
    document.getElementById('tab-count-past').textContent =
        allAppointments.filter(b => b.status === 'completed' || (['reserved', 'pending', 'ongoing'].includes(b.status) && isBookingPastNow(b))).length;
    document.getElementById('tab-count-cancelled').textContent =
        allAppointments.filter(b => b.status === 'cancelled').length;
}

function switchTab(tab) {
    currentTab = tab;
    ['upcoming', 'past', 'cancelled'].forEach(t => {
        const el = document.getElementById(`tab-${t}`);
        if (t === tab) {
            el.classList.add('border-[#8B7355]', 'dark:border-[#C4A97D]', 'text-[#8B7355]', 'dark:text-[#C4A97D]');
            el.classList.remove('border-transparent', 'text-gray-500', 'dark:text-gray-400');
        } else {
            el.classList.remove('border-[#8B7355]', 'dark:border-[#C4A97D]', 'text-[#8B7355]', 'dark:text-[#C4A97D]');
            el.classList.add('border-transparent', 'text-gray-500', 'dark:text-gray-400');
        }
    });
    renderTab(tab);
}

function renderTab(tab) {
    let filtered = [];
    if (tab === 'upcoming') {
        filtered = allAppointments.filter(b => ['reserved', 'pending', 'ongoing'].includes(b.status) && !isBookingPastNow(b));
    } else if (tab === 'past') {
        filtered = allAppointments.filter(b => b.status === 'completed' || (['reserved', 'pending', 'ongoing'].includes(b.status) && isBookingPastNow(b)));
    } else {
        filtered = allAppointments.filter(b => b.status === 'cancelled');
    }

    Object.keys(_appointmentMap).forEach(k => delete _appointmentMap[k]);
    filtered.forEach((b, i) => { _appointmentMap[i] = b; });

    const container = document.getElementById('appointmentsContent');
    if (!filtered.length) {
        container.innerHTML = `
            <div class="py-12 text-center text-gray-400 dark:text-gray-500">
                <i class="mb-3 text-3xl fa-solid fa-calendar-xmark"></i>
                <p class="text-sm">No ${tab} appointments</p>
            </div>`;
        return;
    }

    container.innerHTML = filtered.map((b, i) => {
        const canRate   = b.status === 'completed' && !b.has_rating;
        const hasRating = b.has_rating === true;

        return `
        <div class="p-4 mb-3 border border-black/5 dark:border-white/10 rounded-2xl bg-[#F6EFE6]/40 dark:bg-gray-700/40 ring-1 ring-black/5 dark:ring-white/10 transition hover:shadow-md">
            <div onclick="openBookingDetailsModal(_appointmentMap[${i}])" class="cursor-pointer">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="font-semibold text-[#3C2F23] dark:text-white">${escapeHtml(b.spa_name)}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(b.branch_location ?? b.branch_name)} • ${b.service_type}</p>
                    </div>
                    <span class="px-2 py-1 text-[10px] font-semibold rounded-full ${statusBadge(b.status)}">
                        ${b.status.charAt(0).toUpperCase() + b.status.slice(1)}
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-2 mt-3 text-xs text-gray-600 dark:text-gray-400">
                    <div class="flex items-center gap-1"><i class="fa-solid fa-spa text-[#8B7355] dark:text-[#C4A97D]"></i> ${escapeHtml(b.treatment)}</div>
                    <div class="flex items-center gap-1"><i class="fa-solid fa-user-nurse text-[#8B7355] dark:text-[#C4A97D]"></i> ${escapeHtml(b.therapist)}</div>
                    <div class="flex items-center gap-1"><i class="fa-solid fa-calendar text-[#8B7355] dark:text-[#C4A97D]"></i> ${b.date}</div>
                    <div class="flex items-center gap-1"><i class="fa-solid fa-clock text-[#8B7355] dark:text-[#C4A97D]"></i> ${formatTime(b.start_time)} – ${formatTime(b.end_time)}</div>
                </div>
                ${b.reschedule_status === 'pending' ? `
                <div class="mt-2 text-[11px] font-semibold text-amber-600 dark:text-amber-400 flex items-center gap-1">
                    <i class="fa-solid fa-clock-rotate-left"></i> Reschedule request pending
                </div>` : ''}
            </div>
            ${canRate ? `
            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                <button onclick="openRatingModal(_appointmentMap[${i}].id, _appointmentMap[${i}].therapist, _appointmentMap[${i}].spa_name, _appointmentMap[${i}].branch_name, _appointmentMap[${i}].branch_location)"
                    class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white transition rounded-xl bg-[#8B7355] hover:bg-[#6F5430] w-full justify-center">
                    <i class="fa-solid fa-star"></i>
                    Rate Your Experience
                </button>
            </div>` : ''}
            ${hasRating ? `
            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-green-600 dark:text-green-400 text-sm"></i>
                        <span class="text-sm font-semibold text-green-600 dark:text-green-400">Thank you for rating!</span>
                    </div>
                    <div class="flex items-center gap-0.5">
                        ${renderStars(b.rating_value)}
                    </div>
                </div>
            </div>` : ''}
        </div>`;
    }).join('');
}

// =====================================================
// BUSINESS INFO MODAL
// =====================================================
function openBusinessInfo() {
    document.getElementById('businessInfoModal')?.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeBusinessInfo() {
    document.getElementById('businessInfoModal')?.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

window.openBusinessInfo  = openBusinessInfo;
window.closeBusinessInfo = closeBusinessInfo;

// Helper function to escape HTML to prevent XSS
function escapeHtml(str) {
    if (!str) return '';
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// Helper function to render stars
function renderStars(rating) {
    if (!rating) return '';
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        stars += `<i class="fa-solid fa-star ${i <= rating ? 'text-yellow-400' : 'text-gray-300 dark:text-gray-600'} text-xs"></i>`;
    }
    return stars;
}

function statusBadge(status) {
    const map = {
        reserved:  'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        ongoing:   'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        completed: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        pending:   'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    };
    return map[status] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300';
}

// =====================================================
// MY SCHEDULE MODAL
// =====================================================
let scheduleBookings  = [];
let calendarDate      = new Date();
let _dayBookingMap    = {};
let _listBookingMap   = {};
let scheduleViewMode  = 'list'; // 'list' | 'calendar'

function openScheduleModal() {
    document.getElementById('scheduleModal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    loadSchedule();
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

function loadSchedule() {
    fetch('/my-schedule')
        .then(r => r.json())
        .then(data => {
            scheduleBookings = data;
            renderSchedule();
        });
}

function renderSchedule() {
    if (scheduleViewMode === 'list') {
        renderScheduleList();
    } else {
        renderCalendar();
    }
}

function toggleScheduleView() {
    scheduleViewMode = scheduleViewMode === 'list' ? 'calendar' : 'list';

    const listView  = document.getElementById('scheduleListView');
    const calView   = document.getElementById('scheduleCalendarView');
    const toggleBtn = document.getElementById('scheduleViewToggleBtn');

    if (scheduleViewMode === 'list') {
        listView?.classList.remove('hidden');
        calView?.classList.add('hidden');
        if (toggleBtn) {
            toggleBtn.title = 'Switch to calendar view';
            toggleBtn.innerHTML = '<i class="text-sm fa-solid fa-calendar-days"></i>';
        }
    } else {
        listView?.classList.add('hidden');
        calView?.classList.remove('hidden');
        if (toggleBtn) {
            toggleBtn.title = 'Switch to list view';
            toggleBtn.innerHTML = '<i class="text-sm fa-solid fa-list"></i>';
        }
        document.getElementById('selectedDayBookings')?.classList.add('hidden');
    }

    renderSchedule();
}

function changeMonth(dir) {
    calendarDate.setMonth(calendarDate.getMonth() + dir);
    document.getElementById('selectedDayBookings')?.classList.add('hidden');
    renderSchedule();
}

// ── LIST VIEW: every booking this month, grouped by day ────────────────────
function renderScheduleList() {
    const year  = calendarDate.getFullYear();
    const month = calendarDate.getMonth();
    document.getElementById('calendarTitle').textContent =
        calendarDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

    const monthBookings = scheduleBookings
        .filter(b => {
            const d = new Date(b.date_raw + 'T00:00:00');
            return d.getFullYear() === year && d.getMonth() === month;
        })
        .sort((a, b) => {
            if (a.date_raw !== b.date_raw) return a.date_raw.localeCompare(b.date_raw);
            return (a.start_time || '').localeCompare(b.start_time || '');
        });

    const container = document.getElementById('scheduleListContent');
    if (!container) return;

    if (!monthBookings.length) {
        container.innerHTML = `
            <div class="py-12 text-center text-gray-400 dark:text-gray-500">
                <i class="mb-3 text-3xl fa-solid fa-calendar-xmark"></i>
                <p class="text-sm">No bookings this month</p>
            </div>`;
        return;
    }

    const groups = {};
    monthBookings.forEach(b => { (groups[b.date_raw] ??= []).push(b); });

    Object.keys(_listBookingMap).forEach(k => delete _listBookingMap[k]);
    let idx = 0;

    const today = getTodayLocal();

    container.innerHTML = Object.keys(groups).sort().map(dateRaw => {
        const dayBookings = groups[dateRaw];
        const isToday     = dateRaw === today;
        const dateLabel   = new Date(dateRaw + 'T00:00:00').toLocaleDateString('en-US', {
            weekday: 'long', month: 'long', day: 'numeric'
        });

        const items = dayBookings.map(b => {
            _listBookingMap[idx] = b;
            const html = `
                <div class="p-3 border border-black/5 dark:border-white/10 rounded-xl bg-[#F6EFE6]/50 dark:bg-gray-700/40 ring-1 ring-black/5 dark:ring-white/10 cursor-pointer hover:shadow-md transition"
                    onclick="openBookingDetailsModal(_listBookingMap[${idx}])">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-[#3C2F23] dark:text-white">${escapeHtml(b.spa_name)}</p>
                        <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full ${statusBadge(b.status)}">${b.status}</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">${escapeHtml(b.branch_name)} • ${escapeHtml(b.treatment)}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        <i class="fa-solid fa-clock text-[#8B7355] dark:text-[#C4A97D]"></i>
                        ${formatTime(b.start_time)} – ${formatTime(b.end_time)} • ${escapeHtml(b.therapist)}
                    </p>
                    ${b.reschedule_status === 'pending' ? `
                    <div class="mt-2 text-[11px] font-semibold text-amber-600 dark:text-amber-400 flex items-center gap-1">
                        <i class="fa-solid fa-clock-rotate-left"></i> Reschedule request pending
                    </div>` : ''}
                </div>`;
            idx++;
            return html;
        }).join('');

        return `
            <div class="mb-5">
                <div class="flex items-center gap-2 mb-2">
                    <h4 class="text-sm font-semibold ${isToday ? 'text-[#8B7355] dark:text-[#C4A97D]' : 'text-[#3C2F23] dark:text-white'}">${dateLabel}</h4>
                    ${isToday ? '<span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-[#8B7355] text-white">Today</span>' : ''}
                    <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-[#F6EFE6] dark:bg-gray-700 text-[#6F5430] dark:text-[#C4A97D]">${dayBookings.length}</span>
                </div>
                <div class="space-y-2">${items}</div>
            </div>`;
    }).join('');
}

// ── CALENDAR VIEW (unchanged behavior, now optional) ────────────────────────
function renderCalendar() {
    const year  = calendarDate.getFullYear();
    const month = calendarDate.getMonth();
    const today = getTodayLocal();
    document.getElementById('calendarTitle').textContent =
        calendarDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    const firstDay    = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const bookedDates = new Set(scheduleBookings.map(b => b.date_raw));
    const grid        = document.getElementById('calendarGrid');
    grid.innerHTML    = '';
    for (let i = 0; i < firstDay; i++) grid.innerHTML += `<div></div>`;
    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr    = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const isToday    = dateStr === today;
        const hasBooking = bookedDates.has(dateStr);
        const isPast     = dateStr < today;
        grid.innerHTML  += `
            <button onclick="selectDay('${dateStr}')"
                class="relative flex flex-col items-center justify-center h-10 rounded-xl text-sm transition
                ${isToday ? 'bg-[#8B7355] text-white font-bold' : ''}
                ${hasBooking && !isToday ? 'bg-[#F6EFE6] dark:bg-gray-700 text-[#6F5430] dark:text-[#C4A97D] font-semibold ring-1 ring-[#8B7355]/30 dark:ring-[#C4A97D]/30' : ''}
                ${isPast && !isToday ? 'text-gray-300 dark:text-gray-600 cursor-default' : 'hover:bg-[#F6EFE6] dark:hover:bg-gray-700'}
                ${!hasBooking && !isToday && !isPast ? 'text-gray-700 dark:text-gray-300' : ''}">
                ${d}
                ${hasBooking ? `<span class="absolute bottom-1 w-1 h-1 rounded-full ${isToday ? 'bg-white' : 'bg-[#8B7355] dark:bg-[#C4A97D]'}"></span>` : ''}
            </button>`;
    }
}

function selectDay(dateStr) {
    const dayBookings = scheduleBookings.filter(b => b.date_raw === dateStr);
    if (!dayBookings.length) return;

    Object.keys(_dayBookingMap).forEach(k => delete _dayBookingMap[k]);
    dayBookings.forEach((b, i) => { _dayBookingMap[i] = b; });

    const title = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', {
        weekday: 'long', month: 'long', day: 'numeric'
    });
    document.getElementById('selectedDayTitle').textContent = title;
    document.getElementById('selectedDayContent').innerHTML = dayBookings.map((b, i) => `
        <div class="p-3 mb-3 border border-black/5 dark:border-white/10 rounded-xl bg-[#F6EFE6]/50 dark:bg-gray-700/40 ring-1 ring-black/5 dark:ring-white/10 cursor-pointer hover:shadow-md transition"
            onclick="openBookingDetailsModal(_dayBookingMap[${i}])">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-[#3C2F23] dark:text-white">${escapeHtml(b.spa_name)}</p>
                <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full ${statusBadge(b.status)}">${b.status}</span>
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">${escapeHtml(b.branch_name)} • ${escapeHtml(b.treatment)}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                <i class="fa-solid fa-clock text-[#8B7355] dark:text-[#C4A97D]"></i>
                ${formatTime(b.start_time)} – ${formatTime(b.end_time)} • ${escapeHtml(b.therapist)}
            </p>
            ${b.reschedule_status === 'pending' ? `
            <div class="mt-2 text-[11px] font-semibold text-amber-600 dark:text-amber-400 flex items-center gap-1">
                <i class="fa-solid fa-clock-rotate-left"></i> Reschedule request pending
            </div>` : ''}
        </div>
    `).join('');
    document.getElementById('selectedDayBookings').classList.remove('hidden');
}

function formatTime(timeStr) {
    if (!timeStr) return 'N/A';
    const [hour, minute] = timeStr.split(':');
    const h    = parseInt(hour);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12  = h % 12 || 12;
    return `${h12}:${minute} ${ampm}`;
}

// =====================================================
// BOOKING DETAILS MODAL
// =====================================================
let selectedBooking = null;

function openBookingDetailsModal(booking) {
    selectedBooking = booking;

    document.getElementById('detailSpaName').textContent =
        `${booking.spa_name} • ${booking.branch_name}`;
    document.getElementById('detailTreatment').textContent = booking.treatment;
    document.getElementById('detailDate').textContent      = booking.date;
    document.getElementById('detailTime').textContent      =
        `${formatTime(booking.start_time)} – ${formatTime(booking.end_time)}`;
    document.getElementById('detailTherapist').textContent = booking.therapist;

    const statusEl = document.getElementById('detailStatus');
    statusEl.textContent = booking.status.charAt(0).toUpperCase() + booking.status.slice(1);
    statusEl.className   = `text-sm font-semibold ${statusColor(booking.status)}`;

    const rescheduleStatusEl   = document.getElementById('detailRescheduleStatus');
    const rescheduleStatusText = document.getElementById('detailRescheduleStatusText');
    const rescheduleBtn        = document.getElementById('openRescheduleBtn');

    if (booking.reschedule_status === 'pending') {
        rescheduleStatusEl.classList.remove('hidden');
        rescheduleStatusEl.className     = 'p-3 rounded-xl ring-1 bg-amber-50 dark:bg-amber-900/20 ring-amber-200 dark:ring-amber-800';
        rescheduleStatusText.textContent = '⏳ Reschedule request is pending approval.';
        rescheduleStatusText.className   = 'text-sm font-semibold text-amber-700 dark:text-amber-300';
        rescheduleBtn.disabled           = true;
        rescheduleBtn.classList.add('opacity-50', 'cursor-not-allowed');
    } else if (booking.reschedule_status === 'approved') {
        rescheduleStatusEl.classList.remove('hidden');
        rescheduleStatusEl.className     = 'p-3 rounded-xl ring-1 bg-green-50 dark:bg-green-900/20 ring-green-200 dark:ring-green-800';
        rescheduleStatusText.textContent = '✅ Your reschedule was approved.';
        rescheduleStatusText.className   = 'text-sm font-semibold text-green-700 dark:text-green-300';
        rescheduleBtn.disabled           = false;
        rescheduleBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    } else if (booking.reschedule_status === 'rejected') {
        rescheduleStatusEl.classList.remove('hidden');
        rescheduleStatusEl.className     = 'p-3 rounded-xl ring-1 bg-red-50 dark:bg-red-900/20 ring-red-200 dark:ring-red-800';
        rescheduleStatusText.textContent = '❌ Your last reschedule request was rejected.';
        rescheduleStatusText.className   = 'text-sm font-semibold text-red-600 dark:text-red-400';
        rescheduleBtn.disabled           = false;
        rescheduleBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    } else {
        rescheduleStatusEl.classList.add('hidden');
        rescheduleBtn.disabled = false;
        rescheduleBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    }

    if (!['reserved', 'pending'].includes(booking.status)) {
        rescheduleBtn.classList.add('hidden');
    } else {
        rescheduleBtn.classList.remove('hidden');
    }

    document.getElementById('bookingDetailsModal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeBookingDetailsModal() {
    document.getElementById('bookingDetailsModal').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

function statusColor(status) {
    const map = {
        reserved:  'text-blue-600 dark:text-blue-400',
        pending:   'text-amber-600 dark:text-amber-400',
        ongoing:   'text-emerald-600 dark:text-emerald-400',
        completed: 'text-gray-500 dark:text-gray-400',
        cancelled: 'text-red-500 dark:text-red-400',
    };
    return map[status] ?? 'text-gray-600 dark:text-gray-400';
}

// =====================================================
// RESCHEDULE MODAL
// =====================================================
function openRescheduleModal() {
    if (!selectedBooking) return;

    document.getElementById('rescheduleBookingId').value             = selectedBooking.id;
    document.getElementById('rescheduleCurrentSchedule').textContent =
        `${selectedBooking.date} at ${formatTime(selectedBooking.start_time)}`;

    const today     = getTodayLocal();
    const timeInput = document.getElementById('rescheduleTime');

    document.getElementById('rescheduleDate').min   = today;
    document.getElementById('rescheduleDate').value = '';
    timeInput.value    = '';
    timeInput.disabled = false;
    timeInput.removeAttribute('min');
    timeInput.removeAttribute('max');
    document.getElementById('rescheduleReason').value            = '';
    document.getElementById('rescheduleReasonCount').textContent = '0 / 1000 characters';

    document.getElementById('rescheduleError').classList.add('hidden');
    document.getElementById('rescheduleTimeError').classList.add('hidden');

    document.getElementById('rescheduleModal').classList.remove('hidden');
}

function closeRescheduleModal() {
    document.getElementById('rescheduleModal').classList.add('hidden');
}

async function updateRescheduleAvailableTimes() {
    const branchId      = selectedBooking?.branch_id;
    const dateValue     = document.getElementById('rescheduleDate').value;
    const timeInput     = document.getElementById('rescheduleTime');
    const timeError     = document.getElementById('rescheduleTimeError');
    const timeErrorText = document.getElementById('rescheduleTimeErrorText');
    const submitBtn     = document.getElementById('rescheduleSubmitBtn');

    if (!branchId || !dateValue) return;

    const day = new Date(dateValue + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long' });

    try {
        const response = await fetch(`/api/operating-hours/${branchId}/${day}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data     = await response.json();

        if (data.is_closed) {
            timeInput.value = '';
            timeInput.disabled = true;
            timeInput.removeAttribute('min');
            timeInput.removeAttribute('max');
            timeErrorText.textContent = 'The spa is closed on the selected day.';
            timeError.classList.remove('hidden');
            submitBtn.disabled = true;
            return;
        }

        timeInput.disabled = false;
        timeError.classList.add('hidden');
        submitBtn.disabled = false;

        const opening = (data.opening_time || '').slice(0, 5);
        const closing = (data.closing_time || '').slice(0, 5);
        timeInput.min = opening;
        timeInput.max = closing;

        if (timeInput.value) validateRescheduleTime();

    } catch (err) {
        console.error('Failed to load operating hours for reschedule:', err);
        showSpaToast('Unable to check spa hours. Please try again.', 'error');
    }
}

function validateRescheduleTime() {
    const timeInput     = document.getElementById('rescheduleTime');
    const dateValue     = document.getElementById('rescheduleDate').value;
    const timeValue     = timeInput.value;
    const timeError     = document.getElementById('rescheduleTimeError');
    const timeErrorText = document.getElementById('rescheduleTimeErrorText');
    const submitBtn     = document.getElementById('rescheduleSubmitBtn');

    if (!dateValue || !timeValue) {
        timeError.classList.add('hidden');
        submitBtn.disabled = false;
        return true;
    }

    const selectedTime = timeValue.slice(0, 5);
    const openingTime  = (timeInput.min || '').slice(0, 5);
    const closingTime  = (timeInput.max || '').slice(0, 5);

    if (openingTime && selectedTime < openingTime) {
        timeErrorText.textContent =
            `Selected time must be within branch hours only (${formatTime12Hour(openingTime)} to ${formatTime12Hour(closingTime)}).`;
        timeError.classList.remove('hidden');
        submitBtn.disabled = true;
        return false;
    }

    if (closingTime && selectedTime >= closingTime) {
        timeErrorText.textContent =
            `Selected time must be within branch hours only (${formatTime12Hour(openingTime)} to ${formatTime12Hour(closingTime)}).`;
        timeError.classList.remove('hidden');
        submitBtn.disabled = true;
        return false;
    }

    const today = getTodayLocal();
    if (dateValue === today) {
        const now = new Date();
        const [hh, mm] = selectedTime.split(':').map(Number);
        const selected  = new Date();
        selected.setHours(hh, mm, 0, 0);
        if (selected <= now) {
            timeErrorText.textContent = 'Please select a future time.';
            timeError.classList.remove('hidden');
            submitBtn.disabled = true;
            return false;
        }
    }

    timeError.classList.add('hidden');
    submitBtn.disabled = false;
    return true;
}

document.getElementById('rescheduleReason')?.addEventListener('input', function () {
    document.getElementById('rescheduleReasonCount').textContent =
        `${this.value.length} / 1000 characters`;
});

document.getElementById('rescheduleDate')?.addEventListener('change', updateRescheduleAvailableTimes);
document.getElementById('rescheduleTime')?.addEventListener('change', validateRescheduleTime);
document.getElementById('rescheduleTime')?.addEventListener('input',  validateRescheduleTime);

async function submitRescheduleRequest() {
    if (!validateRescheduleTime()) return;

    const bookingId = document.getElementById('rescheduleBookingId').value;
    const date      = document.getElementById('rescheduleDate').value;
    const time      = document.getElementById('rescheduleTime').value;
    const reason    = document.getElementById('rescheduleReason').value.trim();
    const errorEl   = document.getElementById('rescheduleError');
    const errorText = document.getElementById('rescheduleErrorText');
    const submitBtn = document.getElementById('rescheduleSubmitBtn');

    if (!date || !time || !reason) {
        errorText.textContent = 'Please fill in all fields.';
        errorEl.classList.remove('hidden');
        return;
    }

    if (reason.length < 10) {
        errorText.textContent = 'Reason must be at least 10 characters.';
        errorEl.classList.remove('hidden');
        return;
    }

    errorEl.classList.add('hidden');
    submitBtn.disabled  = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Submitting...';

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        const response = await fetch('/reschedule-requests', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken ?? '',
            },
            body: JSON.stringify({
                booking_id:     bookingId,
                requested_date: date,
                requested_time: time,
                reason:         reason,
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            errorText.textContent = data.message ?? 'Something went wrong. Please try again.';
            errorEl.classList.remove('hidden');
            return;
        }

        closeRescheduleModal();
        closeBookingDetailsModal();
        showSpaToast('Reschedule request submitted! Waiting for approval.', 'success');

        loadAppointments();
        loadSchedule();

    } catch (err) {
        errorText.textContent = 'Network error. Please try again.';
        errorEl.classList.remove('hidden');
    } finally {
        submitBtn.disabled  = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane mr-2"></i> Submit Request';
    }
}

// =====================================================
// SPAS NEAR YOU
// =====================================================
async function loadNearbySpas() {
    try {
        const res  = await fetch('/web-api/spas/nearby');
        const data = await res.json();

        if (!Array.isArray(data) || data.length === 0) return;

        const section = document.getElementById('nearbySection');
        const grid    = document.getElementById('nearbyGrid');
        if (!section || !grid) return;

        const fallback = document.body.dataset.fallbackImage ?? '';

        grid.innerHTML = data.map(spa => {
            const thumb = spa.photos?.[0] || fallback;
            const addr  = spa.address ?? '';
            const parts = addr.split(',').map(s => s.trim());
            const addrSummary = parts.length >= 3
                ? parts.slice(0, parts.length - 2).slice(-3).join(', ')
                : (addr || 'Location unavailable');

            const escapedData = JSON.stringify(spa).replace(/'/g, '&#39;');

            return `
                <button type="button"
                    class="w-full overflow-hidden text-left transition bg-white dark:bg-gray-800 shadow-sm group rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-2xl"
                    data-open-spa-modal
                    data-spa='${escapedData}'>
                    <div class="relative overflow-hidden">
                        <img src="${thumb}" class="h-56 w-full object-cover transition duration-500 group-hover:scale-[1.04]" alt="${spa.name}">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-black/0 to-transparent"></div>
                        <div class="absolute top-3 left-3 flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/80 dark:bg-gray-900/70 text-[#6F5430] dark:text-[#C4A97D] text-[11px] font-semibold backdrop-blur-sm ring-1 ring-black/5 dark:ring-white/10">
                            <i class="fa-solid fa-location-dot text-[#8B7355] dark:text-[#C4A97D] text-[10px]"></i>
                            ${spa.distance_km} km away
                        </div>
                    </div>
                    <div class="p-5">
                        <h3 class="text-[15px] font-semibold text-[#3C2F23] dark:text-white leading-tight">${spa.name}</h3>
                        ${spa.rating_avg ? `
                        <div class="flex items-center gap-1 mt-1">
                            <i class="fa-solid fa-star text-[#D2A85B] text-xs"></i>
                            <span class="text-xs font-semibold text-[#3C2F23] dark:text-white">${spa.rating_avg}</span>
                            <span class="text-xs text-gray-400">(${spa.rating_count})</span>
                        </div>` : ''}
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">${addrSummary}</p>
                    </div>
                </button>`;
        }).join('');

        grid.querySelectorAll('[data-open-spa-modal]').forEach(btn => {
            btn.addEventListener('click', () => {
                try {
                    openSpaModal(JSON.parse(btn.getAttribute('data-spa')));
                } catch (e) { console.error(e); }
            });
        });

        section.classList.remove('hidden');

    } catch (err) {
        console.warn('Nearby spas unavailable:', err);
    }
}

document.addEventListener('DOMContentLoaded', loadNearbySpas);

document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('myAppointmentsBadge')) {
        loadAppointments();
    }
});

// =====================================================
// MAP (For Profile Modal) — Cavite-bounded, draggable pin, with feedback so
// a stray/accidental tap is visible and undoable instead of silently
// overwriting whatever address the customer had typed.
// =====================================================

// Places/moves the pin + syncs the hidden lat/lng inputs. Pure mechanical
// placement — doesn't touch the address text field and doesn't toast.
// Returns false (leaving everything untouched) if the point falls outside
// Cavite.
function placeProfilePin(lat, lng) {
    if (!window.profileMap) return false;

    const withinCavite = lat >= PROFILE_CAVITE_LAT_MIN && lat <= PROFILE_CAVITE_LAT_MAX
        && lng >= PROFILE_CAVITE_LNG_MIN && lng <= PROFILE_CAVITE_LNG_MAX;
    if (!withinCavite) return false;

    if (profileMapMarker) {
        window.profileMap.removeLayer(profileMapMarker);
    }
    profileMapMarker = L.marker([lat, lng], { draggable: true }).addTo(window.profileMap);
    profileMapMarker.on('dragend', () => updateProfilePin(profileMapMarker.getLatLng()));

    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    if (latInput) latInput.value = lat;
    if (lngInput) lngInput.value = lng;

    return true;
}

// Click/drag path: place the pin, then reverse-geocode to fill the address
// field, with a toast so the change is visible rather than silent. On an
// out-of-bounds tap, nothing is overwritten and an existing marker snaps
// back to its last valid position.
function updateProfilePin(latlng) {
    const placed = placeProfilePin(latlng.lat, latlng.lng);

    if (!placed) {
        showSpaToast('That spot is outside Cavite — pin somewhere within the province.', 'error');
        if (profileMapMarker) {
            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');
            const revertLat = latInput && latInput.value ? parseFloat(latInput.value) : null;
            const revertLng = lngInput && lngInput.value ? parseFloat(lngInput.value) : null;
            if (revertLat && revertLng) profileMapMarker.setLatLng([revertLat, revertLng]);
        }
        return;
    }

    fetch(`https://nominatim.openstreetmap.org/reverse?lat=${latlng.lat}&lon=${latlng.lng}&format=json&addressdetails=1`)
        .then(res => res.json())
        .then(data => {
            const addressField = document.getElementById('address');
            if (addressField && data.display_name) addressField.value = data.display_name;
        })
        .catch(err => console.warn('Reverse geocoding failed:', err))
        .finally(() => {
            showSpaToast('Pin updated — hit "Save Changes" below to keep it.', 'success');
        });
}

// Restores the address + pin to what was on file when the modal opened —
// a one-tap undo for a stray tap or an experiment that didn't work out.
function resetProfileLocation() {
    const addressField = document.getElementById('address');
    const latInput      = document.getElementById('latitude');
    const lngInput       = document.getElementById('longitude');

    if (addressField) addressField.value = profileSavedLocation.address || '';
    if (latInput)      latInput.value    = profileSavedLocation.lat ?? '';
    if (lngInput)       lngInput.value    = profileSavedLocation.lng ?? '';

    const suggestions = document.getElementById('addressSuggestions');
    if (suggestions) suggestions.classList.add('hidden');

    if (!window.profileMap) return;

    if (profileMapMarker) {
        window.profileMap.removeLayer(profileMapMarker);
        profileMapMarker = null;
    }

    if (profileSavedLocation.lat && profileSavedLocation.lng) {
        window.profileMap.setView([profileSavedLocation.lat, profileSavedLocation.lng], 15);
        profileMapMarker = L.marker([profileSavedLocation.lat, profileSavedLocation.lng], { draggable: true }).addTo(window.profileMap);
        profileMapMarker.on('dragend', () => updateProfilePin(profileMapMarker.getLatLng()));
    }

    showSpaToast('Location reset to your saved address.', 'success');
}

// Wait for DOM to be fully loaded before initializing map
document.addEventListener('DOMContentLoaded', function() {
    const mapContainer = document.getElementById('map');

    if (mapContainer) {
        const caviteBounds = L.latLngBounds(
            [PROFILE_CAVITE_LAT_MIN, PROFILE_CAVITE_LNG_MIN],
            [PROFILE_CAVITE_LAT_MAX, PROFILE_CAVITE_LNG_MAX]
        );

        // Initialize map only if container exists
        window.profileMap = L.map('map', {
            maxBounds: caviteBounds,
            maxBoundsViscosity: 1.0,
        }).setView([14.2456, 120.8786], 11); // Cavite center — matches the branch-profile map default

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(window.profileMap);

        window.profileMap.on('click', (e) => updateProfilePin(e.latlng));

        setupProfileAddressAutocomplete();
    } else {
        console.log('Map container not found on this page - skipping map initialization');
    }
});

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('profileLocationReset')?.addEventListener('click', function (e) {
        e.preventDefault();
        resetProfileLocation();
    });
});

// =====================================================
// PROFILE ADDRESS AUTOCOMPLETE — search-first path, so most customers never
// need to touch the map directly; the map becomes a fine-tune/confirm step
// rather than the primary way to set a location.
// =====================================================
let profileAddressGeocodeTimeout = null;
let profileAddressSuggestions    = [];
let profileActiveSuggestionIndex = -1;

function fetchProfileAddressSuggestions(query) {
    const dropdown = document.getElementById('addressSuggestions');
    if (!dropdown) return;

    if (!query || query.length < 3) {
        dropdown.classList.add('hidden');
        profileAddressSuggestions = [];
        return;
    }

    const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query + ', Cavite, Philippines')}&format=json&limit=5&viewbox=${PROFILE_CAVITE_VIEWBOX}&bounded=1&addressdetails=1`;

    fetch(url)
        .then(res => res.json())
        .then(data => {
            profileAddressSuggestions = data || [];
            renderProfileAddressSuggestions();
        })
        .catch(err => {
            console.warn('Address search failed:', err);
            dropdown.classList.add('hidden');
        });
}

function renderProfileAddressSuggestions() {
    const dropdown = document.getElementById('addressSuggestions');
    if (!dropdown) return;

    profileActiveSuggestionIndex = -1;

    if (profileAddressSuggestions.length === 0) {
        dropdown.innerHTML = `<div class="px-4 py-2.5 text-sm text-gray-400">No matches in Cavite — try a broader search, or pin it on the map below.</div>`;
        dropdown.classList.remove('hidden');
        return;
    }

    dropdown.innerHTML = profileAddressSuggestions.map((item, index) => `
        <button type="button"
                data-index="${index}"
                class="profile-address-suggestion-item flex items-start w-full gap-2 px-4 py-2.5 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-600 border-b border-gray-100 dark:border-gray-600 last:border-0">
            <i class="fa-solid fa-location-dot text-[#8B7355] text-xs mt-1 flex-shrink-0"></i>
            <span class="text-gray-700 dark:text-gray-200">${escapeHtml(item.display_name)}</span>
        </button>
    `).join('');

    dropdown.classList.remove('hidden');

    dropdown.querySelectorAll('.profile-address-suggestion-item').forEach(btn => {
        btn.addEventListener('click', () => {
            selectProfileAddressSuggestion(parseInt(btn.dataset.index, 10));
        });
    });
}

function selectProfileAddressSuggestion(index) {
    const item = profileAddressSuggestions[index];
    if (!item) return;

    const lat = parseFloat(item.lat);
    const lng = parseFloat(item.lon);

    const addressField = document.getElementById('address');
    if (addressField) addressField.value = item.display_name;

    const placed = placeProfilePin(lat, lng);
    if (placed && window.profileMap) {
        window.profileMap.setView([lat, lng], 16);
    } else {
        showSpaToast('That result is outside Cavite — try a more specific search or pin it manually.', 'error');
    }

    const dropdown = document.getElementById('addressSuggestions');
    if (dropdown) dropdown.classList.add('hidden');
    profileAddressSuggestions = [];
}

function highlightProfileSuggestion(items) {
    items.forEach((item, i) => {
        item.classList.toggle('bg-gray-100', i === profileActiveSuggestionIndex);
        item.classList.toggle('dark:bg-gray-600', i === profileActiveSuggestionIndex);
    });
    items[profileActiveSuggestionIndex]?.scrollIntoView({ block: 'nearest' });
}

function setupProfileAddressAutocomplete() {
    const addressInput = document.getElementById('address');
    const dropdown      = document.getElementById('addressSuggestions');
    if (!addressInput || !dropdown) return;

    addressInput.addEventListener('input', function () {
        clearTimeout(profileAddressGeocodeTimeout);
        const query = this.value.trim();
        profileAddressGeocodeTimeout = setTimeout(() => fetchProfileAddressSuggestions(query), 500);
    });

    addressInput.addEventListener('keydown', function (e) {
        const items = dropdown.querySelectorAll('.profile-address-suggestion-item');
        if (items.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            profileActiveSuggestionIndex = Math.min(profileActiveSuggestionIndex + 1, items.length - 1);
            highlightProfileSuggestion(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            profileActiveSuggestionIndex = Math.max(profileActiveSuggestionIndex - 1, 0);
            highlightProfileSuggestion(items);
        } else if (e.key === 'Enter') {
            if (profileActiveSuggestionIndex >= 0) {
                e.preventDefault();
                selectProfileAddressSuggestion(profileActiveSuggestionIndex);
            }
        } else if (e.key === 'Escape') {
            dropdown.classList.add('hidden');
        }
    });

    document.addEventListener('click', function (e) {
        if (!addressInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
}

// =====================================================
// KEYBOARD: Escape closes all modals
// =====================================================
window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (!document.getElementById('logoutModal')?.classList.contains('hidden'))          closeLogoutModal();
        if (!document.getElementById('rescheduleModal')?.classList.contains('hidden'))      closeRescheduleModal();
        if (!document.getElementById('bookingDetailsModal')?.classList.contains('hidden'))  closeBookingDetailsModal();
        if (!document.getElementById('termsModal')?.classList.contains('hidden'))           closeTermsModal();
        if (!spaModal?.classList.contains('hidden'))                                        closeSpaModal();
        if (!bookingModal?.classList.contains('hidden'))                                    closeBookingModal();
        if (!document.getElementById('businessInfoModal')?.classList.contains('hidden'))    closeBusinessInfo();
        if (!document.getElementById('applicationModal')?.classList.contains('hidden'))     closeApplicationModal();
        if (!document.getElementById('reviewsModal')?.classList.contains('hidden'))         closeReviewsModal();
    }
});

// =====================================================
// EXPOSE GLOBALS
// =====================================================
window.openAppointmentsModal    = openAppointmentsModal;
window.closeAppointmentsModal   = closeAppointmentsModal;
window.openScheduleModal        = openScheduleModal;
window.closeScheduleModal       = closeScheduleModal;
window.switchTab                = switchTab;
window.selectDay                = selectDay;
window.changeMonth              = changeMonth;
window.openProfileModal         = openProfileModal;
window.closeProfileModal        = closeProfileModal;
window.closeProfileDropdown     = closeProfileDropdown;
window.toggleEmail              = toggleEmail;
window.showSpaToast             = showSpaToast;
window.openTermsModal           = openTermsModal;
window.closeTermsModal          = closeTermsModal;
window.openBookingDetailsModal  = openBookingDetailsModal;
window.closeBookingDetailsModal = closeBookingDetailsModal;
window.openRescheduleModal      = openRescheduleModal;
window.closeRescheduleModal     = closeRescheduleModal;
window.submitRescheduleRequest  = submitRescheduleRequest;
window.openRatingModal          = openRatingModal;
window.closeRatingModal         = closeRatingModal;
window.openReviewsModal         = openReviewsModal;
window.openReviewsModalFromSpa  = openReviewsModalFromSpa;
window.closeReviewsModal        = closeReviewsModal;
window.selectReviewFilter       = selectReviewFilter;
window.submitRating             = submitRating;
window.setRating                = setRating;
window.setSpaRating             = setSpaRating;
window._dayBookingMap           = _dayBookingMap;
window._appointmentMap          = _appointmentMap;
window.openApplicationModal     = openApplicationModal;
window.closeApplicationModal    = closeApplicationModal;
window.openApplicationModal     = openApplicationModal;
window.closeApplicationModal    = closeApplicationModal;
window.hideResumeUploadStatus   = hideResumeUploadStatus;
window.clearResumeUpload        = clearResumeUpload;
window.showResumeUploadStatus   = showResumeUploadStatus;
window.handleResumeFileSelect   = handleResumeFileSelect;
window.formatFileSize           = formatFileSize;
window.openLogoutModal          = openLogoutModal;
window.closeLogoutModal         = closeLogoutModal;
window.toggleScheduleView       = toggleScheduleView;

// =====================================================
// TOAST
// =====================================================
function showSpaToast(message, type = 'success') {
    const isSuccess = type === 'success';
    // Toastify renders via inline styles injected at call time, so a CSS
    // media query can't reach it - we check prefers-color-scheme directly
    // in JS instead, matching the same gray-800/900 palette used elsewhere.
    const isDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;

    const palette = isDark
        ? {
            iconBg:     isSuccess ? 'rgba(22,163,74,0.15)' : 'rgba(220,38,38,0.15)',
            iconColor:  isSuccess ? '#4ade80' : '#f87171',
            labelColor: isSuccess ? '#4ade80' : '#f87171',
            textColor:  '#e5e7eb',
            background: '#1f2937', // gray-800
            border:     isSuccess ? '1px solid rgba(74,222,128,0.35)' : '1px solid rgba(248,113,113,0.35)',
            borderLeft: isSuccess ? '4px solid #22c55e' : '4px solid #ef4444',
            shadow:     '0 10px 30px rgba(0,0,0,0.45)',
        }
        : {
            iconBg:     isSuccess ? '#f0fdf4' : '#fef2f2',
            iconColor:  isSuccess ? '#16a34a' : '#dc2626',
            labelColor: isSuccess ? '#15803d' : '#b91c1c',
            textColor:  '#374151',
            background: '#ffffff',
            border:     isSuccess ? '1px solid #bbf7d0' : '1px solid #fecaca',
            borderLeft: isSuccess ? '4px solid #16a34a' : '4px solid #dc2626',
            shadow:     '0 10px 30px rgba(0,0,0,0.08)',
        };

    Toastify({
        text: `
            <div style="display:flex;align-items:center;gap:12px;padding:2px 0;">
                <div style="width:36px;height:36px;border-radius:50%;background:${palette.iconBg};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="${isSuccess ? 'fa-solid fa-spa' : 'fa-solid fa-circle-xmark'}" style="color:${palette.iconColor};font-size:15px;"></i>
                </div>
                <div style="display:flex;flex-direction:column;gap:2px;">
                    <span style="font-size:11px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:${palette.labelColor};">${isSuccess ? 'Success' : 'Error'}</span>
                    <span style="font-size:13px;color:${palette.textColor};font-weight:400;line-height:1.4;">${message}</span>
                </div>
            </div>`,
        duration: 3500,
        gravity: 'top',
        position: 'right',
        close: false,
        escapeMarkup: false,
        style: {
            background: palette.background,
            border: palette.border,
            borderLeft: palette.borderLeft,
            borderRadius: '10px',
            minWidth: '300px',
            maxWidth: '360px',
            padding: '14px 18px',
            boxShadow: palette.shadow,
        }
    }).showToast();
}

// =====================================================
// RATING MODAL
// =====================================================
function openRatingModal(bookingId, therapistName, spaName, branchName, branchLocation) {
    console.log('openRatingModal called', { bookingId, therapistName, spaName, branchName, branchLocation });

    const ratingBookingId = document.getElementById('ratingBookingId');
    const ratingTherapistName = document.getElementById('ratingTherapistName');
    const ratingBranchLocation = document.getElementById('ratingBranchLocation');
    const ratingSpaName = document.getElementById('ratingSpaName');
    const ratingSpaBranchLocation = document.getElementById('ratingSpaBranchLocation');

    if (ratingBookingId) ratingBookingId.value = bookingId;
    if (ratingTherapistName) ratingTherapistName.innerText = therapistName;
    if (ratingSpaName) ratingSpaName.innerText = spaName;

    const locationText = branchLocation || branchName || 'Branch location unavailable';
    if (ratingBranchLocation) ratingBranchLocation.innerText = locationText;
    if (ratingSpaBranchLocation) ratingSpaBranchLocation.innerText = locationText;

    resetStars();
    resetSpaStars();

    const ratingComment = document.getElementById('ratingComment');
    const ratingFeedback = document.getElementById('ratingFeedback');
    const spaComment = document.getElementById('spaComment');
    if (ratingComment) ratingComment.value = '';
    if (ratingFeedback) ratingFeedback.value = '';
    if (spaComment) spaComment.value = '';

    const commentCount = document.getElementById('ratingCommentCount');
    const feedbackCount = document.getElementById('ratingFeedbackCount');
    const spaCommentCount = document.getElementById('spaCommentCount');
    if (commentCount) commentCount.textContent = '0';
    if (feedbackCount) feedbackCount.textContent = '0';
    if (spaCommentCount) spaCommentCount.textContent = '0';

    const submitBtn = document.getElementById('ratingSubmitBtn');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        submitBtn.innerHTML = '<i class="mr-2 fa-solid fa-paper-plane"></i> Submit Rating';
    }

    const modal = document.getElementById('ratingModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        console.log('Rating modal opened');
    } else {
        console.error('Rating modal not found');
    }
}

function closeRatingModal() {
    const modal = document.getElementById('ratingModal');
    if (modal) modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    resetStars();
    resetSpaStars();
}

function resetStars() {
    for (let i = 1; i <= 5; i++) {
        const star = document.getElementById(`star-${i}`);
        if (!star) continue;
        star.classList.remove('text-yellow-400');
        star.classList.add('text-gray-300', 'dark:text-gray-600');
    }
    const selectedRating = document.getElementById('selectedRating');
    if (selectedRating) selectedRating.value = 0;
}

function resetSpaStars() {
    for (let i = 1; i <= 5; i++) {
        const star = document.getElementById(`spa-star-${i}`);
        if (!star) continue;
        star.classList.remove('text-yellow-400');
        star.classList.add('text-gray-300', 'dark:text-gray-600');
    }
    const selectedSpaRating = document.getElementById('selectedSpaRating');
    if (selectedSpaRating) selectedSpaRating.value = 0;
}

function setRating(rating) {
    const selectedRating = document.getElementById('selectedRating');
    if (selectedRating) selectedRating.value = rating;

    for (let i = 1; i <= 5; i++) {
        const star = document.getElementById(`star-${i}`);
        if (!star) continue;
        if (i <= rating) {
            star.classList.remove('text-gray-300', 'dark:text-gray-600');
            star.classList.add('text-yellow-400');
        } else {
            star.classList.remove('text-yellow-400');
            star.classList.add('text-gray-300', 'dark:text-gray-600');
        }
    }
}

function setSpaRating(rating) {
    const selectedSpaRating = document.getElementById('selectedSpaRating');
    if (selectedSpaRating) selectedSpaRating.value = rating;

    for (let i = 1; i <= 5; i++) {
        const star = document.getElementById(`spa-star-${i}`);
        if (!star) continue;
        if (i <= rating) {
            star.classList.remove('text-gray-300', 'dark:text-gray-600');
            star.classList.add('text-yellow-400');
        } else {
            star.classList.remove('text-yellow-400');
            star.classList.add('text-gray-300', 'dark:text-gray-600');
        }
    }
}

async function submitRating() {
    const bookingId  = document.getElementById('ratingBookingId')?.value;
    const rating     = document.getElementById('selectedRating')?.value;
    const comment    = document.getElementById('ratingComment')?.value || '';
    const feedback   = document.getElementById('ratingFeedback')?.value || '';
    const spaRating  = document.getElementById('selectedSpaRating')?.value;
    const spaComment = document.getElementById('spaComment')?.value || '';

    if (!spaRating || spaRating == 0) {
        showSpaToast('Please rate the spa', 'error');
        return;
    }

    if (!rating || rating == 0) {
        showSpaToast('Please rate the therapist', 'error');
        return;
    }

    const submitBtn = document.getElementById('ratingSubmitBtn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Submitting...';
    }

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        const response = await fetch('/ratings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                booking_id:   bookingId,
                rating:       parseInt(rating),
                comment:      comment,
                feedback:     feedback,
                spa_rating:   parseInt(spaRating),
                spa_comment:  spaComment
            })
        });

        const data = await response.json();

        if (response.ok && data.success) {
            showSpaToast('Thank you for your feedback!', 'success');
            closeRatingModal();
            loadAppointments();
        } else {
            showSpaToast(data.message || 'Failed to submit rating', 'error');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="mr-2 fa-solid fa-paper-plane"></i> Submit Rating';
            }
        }
    } catch (error) {
        console.error('Error submitting rating:', error);
        showSpaToast('Network error. Please try again.', 'error');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="mr-2 fa-solid fa-paper-plane"></i> Submit Rating';
        }
    }
}

// Add event listeners for rating inputs
const ratingComment = document.getElementById('ratingComment');
const ratingFeedback = document.getElementById('ratingFeedback');

if (ratingComment) {
    ratingComment.addEventListener('input', function () {
        const el = document.getElementById('ratingCommentCount');
        if (el) el.textContent = this.value.length;
    });
}

if (ratingFeedback) {
    ratingFeedback.addEventListener('input', function () {
        const el = document.getElementById('ratingFeedbackCount');
        if (el) el.textContent = this.value.length;
    });
}

// =====================================================
// RATE REMINDER (unrated completed bookings)
// =====================================================
const RATE_REMINDER_STORAGE_KEY = 'levictas_rate_reminder_dismissed_date';
let _reminderBookingMap = {};

function shouldShowRateReminderToday() {
    try {
        const dismissedDate = localStorage.getItem(RATE_REMINDER_STORAGE_KEY);
        return dismissedDate !== getTodayLocal();
    } catch (e) {
        // localStorage unavailable (privacy mode, etc.) — default to showing it.
        return true;
    }
}

function markRateReminderDismissedToday() {
    try {
        localStorage.setItem(RATE_REMINDER_STORAGE_KEY, getTodayLocal());
    } catch (e) {
        // Ignore — worst case it reminds again this session.
    }
}

function checkUnratedBookings(bookings) {
    if (!shouldShowRateReminderToday()) return;

    const unrated = bookings.filter(b => b.status === 'completed' && !b.has_rating);
    if (!unrated.length) return;

    showRateReminderModal(unrated);
}

function showRateReminderModal(unrated) {
    const modal   = document.getElementById('rateReminderModal');
    const content = document.getElementById('rateReminderContent');
    if (!modal || !content) return;

    Object.keys(_reminderBookingMap).forEach(k => delete _reminderBookingMap[k]);

    content.innerHTML = `
        <p class="text-sm text-gray-600 dark:text-gray-400">
            You have ${unrated.length} completed ${unrated.length === 1 ? 'appointment' : 'appointments'} waiting for your feedback.
        </p>
        ${unrated.map((b, i) => {
            _reminderBookingMap[i] = b;
            return `
            <div class="p-4 border border-black/5 dark:border-white/10 rounded-2xl bg-[#F6EFE6]/50 dark:bg-gray-700/40 ring-1 ring-black/5 dark:ring-white/10">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-[#3C2F23] dark:text-white">${escapeHtml(b.spa_name)}</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">${escapeHtml(b.branch_location ?? b.branch_name)} • ${escapeHtml(b.treatment)}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            <i class="fa-solid fa-user-nurse text-[#8B7355] dark:text-[#C4A97D]"></i> ${escapeHtml(b.therapist)}
                            &nbsp;•&nbsp; <i class="fa-solid fa-calendar text-[#8B7355] dark:text-[#C4A97D]"></i> ${b.date}
                        </p>
                    </div>
                    <button type="button"
                        onclick="rateFromReminder(${i})"
                        class="flex-shrink-0 px-4 py-2 text-xs font-semibold text-white transition rounded-xl bg-[#8B7355] hover:bg-[#6F5430]">
                        <i class="mr-1 fa-solid fa-star"></i> Rate
                    </button>
                </div>
            </div>`;
        }).join('')}
    `;

    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function rateFromReminder(index) {
    const b = _reminderBookingMap[index];
    if (!b) return;
    closeRateReminderModalOnly();
    openRatingModal(b.id, b.therapist, b.spa_name, b.branch_name, b.branch_location);
}

// Closes the reminder without marking it "dismissed" — used when the customer chose to rate instead of skipping.
function closeRateReminderModalOnly() {
    document.getElementById('rateReminderModal')?.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

function dismissRateReminder() {
    markRateReminderDismissedToday();
    closeRateReminderModalOnly();
}

window.dismissRateReminder = dismissRateReminder;
window.rateFromReminder    = rateFromReminder;
