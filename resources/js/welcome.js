// Navbar behaviour (mobile menu, scroll glass, profile dropdown, logout
// window) is shared with the public pages (landing footer Phase 1).
import './landing-nav.js';

let selectedSpa = null;
let spaMap      = null;


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

// Customer profile photo (D13): checks the size here so a too-large photo
// doesn't upload for nothing, then submits #customerPhotoForm.
function submitCustomerPhoto(input) {
    const file = input.files && input.files[0];
    if (!file) return;

    const maxMb = parseInt(input.dataset.maxMb || '5', 10);
    if (file.size > maxMb * 1024 * 1024) {
        input.value = '';
        if (typeof window.showSpaToast === 'function') {
            window.showSpaToast(`The photo must be ${maxMb} MB or smaller.`, 'error');
        }
        return;
    }

    const label = document.getElementById('customerPhotoLabel');
    if (label) label.textContent = 'Uploading…';
    input.closest('label')?.classList.add('opacity-60', 'pointer-events-none');
    input.form.submit();
}

// =====================================================
// SPA MODAL
// =====================================================
/*
  Spa modal rework (Nov 2026): sheet on phones, dialog from sm, same contract as
  bookingDetailsModal — focus goes to the title on open and back to the trigger
  on close, Tab stays inside, Escape is in the shared top-most-wins handler.
  Every class toggled here is written out in welcome.blade.php so Tailwind
  generates it (welcome.js is not in Tailwind's content globs).
*/
const spaModal     = document.getElementById('spaModal');
const closeSpaBtns = document.querySelectorAll('[data-close-spa-modal]');
let photos         = [];
let photoIndex     = 0;
let spaReturnFocus = null;

// Labels and icons for the default amenities. The keys and labels mirror the
// $defaultAmenities list in branches/edit.blade.php; keep the two in sync.
// Custom amenities (typed by the spa) fall back to a prettified key and a
// generic icon.
const AMENITY_META = {
    aircon:        ['Air Conditioning', 'fa-snowflake'],
    private_rooms: ['Private Rooms',    'fa-door-closed'],
    shower:        ['Shower',           'fa-shower'],
    parking:       ['Parking',          'fa-square-parking'],
    wifi:          ['WiFi',             'fa-wifi'],
    locker:        ['Locker',           'fa-lock'],
    pet_friendly:  ['Pet Friendly',     'fa-paw'],
    sauna:         ['Sauna',            'fa-hot-tub-person'],
};

function amenityMeta(key) {
    const raw = String(key ?? '');
    if (AMENITY_META[raw]) return AMENITY_META[raw];
    const label = raw.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    return [label, 'fa-circle-check'];
}

// D11: "Business verified" badge and "Owned by …" under the photos.
function renderSpaOwner(spaData) {
    const badge  = document.getElementById('spaModalBusinessVerified');
    const owner  = document.getElementById('spaModalOwner');
    const nameEl = document.getElementById('spaModalOwnerName');
    if (!badge || !owner || !nameEl) return;

    const verified = spaData.business_verified === true;
    const name     = (spaData.owner_name || '').trim();

    badge.classList.toggle('hidden', !verified);
    badge.classList.toggle('inline-flex', verified);
    nameEl.textContent = name;
    owner.classList.toggle('hidden', name === '');
}

function getAddressSummary(fullAddress) {
    if (!fullAddress) return 'Location unavailable';
    const parts = fullAddress.split(',').map(p => p.trim());
    if (parts.length < 3) return fullAddress;
    const withoutZipCountry = parts.slice(0, parts.length - 2);
    return withoutZipCountry.slice(-3).join(', ');
}

function spaHasCoords(spaData) {
    return Number.isFinite(Number(spaData?.lat)) && Number.isFinite(Number(spaData?.lng))
        && spaData.lat !== null && spaData.lng !== null && spaData.lat !== '' && spaData.lng !== '';
}

// Google Maps URLs API: opens directions in the app on phones, the site elsewhere.
function spaDirectionsUrl(spaData) {
    return `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(`${spaData.lat},${spaData.lng}`)}`;
}

// Popup text through a text node: Leaflet treats a string popup as HTML, and
// the spa name is typed by the business.
function spaMapPopup(name) {
    const el = document.createElement('span');
    el.textContent = name || 'Spa';
    return el;
}

function renderSpaAmenities(spaData) {
    const container = document.getElementById('spaModalAmenities');
    if (!container) return;

    const amenities = Array.isArray(spaData.amenities) ? spaData.amenities.filter(Boolean) : [];
    if (!amenities.length) {
        container.innerHTML = `<p class="text-sm text-gray-500 dark:text-gray-400">No amenities listed yet.</p>`;
        return;
    }

    // Custom amenities are free text from the spa, so the label is escaped.
    container.innerHTML = `
        <ul class="grid grid-cols-2 gap-2">
            ${amenities.map(a => {
                const [label, icon] = amenityMeta(a);
                return `
                    <li class="amenity-chip">
                        <span class="amenity-chip-icon"><i class="fa-solid ${icon}" aria-hidden="true"></i></span>
                        <span class="amenity-chip-label">${escapeHtml(label)}</span>
                    </li>`;
            }).join('')}
        </ul>`;
}

function renderSpaMapPreview(spaData) {
    const wrap  = document.getElementById('spaModalMapWrap');
    const elMap = document.getElementById('spaModalMap');
    const dir   = document.getElementById('spaModalDirections');

    if (spaMap) { spaMap.remove(); spaMap = null; }

    const hasCoords = spaHasCoords(spaData);
    wrap?.classList.toggle('hidden', !hasCoords);
    if (!hasCoords || !elMap) return;

    if (dir) dir.href = spaDirectionsUrl(spaData);

    // Wait for the sheet's open animation so Leaflet measures the real size.
    setTimeout(() => {
        if (spaModal.classList.contains('hidden') || selectedSpa !== spaData) return;
        const latLng = [Number(spaData.lat), Number(spaData.lng)];
        // Static preview: it lives inside a scrolling sheet, so dragging, wheel
        // and tap zoom are off and the Expand button covers it.
        spaMap = L.map(elMap, {
            dragging: false,
            touchZoom: false,
            scrollWheelZoom: false,
            doubleClickZoom: false,
            boxZoom: false,
            keyboard: false,
            zoomControl: false,
        }).setView(latLng, 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19, attribution: '&copy; OpenStreetMap'
        }).addTo(spaMap);
        L.marker(latLng).addTo(spaMap);
        spaMap.invalidateSize();
    }, 300);
}

function openSpaModal(spaData) {
    selectedSpa = spaData;
    // chat-widget.js: which spa the Message button talks to.
    document.dispatchEvent(new CustomEvent('levictas:spa-opened', { detail: spaData }));
    if (spaModal.classList.contains('hidden')) spaReturnFocus = document.activeElement;

    document.getElementById('spaModalName').textContent    = spaData.name    ?? 'Spa';
    document.getElementById('spaModalName').title          = spaData.name    ?? '';
    document.getElementById('spaModalTag').textContent     = spaData.tag     ?? 'Featured Spa';
    renderSpaOwner(spaData);
    document.getElementById('spaModalDesc').textContent    = spaData.desc    || 'This spa hasn\'t added a description yet.';
    document.getElementById('spaModalPhone').textContent   = spaData.phone   || 'No contact info';
    document.getElementById('spaModalAddress').textContent = spaData.address || 'Address unavailable';
    document.getElementById('spaModalAddressSummary').textContent = getAddressSummary(spaData.address);

    const priceText = spaData.price_note
        ? (spaData.has_promo ? `Starts at ₱${spaData.price_note} — ${spaData.promo_label} running` : `Starts at ₱${spaData.price_note}`)
        : 'Prices vary per treatment';
    document.getElementById('spaModalPrice').textContent = priceText;
    const footerPrice = document.getElementById('spaModalFooterPrice');
    if (footerPrice) {
        footerPrice.textContent = spaData.price_note ? `From ₱${spaData.price_note}` : 'Varies per treatment';
        footerPrice.title = priceText;
    }

    // Online reservation gate. The server sends can_book_online per spa; a
    // missing flag counts as allowed so older payloads keep working. One
    // attribute drives the Reserve button, the Call button and the note (landing.css).
    const canBookOnline = spaData.can_book_online !== false;
    spaModal.dataset.canBook = canBookOnline ? 'true' : 'false';

    // "Call spa" stands in for Reserve in the phone footer when online booking
    // is off, but only when there's a number to call.
    const callBtn = document.getElementById('spaModalCallBtn');
    if (callBtn) {
        const tel = String(spaData.phone ?? '').replace(/[^\d+]/g, '');
        const callable = tel.replace(/\D/g, '').length >= 7;
        callBtn.href = callable ? `tel:${tel}` : '#';
        callBtn.classList.toggle('hidden', !callable);
        callBtn.classList.toggle('inline-flex', callable);
    }

    const ratingBlock = document.getElementById('spaModalRating');
    const ratingValue = document.getElementById('spaModalRatingValue');
    const ratingCount = document.getElementById('spaModalRatingCount');
    if (ratingBlock && ratingValue && ratingCount) {
        const hasRating = !!spaData.rating_avg;
        if (hasRating) {
            const n = Number(spaData.rating_count) || 0;
            ratingValue.textContent = spaData.rating_avg;
            ratingCount.textContent = `· ${n} ${n === 1 ? 'review' : 'reviews'}`;
            ratingBlock.setAttribute('aria-label', `Rated ${spaData.rating_avg} out of 5 from ${n} ${n === 1 ? 'review' : 'reviews'}. See reviews`);
        }
        ratingBlock.classList.toggle('hidden', !hasRating);
        ratingBlock.classList.toggle('inline-flex', hasRating);
    }

    const hiringBlock = document.getElementById('spaModalHiring');
    const hiringNote  = document.getElementById('spaModalHiringNote');
    if (hiringBlock) {
        hiringBlock.classList.toggle('hidden', !spaData.is_hiring);
        if (spaData.is_hiring && hiringNote) {
            hiringNote.textContent = spaData.hiring_note || 'This branch is currently accepting applications.';
        }
        renderSpaModalOpenings(spaData);
    }

    renderSpaAmenities(spaData);

    photos = normalizeSpaPhotos(spaData.photos);
    renderSpaPhotoGrid(photos, spaData.name ?? 'Spa');

    const body = document.getElementById('spaBody');
    if (body) body.scrollTop = 0;

    spaModal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    requestAnimationFrame(() => document.getElementById('spaModalName')?.focus());

    renderSpaMapPreview(spaData);
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
    releaseBodyUnlessSpaOpen();
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
        listEl.innerHTML = `<p class="text-sm italic text-gray-400 dark:text-gray-500">
            No reviews${currentReviewFilter
                ? ` with ${currentReviewFilter} star${currentReviewFilter > 1 ? 's' : ''}`
                : ''} yet.
        </p>`;
        return;
    }

    listEl.innerHTML = filtered.map(r => `
        <div class="p-3 rounded-xl bg-[#F6EFE6]/50 dark:bg-gray-700/40 ring-1 ring-black/5 dark:ring-white/10">
            <div class="flex items-center justify-between gap-3">
                <span class="text-sm font-semibold text-[#3C2F23] dark:text-white">
                    ${escapeHtml(r.name || 'Anonymous')}
                </span>

                <span class="text-xs text-gray-400 dark:text-gray-500">
                    ${escapeHtml(r.date ?? '')}
                </span>
            </div>

            <div class="flex items-center gap-0.5 mt-1">
                ${renderStars(r.rating)}
            </div>

            ${r.comment ? `
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    ${escapeHtml(r.comment)}
                </p>
            ` : ''}

            ${Array.isArray(r.photos) && r.photos.length ? `
                <div class="flex flex-wrap gap-2 mt-3">
                    ${r.photos.map((url, index) => `
                        <button
                            type="button"
                            onclick="openReviewPhoto('${escapeHtml(url)}')"
                            class="relative overflow-hidden w-14 h-14 rounded-xl
                                   ring-1 ring-black/10 dark:ring-white/10
                                   transition hover:opacity-90
                                   focus-visible:outline-none
                                   focus-visible:ring-2
                                   focus-visible:ring-[#8B7355]"
                            aria-label="View review photo ${index + 1}"
                        >
                            <img
                                src="${escapeHtml(url)}"
                                alt="Customer review photo ${index + 1}"
                                class="object-cover w-full h-full"
                                loading="lazy"
                            >
                        </button>
                    `).join('')}
                </div>
            ` : ''}
        </div>
    `).join('');
}

function openReviewPhoto(url) {
    photos = [{ url, caption: null }];
    openLightbox(0);
}

function closeSpaModal() {
    closeSpaMapFull(false);
    closeLightbox();
    spaModal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    if (spaMap) { spaMap.remove(); spaMap = null; }
    if (spaReturnFocus && document.contains(spaReturnFocus)) spaReturnFocus.focus();
    spaReturnFocus = null;
}

function isSpaModalOpen() {
    return !!spaModal && !spaModal.classList.contains('hidden');
}

// Overlays that open on top of the spa modal (reviews, booking, job
// application) keep the page locked while it is still open underneath.
function releaseBodyUnlessSpaOpen() {
    if (!isSpaModalOpen()) document.body.classList.remove('overflow-hidden');
}

// Tab stays inside whichever spa layer is on top. Leaflet's own controls are
// skipped, same as the profile modal's trap.
function trapTab(e, container) {
    if (e.key !== 'Tab' || !container) return;
    const focusable = [...container.querySelectorAll(
        'a[href]:not([href="#"]), button:not([disabled]), [tabindex="0"]'
    )].filter(el => el.offsetParent !== null && !el.closest('.leaflet-control-container'));
    if (!focusable.length) return;
    const first = focusable[0];
    const last  = focusable[focusable.length - 1];
    if (e.shiftKey && (document.activeElement === first || !container.contains(document.activeElement))) {
        e.preventDefault(); last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault(); first.focus();
    }
}

spaModal?.addEventListener('keydown', (e) => {
    if (isLightboxOpen() || isSpaMapFullOpen()) return; // they trap their own Tab
    trapTab(e, document.getElementById('spaPanel'));
});

// =====================================================
// SPA MODAL: FULLSCREEN MAP
// =====================================================
const spaMapFullEl     = document.getElementById('spaMapFull');
let spaFullMap         = null;
let spaMapFullReturnFocus = null;

function isSpaMapFullOpen() {
    return !!spaMapFullEl && !spaMapFullEl.classList.contains('hidden');
}

function openSpaMapFull() {
    if (!spaMapFullEl || !selectedSpa || !spaHasCoords(selectedSpa)) return;

    spaMapFullReturnFocus = document.activeElement;
    document.getElementById('spaMapFullTitle').textContent   = selectedSpa.name ?? 'Map';
    document.getElementById('spaMapFullAddress').textContent = selectedSpa.address ?? '';
    document.getElementById('spaMapFullDirections').href     = spaDirectionsUrl(selectedSpa);

    spaMapFullEl.classList.remove('hidden');
    requestAnimationFrame(() => document.getElementById('spaMapFullTitle')?.focus());

    // Built after the overlay is visible, so Leaflet measures the full size.
    // Body scroll is already locked by the spa modal underneath.
    requestAnimationFrame(() => {
        if (spaFullMap) { spaFullMap.remove(); spaFullMap = null; }
        const latLng = [Number(selectedSpa.lat), Number(selectedSpa.lng)];
        spaFullMap = L.map('spaMapFullCanvas').setView(latLng, 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19, attribution: '&copy; OpenStreetMap'
        }).addTo(spaFullMap);
        L.marker(latLng).addTo(spaFullMap).bindPopup(spaMapPopup(selectedSpa.name)).openPopup();
        spaFullMap.invalidateSize();
    });
}

function closeSpaMapFull(restoreFocus = true) {
    if (!isSpaMapFullOpen()) return;
    spaMapFullEl.classList.add('hidden');
    if (spaFullMap) { spaFullMap.remove(); spaFullMap = null; }
    if (restoreFocus && spaMapFullReturnFocus && document.contains(spaMapFullReturnFocus)) {
        spaMapFullReturnFocus.focus();
    }
    spaMapFullReturnFocus = null;
}

document.getElementById('spaModalMapExpand')?.addEventListener('click', openSpaMapFull);
document.getElementById('spaMapFullClose')?.addEventListener('click', () => closeSpaMapFull());
spaMapFullEl?.addEventListener('keydown', (e) => trapTab(e, spaMapFullEl));
window.addEventListener('resize', () => spaFullMap?.invalidateSize());

// =====================================================
// RATING PHOTOS (spa section)
// =====================================================
let spaRatingPhotoFiles = [];
const SPA_RATING_MAX_PHOTOS = 3;
const SPA_RATING_MAX_SIZE   = 5 * 1024 * 1024;

document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('spaRatingPhotoInput');
    if (!input) return;

    input.addEventListener('change', function () {
        const errorEl     = document.getElementById('spaRatingPhotoError');
        const errorTextEl = document.getElementById('spaRatingPhotoErrorText');
        errorEl?.classList.add('hidden');

        for (const file of Array.from(input.files)) {
            if (spaRatingPhotoFiles.length >= SPA_RATING_MAX_PHOTOS) {
                if (errorTextEl) errorTextEl.textContent = `You can upload up to ${SPA_RATING_MAX_PHOTOS} photos.`;
                errorEl?.classList.remove('hidden');
                break;
            }
            if (file.size > SPA_RATING_MAX_SIZE) {
                if (errorTextEl) errorTextEl.textContent = `${file.name} is over 5MB.`;
                errorEl?.classList.remove('hidden');
                continue;
            }
            spaRatingPhotoFiles.push(file);
        }

        input.value = '';
        renderSpaRatingPhotoPreview();
    });
});

function renderSpaRatingPhotoPreview() {
    const grid = document.getElementById('spaRatingPhotoPreview');
    if (!grid) return;
    grid.innerHTML = '';

    spaRatingPhotoFiles.forEach((file, index) => {
        const url  = URL.createObjectURL(file);
        const cell = document.createElement('div');
        cell.className = 'relative overflow-hidden rounded-lg aspect-square ring-1 ring-black/10 dark:ring-white/10';
        cell.innerHTML = `
            <img src="${url}" class="object-cover w-full h-full">
            <button type="button" onclick="removeSpaRatingPhoto(${index})"
                class="absolute top-1 right-1 flex items-center justify-center w-5 h-5 text-white bg-black/60 rounded-full hover:bg-black/80">
                <i class="fa-solid fa-xmark text-[10px]"></i>
            </button>`;
        grid.appendChild(cell);
    });
}

function removeSpaRatingPhoto(index) {
    spaRatingPhotoFiles.splice(index, 1);
    renderSpaRatingPhotoPreview();
}

function resetSpaRatingPhotos() {
    spaRatingPhotoFiles = [];
    renderSpaRatingPhotoPreview();
    document.getElementById('spaRatingPhotoError')?.classList.add('hidden');
}

// =====================================================
// SPA PHOTOS — payload, dynamic grid, lightbox
// =====================================================

function fallbackSpaImage() {
    return document.body.dataset.fallbackImage ?? '';
}

/*
  Spa and treatment photos that fail to load (a file missing from storage, a
  moved upload) show the same neutral placeholder as "no photo yet", instead
  of the browser's broken-image icon and alt text (UI rework Phase 4).
  One listener for every surface: spa cards, the spa window's photo grid and
  the booking treatment list. Image errors don't bubble, so it listens in the
  capture phase, and it re-checks images that already failed before this
  module ran.
*/
const PHOTO_FALLBACK_SCOPE = '[data-open-spa-modal], .spa-photo-cell, .svc-card-thumb, [data-photo-fallback]';

// Last resort: the same placeholder, built in, so it can't 404 even when
// public/images/placeholder-spa.svg is missing from a deploy.
const BUILT_IN_PHOTO_PLACEHOLDER = "data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 800 600%22 role=%22img%22 aria-label=%22No photo yet%22%3E%3Cstyle%3E .bg %7B fill: %23F6EFE6; %7D .mark %7B fill: %23C4A97D; %7D .soft %7B fill: %23C4A97D; opacity: .55; %7D .line %7B stroke: %23C4A97D; stroke-width: 10; stroke-linecap: round; fill: none; %7D %40media (prefers-color-scheme: dark) %7B .bg %7B fill: %23262B33; %7D .mark %7B fill: %238B7355; %7D .soft %7B fill: %238B7355; opacity: .6; %7D .line %7B stroke: %238B7355; %7D %7D %3C/style%3E%3Crect class=%22bg%22 width=%22800%22 height=%22600%22/%3E%3Cg transform=%22translate(400 300)%22%3E%3Cpath class=%22soft%22 d=%22M0 40 C -70 30 -118 -18 -112 -78 C -62 -66 -22 -30 0 40 Z%22/%3E%3Cpath class=%22soft%22 d=%22M0 40 C 70 30 118 -18 112 -78 C 62 -66 22 -30 0 40 Z%22/%3E%3Cpath class=%22mark%22 d=%22M0 40 C -40 0 -40 -62 0 -112 C 40 -62 40 0 0 40 Z%22/%3E%3Cpath class=%22line%22 d=%22M-92 70 C -40 86 40 86 92 70%22/%3E%3C/g%3E%3C/svg%3E";

function useFallbackPhoto(img) {
    const step = img.dataset.fellBack || '';
    if (step === 'built-in') return;                  // nothing left to try, so it can't loop
    const fallback = fallbackSpaImage();
    if (step === '' && fallback && img.getAttribute('src') !== fallback) {
        img.dataset.fellBack = 'file';
        img.src = fallback;
        return;
    }
    img.dataset.fellBack = 'built-in';
    img.src = BUILT_IN_PHOTO_PLACEHOLDER;
}

document.addEventListener('error', (e) => {
    const img = e.target;
    if (img instanceof HTMLImageElement && img.closest(PHOTO_FALLBACK_SCOPE)) useFallbackPhoto(img);
}, true);

document.querySelectorAll(`:is(${PHOTO_FALLBACK_SCOPE}) img`).forEach(img => {
    if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) useFallbackPhoto(img);
});

/**
 * The server now sends photos as [{ url, caption }] with real uploads only
 * (BranchProfile::photoPayload). The string-array branch is a cheap guard for
 * any payload that predates that change — remove it once you're confident
 * nothing is serving the old shape.
 */
function normalizeSpaPhotos(raw) {
    if (!Array.isArray(raw)) return [];

    return raw
        .map(entry => (typeof entry === 'string'
            ? { url: entry, caption: null }
            : { url: entry?.url ?? '', caption: entry?.caption ?? null }))
        .filter(photo => photo.url);
}

/**
 * Thumbnail for listing cards. Falls back to the placeholder when a branch has
 * uploaded nothing at all.
 */
function spaCardThumb(spa) {
    return normalizeSpaPhotos(spa?.photos)[0]?.url || fallbackSpaImage();
}

/**
 * Builds the modal photo grid to fit however many photos exist (0–5). The
 * grid template itself is driven off data-count in the page's <style> block;
 * this only emits the cells.
 */
function renderSpaPhotoGrid(list, spaName) {
    const grid = document.getElementById('spaModalPhotoGrid');
    if (!grid) return;

    const label = escapeHtml(spaName);

    // Zero real uploads: show the placeholder but no gallery affordance —
    // not clickable, no counter, no viewer.
    if (!list.length) {
        grid.dataset.count = '0';
        grid.innerHTML = `
            <div class="spa-photo-cell is-empty">
                <img src="${escapeHtml(fallbackSpaImage())}" alt="${label} has not uploaded any photos yet">
                <span class="spa-photo-empty-label">
                    <i class="fa-solid fa-image"></i>
                    No photos yet
                </span>
            </div>`;
        return;
    }

    grid.dataset.count = String(list.length);
    grid.innerHTML = list.map((photo, i) => {
        const chip = (i === 0 && list.length > 1)
            ? `<span class="spa-photo-chip">
                   <i class="fa-solid fa-images"></i>
                   ${list.length} photos
               </span>`
            : '';

        const alt = photo.caption
            ? escapeHtml(photo.caption)
            : `${label} photo ${i + 1} of ${list.length}`;

        return `
            <button type="button" class="spa-photo-cell" data-photo-index="${i}"
                aria-label="View photo ${i + 1} of ${list.length}">
                <img src="${escapeHtml(photo.url)}" alt="${alt}">
                ${chip}
            </button>`;
    }).join('');
}

// Delegated so it survives the innerHTML rebuild on every modal open.
document.getElementById('spaModalPhotoGrid')?.addEventListener('click', (e) => {
    const cell = e.target.closest('.spa-photo-cell[data-photo-index]');
    if (!cell) return;
    openLightbox(Number(cell.dataset.photoIndex));
});

const lightboxEl = document.getElementById('photoLightbox');
let lightboxIndex        = 0;
let lightboxReturnFocus  = null;

function isLightboxOpen() {
    return !!lightboxEl && !lightboxEl.classList.contains('hidden');
}

function openLightbox(index) {
    if (!lightboxEl || !photos.length) return;

    lightboxIndex       = Math.min(Math.max(index, 0), photos.length - 1);
    lightboxReturnFocus = document.activeElement;

    const single = photos.length < 2;
    document.getElementById('lightboxPrev').hidden = single;
    document.getElementById('lightboxNext').hidden = single;

    renderLightboxSlide();
    lightboxEl.classList.remove('hidden');

    // Body scroll lock is already held by spaModal. Deliberately not touched
    // here — otherwise closing the lightbox would unlock the page behind a
    // still-open modal.
    document.getElementById('lightboxClose')?.focus();
}

function closeLightbox() {
    if (!isLightboxOpen()) return;

    lightboxEl.classList.add('hidden');

    if (lightboxReturnFocus && typeof lightboxReturnFocus.focus === 'function') {
        lightboxReturnFocus.focus();
    }
    lightboxReturnFocus = null;
}

function renderLightboxSlide() {
    const photo   = photos[lightboxIndex];
    const img     = document.getElementById('lightboxImage');
    const caption = document.getElementById('lightboxCaption');
    const counter = document.getElementById('lightboxCounter');
    if (!photo || !img) return;

    img.classList.add('is-swapping');
    img.onload  = () => img.classList.remove('is-swapping');
    img.onerror = () => img.classList.remove('is-swapping');
    img.src = photo.url;
    img.alt = photo.caption || `Photo ${lightboxIndex + 1} of ${photos.length}`;

    if (caption) {
        caption.textContent = photo.caption || '';
        caption.classList.toggle('is-hidden', !photo.caption);
    }
    if (counter) counter.textContent = `${lightboxIndex + 1} / ${photos.length}`;

    preloadAdjacentPhotos();
}

function preloadAdjacentPhotos() {
    if (photos.length < 2) return;

    [-1, 1].forEach(offset => {
        const photo = photos[(lightboxIndex + offset + photos.length) % photos.length];
        if (photo) new Image().src = photo.url;
    });
}

function lightboxStep(delta) {
    if (photos.length < 2) return;
    lightboxIndex = (lightboxIndex + delta + photos.length) % photos.length;
    renderLightboxSlide();
}

if (lightboxEl) {

    lightboxEl.addEventListener('click', (e) => {
        if (e.target.closest('.lightbox-image, .lightbox-btn')) return;
        closeLightbox();
    });

    document.getElementById('lightboxClose')?.addEventListener('click', closeLightbox);
    document.getElementById('lightboxPrev')?.addEventListener('click', () => lightboxStep(-1));
    document.getElementById('lightboxNext')?.addEventListener('click', () => lightboxStep(1));

    // Arrows navigate; Tab is trapped inside the dialog. Escape is handled by
    // the shared top-most-wins handler at the bottom of this file.
    lightboxEl.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            lightboxStep(-1);
            return;
        }
        if (e.key === 'ArrowRight') {
            e.preventDefault();
            lightboxStep(1);
            return;
        }
        if (e.key !== 'Tab') return;

        const focusable = Array.from(lightboxEl.querySelectorAll('button:not([hidden])'));
        if (!focusable.length) return;

        const first = focusable[0];
        const last  = focusable[focusable.length - 1];

        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    });

    // Horizontal swipe, for the Median.co wrapper. A 50px threshold plus the
    // dx > dy check keeps it from firing on a vertical scroll gesture.
    const stage = document.getElementById('lightboxStage');
    let touchStartX = 0;
    let touchStartY = 0;
    let touchTracking = false;

    stage?.addEventListener('touchstart', (e) => {
        touchTracking = e.touches.length === 1;
        if (!touchTracking) return;
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
    }, { passive: true });

    stage?.addEventListener('touchend', (e) => {
        if (!touchTracking) return;
        touchTracking = false;

        const touch = e.changedTouches[0];
        const dx = touch.clientX - touchStartX;
        const dy = touch.clientY - touchStartY;

        if (Math.abs(dx) < 50 || Math.abs(dx) <= Math.abs(dy)) return;
        lightboxStep(dx < 0 ? 1 : -1);
    }, { passive: true });
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
    const thumb = spaCardThumb(spa);
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
// Reserve buttons (the spa modal's footer bar); a list so more can be added.
const openBookingBtns      = document.querySelectorAll('[data-open-booking-modal]');
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

// Whether the selected branch can take online home service at all (staff
// verification, decision Q35b). null = not known yet, so nothing is blocked
// until the server answers; checkout re-checks either way.
let homeServiceAvailable = null;
let homeServiceMessage   = '';

async function loadHomeServiceAvailability(branchId) {
    homeServiceAvailable = null;
    homeServiceMessage   = '';
    if (!branchId) return;

    try {
        const params = new URLSearchParams({ branch_id: branchId });
        const response = await fetch(`/bookings/online/available-days?${params.toString()}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) return;
        const data = await response.json();

        // Ignore a late answer for a branch the customer already left.
        if (String(bookingBranchIdInput?.value) !== String(branchId)) return;

        homeServiceAvailable = data.home_service_available !== false;
        homeServiceMessage   = data.home_service_message || '';

        // A treatment may already be picked: refresh its options.
        if (getCheckedTreatmentInput()) populateServiceTypeOptions();
    } catch (err) {
        console.warn('Could not check home-service availability:', err);
    }
}

function openTermsModal() {
    document.getElementById('termsModal').classList.remove('hidden');
}

function closeTermsModal() {
    document.getElementById('termsModal').classList.add('hidden');
}

// "I Understand": the only way to unlock the booking's terms checkbox, so the
// customer has to open the terms first. It doesn't tick the box for them.
function acceptTermsModal() {
    closeTermsModal();
    setTermsUnlocked(true);
    document.getElementById('bookingTermsCheckbox')?.focus();
}

function setTermsUnlocked(unlocked) {
    const box = document.getElementById('bookingTermsCheckbox');
    if (box) {
        box.disabled = !unlocked;
        if (!unlocked) box.checked = false;
    }
    document.getElementById('bookingTermsHint')?.classList.toggle('hidden', unlocked);
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
    } else if (serviceType === 'in_branch_and_home' && homeServiceAvailable === false) {
        // No verified therapist at this branch: offer In-Branch and say why.
        serviceTypeSelect.innerHTML = `
            <option value="in_branch">In-Branch</option>
            <option value="in_home" disabled>Home Service (not available)</option>
        `;
        serviceTypeSelect.value = 'in_branch';
        serviceTypeSelect.disabled = false;
        if (serviceTypeHint) serviceTypeHint.textContent = homeServiceMessage
            || 'Home service isn\'t available at this branch right now. Choose In-Branch, or try another branch.';
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
    no_verified_therapist: 'Home service needs a verified therapist, and none is free at this time. Try another time, or choose In-Branch.',
    past_closing: 'This service would end after closing.',
    past: 'This time has already passed today.',
    too_soon: 'Online bookings need at least 30 minutes\' notice. Call the spa for anything sooner.',
    already_booked: 'You already have an appointment at this time.',
    no_therapist_on_shift: 'No therapist is working at this time. Try another time or day.',
};

// Short status shown on each unavailable time row.
const SLOT_NOTE_LABELS = {
    fully_booked: 'Booked',
    no_verified_therapist: 'No home-service therapist',
    past_closing: 'Too close to closing',
    past: 'Passed',
    too_soon: 'Too soon',
    already_booked: 'Your appointment',
    no_therapist_on_shift: 'No therapist working',
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
            // Home service only counts identity-verified therapists.
            service_type: serviceTypeSelect?.value || 'in_branch',
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
            slotBtn.dataset.slotTime = slot.time;
            slotBtn.className = 'slot-btn bk-slot';

            const timeEl = document.createElement('span');
            timeEl.className = 'bk-slot-time';
            timeEl.textContent = formatTime12Hour(slot.time);

            const noteEl = document.createElement('span');
            noteEl.className = 'bk-slot-note';
            if (slot.available) {
                const free = Number(slot.free) || 1;
                noteEl.textContent = `${free} left ${free === 1 ? 'is' : 'are'} available`;
            } else {
                noteEl.textContent = SLOT_NOTE_LABELS[slot.reason] || 'Unavailable';
            }

            slotBtn.append(timeEl, noteEl);

            if (slot.available) {
                slotBtn.addEventListener('click', () => selectSlot(slot.time, slotBtn));
            } else {
                slotBtn.disabled = true;
                // too_soon names the server's notice window (config, Phase 3 D7).
                slotBtn.title = slot.reason === 'too_soon' && data.min_notice_minutes
                    ? `Online bookings need at least ${data.min_notice_minutes} minutes' notice. Call the spa for anything sooner.`
                    : (SLOT_REASON_LABELS[slot.reason] || 'Unavailable');
                if (slot.reason === 'past_closing') slotBtn.classList.add('is-past-closing');
            }

            grid.appendChild(slotBtn);
        });

        // One line saying why, when home-service times are closed only because
        // no verified therapist is free (decision Q35). Same message style as
        // the other grid notes, so no new CSS is needed.
        if (data.slots.some(slot => slot.reason === 'no_verified_therapist')) {
            const note = document.createElement('p');
            note.className = 'col-span-3 py-6 text-sm text-center text-gray-400 dark:text-gray-500 sm:col-span-4';
            note.textContent = SLOT_REASON_LABELS.no_verified_therapist;
            grid.prepend(note);
        }

        legend?.classList.remove('hidden');
        legend?.classList.add('flex');

    } catch (err) {
        console.error('Failed to load slots:', err);
        grid.innerHTML = '<p class="col-span-3 py-6 text-sm text-center text-red-400 dark:text-red-300 sm:col-span-4">Unable to load availability. Please try again.</p>';
    } finally {
        loading?.classList.add('hidden');
    }
}

// ── Step 2: month calendar (same look as My Schedule) ───────────────────────
let bookingCalMonth  = new Date();   // the month on display
let bookingOpenDates = new Set();    // dates the customer may book
let bookingCalFirst  = '';           // first bookable date (today)
let bookingCalLast   = '';           // last bookable date (30 days ahead)
let bookingMyDates   = new Set();    // days this customer already has an appointment

async function loadBookingCalendar() {
    const grid     = document.getElementById('bookingCalGrid');
    const branchId = bookingBranchIdInput?.value;

    if (!grid || !branchId) return;

    grid.innerHTML = '<p class="bk-cal-msg">Loading dates…</p>';

    try {
        const params = new URLSearchParams({ branch_id: branchId });
        const response = await fetch(`/bookings/online/available-days?${params.toString()}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();

        bookingOpenDates = new Set(data.dates || []);
        bookingCalFirst  = data.first || '';
        bookingCalLast   = data.last || '';

        // Open on the month of the chosen date, or the current month.
        const base = bookingDateInput?.value || bookingCalFirst;
        bookingCalMonth = base ? new Date(base + 'T00:00:00') : new Date();
        bookingCalMonth.setDate(1);

        renderBookingCalendar();
    } catch (err) {
        console.error('Failed to load booking dates:', err);
        grid.innerHTML = '<p class="bk-cal-msg">Unable to load dates. Please try again.</p>';
        return;
    }

    // The customer's own appointments, for the dot. The calendar still works
    // if this fails.
    try {
        const response = await fetch('/my-schedule', { headers: { 'Accept': 'application/json' } });
        if (!response.ok) return;
        const bookings = await response.json();
        if (!Array.isArray(bookings)) return;

        bookingMyDates = new Set(
            bookings
                .filter(b => !['cancelled', 'completed', 'no_show'].includes(String(b.status || '').toLowerCase()))
                .map(b => b.date_raw)
        );
        renderBookingCalendar();
    } catch (err) {
        console.warn('Could not load your appointments for the calendar:', err);
    }
}

function renderBookingCalendar() {
    const grid  = document.getElementById('bookingCalGrid');
    const title = document.getElementById('bookingCalTitle');
    if (!grid) return;

    const year     = bookingCalMonth.getFullYear();
    const month    = bookingCalMonth.getMonth();
    const monthKey = `${year}-${String(month + 1).padStart(2, '0')}`;

    if (title) {
        title.textContent = bookingCalMonth.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    }

    // The arrows stop at the first and last month that has bookable days.
    const prevBtn = document.getElementById('bookingCalPrev');
    const nextBtn = document.getElementById('bookingCalNext');
    if (prevBtn) prevBtn.disabled = !bookingCalFirst || monthKey <= bookingCalFirst.slice(0, 7);
    if (nextBtn) nextBtn.disabled = !bookingCalLast || monthKey >= bookingCalLast.slice(0, 7);

    const firstDay    = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const selected    = bookingDateInput?.value || '';

    grid.innerHTML = '';
    for (let i = 0; i < firstDay; i++) grid.appendChild(document.createElement('div'));

    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${monthKey}-${String(d).padStart(2, '0')}`;
        const isOpen  = bookingOpenDates.has(dateStr);
        const isMine  = bookingMyDates.has(dateStr);

        const dayBtn = document.createElement('button');
        dayBtn.type = 'button';
        dayBtn.className = 'bk-cal-day';
        dayBtn.dataset.date = dateStr;
        dayBtn.textContent = String(d);
        dayBtn.disabled = !isOpen;
        dayBtn.setAttribute('aria-pressed', dateStr === selected ? 'true' : 'false');

        const longDate = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', {
            weekday: 'long', month: 'long', day: 'numeric',
        });
        dayBtn.setAttribute('aria-label',
            longDate + (isOpen ? '' : ', not available') + (isMine ? ', you have an appointment' : ''));

        if (isMine) {
            const dot = document.createElement('span');
            dot.className = 'bk-cal-dot';
            dot.setAttribute('aria-hidden', 'true');
            dayBtn.appendChild(dot);
        }

        if (isOpen) dayBtn.addEventListener('click', () => selectBookingDay(dateStr));

        grid.appendChild(dayBtn);
    }
}

function changeBookingMonth(dir) {
    bookingCalMonth.setMonth(bookingCalMonth.getMonth() + dir);
    renderBookingCalendar();
}

function selectBookingDay(date) {
    if (bookingDateInput) bookingDateInput.value = date;
    document.querySelectorAll('#bookingCalGrid .bk-cal-day').forEach(dayBtn => {
        dayBtn.setAttribute('aria-pressed', dayBtn.dataset.date === date ? 'true' : 'false');
    });
    loadAvailableSlots();
}

document.getElementById('bookingCalPrev')?.addEventListener('click', () => changeBookingMonth(-1));
document.getElementById('bookingCalNext')?.addEventListener('click', () => changeBookingMonth(1));
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
    recapPrice = price;
    updatePaymentRecap();
    updateRecapCancelNote(dateVal, timeVal);
}

// Customer cancellation (Nov 2026), said before paying. Mirrors
// CustomerCancellation on the server: free until 24 hours after payment or
// 3 hours before the start, whichever is first, once per 30 days. Payment
// happens after this step, so the real window can end a few minutes later.
function updateRecapCancelNote(dateVal, timeVal) {
    const note = document.getElementById('recapCancelNote');
    const text = document.getElementById('recapCancelNoteText');
    if (!note || !text) return;

    const start = (dateVal && timeVal) ? new Date(`${dateVal}T${timeVal.slice(0, 5)}:00`) : null;
    note.classList.toggle('hidden', !start);
    if (!start) return;

    const now       = Date.now();
    const freeUntil = Math.min(now + 24 * 3600e3, start.getTime() - 3 * 3600e3);
    const againOn   = note.dataset.freeAgainOn ? new Date(note.dataset.freeAgainOn) : null;

    if (freeUntil <= now) {
        text.textContent = "This appointment is less than 3 hours away, so it can't be cancelled for free. If you cancel, the 20% reservation fee is kept.";
    } else if (againOn && againOn.getTime() > now) {
        text.textContent = `You've used your free cancellation. It's available again on ${formatCancelDate(againOn)}. If you cancel this booking, the 20% reservation fee is kept.`;
    } else {
        text.textContent = `Free cancellation until about ${formatCancelDateTime(new Date(freeUntil))}. After that, cancelling keeps the 20% reservation fee.`;
    }
}

function formatCancelDate(d) {
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatCancelDateTime(d) {
    return `${d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}, ${d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}`;
}

// Price shown in the recap, kept so the amounts can be redrawn when the
// customer switches between the reservation fee and full payment.
let recapPrice = 0;

// Display only. The server works out the real amount from the chosen option.
function updatePaymentRecap() {
    const payNowEl = document.getElementById('recapPayNow');
    if (!payNowEl) return;

    const price       = recapPrice || 0;
    const downpayment = Math.round(price * 20) / 100;   // 20%, rounded to centavos
    const isFull      = document.querySelector('input[name="payment_option"]:checked')?.value === 'full';
    const payNow      = isFull ? price : downpayment;
    const show        = (amount) => (price ? formatPeso(amount) : '—');

    document.getElementById('payOptDownAmount').textContent = show(downpayment);
    document.getElementById('payOptFullAmount').textContent = show(price);
    payNowEl.textContent = show(payNow);
    document.getElementById('recapBalance').textContent = show(price - payNow);
}

document.addEventListener('change', (e) => {
    if (e.target?.name === 'payment_option') updatePaymentRecap();
});

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

    loadHomeServiceAvailability(selectedSpa.branch_id);
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
        addressInput.value = addressInput.dataset.defaultAddress || '';
        addressInput.required = false;
    }
    if (addressWrapper) addressWrapper.classList.add('hidden');
    if (bookingCustomerPhone) bookingCustomerPhone.value = bookingCustomerPhone.dataset.defaultPhone || '';
    // Every new booking reads the terms again.
    setTermsUnlocked(false);

    const timeError = document.getElementById('bookingTimeError');
    const treatmentError = document.getElementById('bookingTreatmentError');
    const submitBtn = document.getElementById('bookingSubmitBtn');
    if (timeError) { timeError.classList.add('hidden'); }
    if (treatmentError) treatmentError.classList.add('hidden');
    if (submitBtn) submitBtn.disabled = false;
}

// ── Job openings (staff scheduling & hiring Phase 5) ────────────────────────
// GET /apply/{branch}/openings → { openings: [{ id, title, role_label, openings,
// employment_type, description, requirements, pay, deadline, schedule: [{ day, label, rest }] }] }
let applicationOpenings = [];

async function fetchBranchOpenings(branchId) {
    const res = await fetch(`/apply/${encodeURIComponent(branchId)}/openings`, { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    return Array.isArray(data.openings) ? data.openings : [];
}

function openingMetaText(o) {
    const parts = [o.role_label, o.employment_type];
    if (o.openings > 1) parts.push(`${o.openings} openings`);
    if (o.pay) parts.push(o.pay);
    if (o.deadline) parts.push(`Apply by ${o.deadline}`);
    return parts.filter(Boolean).join(' · ');
}

function renderApplicationPosting() {
    const select  = document.getElementById('applicationPosting');
    const details = document.getElementById('applicationPostingDetails');
    if (!select || !details) return;

    const o = applicationOpenings.find(x => String(x.id) === select.value);
    details.classList.toggle('hidden', !o);
    details.querySelectorAll('input[name="schedule_fit"]').forEach(r => { r.required = !!o; });
    if (!o) return;

    document.getElementById('applicationPostingMeta').textContent = openingMetaText(o);
    const desc = document.getElementById('applicationPostingDesc');
    const text = [o.description, o.requirements ? `Requirements: ${o.requirements}` : ''].filter(Boolean).join('\n\n');
    desc.textContent = text;
    desc.classList.toggle('hidden', !text);
    document.getElementById('applicationPostingSchedule').innerHTML = (o.schedule || []).map(l =>
        `<li class="flex justify-between gap-3"><span class="font-medium">${escapeHtml(l.day)}</span><span class="${l.rest ? 'text-gray-400' : ''}">${escapeHtml(l.label)}</span></li>`
    ).join('');
}

async function loadApplicationOpenings(branchId, postingId) {
    const select = document.getElementById('applicationPosting');
    if (!select) return;
    applicationOpenings = [];
    select.innerHTML = '<option value="">Loading openings…</option>';
    select.disabled = true;

    try {
        applicationOpenings = await fetchBranchOpenings(branchId);
    } catch (err) {
        console.warn('Could not load job openings:', err);
    }

    if (!applicationOpenings.length) {
        select.innerHTML = '<option value="">No open positions right now</option>';
        renderApplicationPosting();
        return;
    }

    select.innerHTML = '<option value="">Select a job opening</option>' + applicationOpenings.map(o =>
        `<option value="${escapeHtml(String(o.id))}">${escapeHtml(o.title)}</option>`).join('');
    select.disabled = false;

    const preselect = postingId ?? (applicationOpenings.length === 1 ? applicationOpenings[0].id : null);
    if (preselect != null) select.value = String(preselect);
    renderApplicationPosting();
}

/** Fill the spa modal's "We're Hiring" list with Apply buttons. */
async function renderSpaModalOpenings(spaData) {
    const list = document.getElementById('spaModalOpenings');
    if (!list) return;
    list.innerHTML = '';
    if (!spaData?.is_hiring || !spaData.branch_id) return;

    let openings = [];
    try {
        openings = await fetchBranchOpenings(spaData.branch_id);
    } catch (err) {
        console.warn('Could not load job openings:', err);
        return;
    }

    list.innerHTML = openings.map(o => `
        <li class="spa-opening">
            <div class="min-w-0">
                <p class="spa-opening-title">${escapeHtml(o.title)}</p>
                <p class="spa-opening-meta">${escapeHtml(openingMetaText(o))}</p>
            </div>
            <button type="button" data-posting-id="${escapeHtml(String(o.id))}"
                class="spa-modal-apply-btn inline-flex items-center gap-1.5 min-h-[44px] px-4 py-2 text-xs font-semibold text-white rounded-xl booking-btn shadow-sm">
                <i class="fa-solid fa-paper-plane text-[10px]" aria-hidden="true"></i> Apply
            </button>
        </li>`).join('');

    list.querySelectorAll('.spa-modal-apply-btn').forEach(btn => {
        btn.addEventListener('click', () => openApplicationModal(spaData.id, spaData.branch_id, spaData.name, Number(btn.dataset.postingId)));
    });
}

function openApplicationModal(spaId, branchId, spaName, postingId = null) {
    const modal = document.getElementById('applicationModal');
    const form  = document.getElementById('applicationForm');
    if (!modal || !form) return;

    form.reset();
    form.action = `/apply/${branchId}`;
    loadApplicationOpenings(branchId, postingId);

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
    releaseBodyUnlessSpaOpen();
}

// =====================================================
// APPLICATION FORM SUBMISSION (with resume upload)
// =====================================================

function setupApplicationForm() {
    const form = document.getElementById('applicationForm');
    if (!form) return;

    document.getElementById('applicationPosting')?.addEventListener('change', renderApplicationPosting);

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
        const posting = this.querySelector('select[name="job_posting_id"]')?.value;
        const resume = this.querySelector('input[name="resume"]')?.files[0];

        if (!posting) {
            showSpaToast('Please pick the job opening you are applying for.', 'error');
            return;
        }

        if (!fullName || !email || !phone || !address) {
            showSpaToast('Please fill in all required fields.', 'error');
            return;
        }

        if (!this.querySelector('input[name="schedule_fit"]:checked')) {
            showSpaToast('Please tell us if you can work this schedule.', 'error');
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

        // Consent to the privacy notice (landing footer Phase 2, Q5-A)
        if (!this.querySelector('input[name="privacy_consent"]')?.checked) {
            showSpaToast('Please read the privacy notice and tick the consent box to apply.', 'error');
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
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> <span>Sending…</span>';

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
            renderApplicationPosting();
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
    releaseBodyUnlessSpaOpen();
}

openBookingBtns.forEach(btn => btn.addEventListener('click', () => {
    try {
        openBookingModal();
    } catch (e) {
        console.error('openBookingModal failed to open:', e);
    }
}));
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
// Home service counts only verified therapists, so the times can differ.
serviceTypeSelect?.addEventListener('change', () => {
    if (bookingDateInput?.value && getCheckedTreatmentInput()) loadAvailableSlots();
});
bookingDateInput?.addEventListener('change', loadAvailableSlots);

document.getElementById('bookingNextBtn')?.addEventListener('click', function () {
    if (!validateBookingStep(currentBookingStep)) return;
    if (currentBookingStep < TOTAL_BOOKING_STEPS) {
        const nextStep = currentBookingStep + 1;
        showBookingStep(nextStep);
        if (nextStep === 2) loadBookingCalendar();
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

    const termsBox = document.getElementById('bookingTermsCheckbox');
    if (termsBox?.disabled) {
        showSpaToast('Please open and read the terms and conditions first.', 'error');
        return;
    }
    if (!termsBox?.checked) {
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
let _apReturnFocus  = null;

/*
  My appointments (UI rework Phase 2).
  - Upcoming: soonest first, grouped "Happening now" / "Today" / "Tomorrow" /
    one group per later day. Past and Cancelled: most recent first, grouped by
    month.
  - Rows are <button>s styled by the .apt-* rules in landing.css (this file
    isn't in Tailwind's content globs, so no Tailwind classes are built here).
  - Customers see their own status words (customerStatus); staff pages keep
    the raw statuses.
*/

const ACTIVE_BOOKING_STATUSES = ['reserved', 'pending', 'ongoing'];

// Local Date for a booking's day + time ("HH:MM[:SS]"); null when missing.
function bookingDateTime(b, time) {
    if (!b.date_raw || !time) return null;
    return new Date(`${b.date_raw}T${time.slice(0, 8)}`);
}

function isBookingHappeningNow(b) {
    const start = bookingDateTime(b, b.start_time);
    const end   = bookingDateTime(b, b.end_time);
    const now   = Date.now();
    return ACTIVE_BOOKING_STATUSES.includes(b.status) && !!start && !!end
        && start.getTime() <= now && now < end.getTime();
}

/*
  What the customer sees for a status. 'pending' (start time passed, set by
  the clock, staff haven't pressed Start) reads "Waiting to start"; only
  'ongoing' (started by staff) reads "In progress" (attendance-aware booking
  Phase 3, D5 / Q1 B: true whoever is late, customer or spa). An
  active booking whose end time has passed is waiting for the spa to check it
  out (staff checkout is the only thing that completes a booking), so it
  reads "Ended" rather than claiming to be confirmed or completed.
*/
function customerStatus(b) {
    const s = b.status;
    if (ACTIVE_BOOKING_STATUSES.includes(s) && isBookingPastNow(b)) return { key: 'ended',     label: 'Ended' };
    if (s === 'reserved')                          return { key: 'confirmed', label: 'Confirmed' };
    if (s === 'pending')                           return { key: 'waiting',   label: 'Waiting to start' };
    if (s === 'ongoing')                           return { key: 'progress',  label: 'In progress' };
    if (s === 'completed')                         return { key: 'completed', label: 'Completed' };
    if (s === 'cancelled')                         return { key: 'cancelled', label: 'Cancelled' };
    if (s === 'no_show')                           return { key: 'missed',    label: 'Missed' };
    return { key: 'ended', label: s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : '' };
}

// A reschedule request still waiting for review. The server's reschedule_pending
// is false once the original start has passed (lifecycle Phase 3); older
// payloads without it fall back to the raw status.
function rescheduleWaiting(b) {
    return b.reschedule_pending ?? (b.reschedule_status === 'pending');
}

function customerStatusPill(b) {
    const st = customerStatus(b);
    return `<span class="apt-pill apt-pill--${st.key}">${escapeHtml(st.label)}</span>`;
}

// Which tab a booking belongs to. Missed visits sit with past ones.
function appointmentTab(b) {
    if (b.status === 'cancelled') return 'cancelled';
    if (ACTIVE_BOOKING_STATUSES.includes(b.status) && !isBookingPastNow(b)) return 'upcoming';
    return 'past';
}

function sortForTab(list, tab) {
    const key = b => `${b.date_raw || ''}T${(b.start_time || '').slice(0, 8)}`;
    return [...list].sort((a, b) => tab === 'upcoming'
        ? key(a).localeCompare(key(b))
        : key(b).localeCompare(key(a)));
}

function addDaysLocal(dateStr, days) {
    const [y, m, d] = dateStr.split('-').map(Number);
    const dt = new Date(y, m - 1, d + days);
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
}

function localDateFromRaw(raw) {
    const [y, m, d] = raw.split('-').map(Number);
    return new Date(y, m - 1, d);
}

// Groups for one tab, in display order: [{ key, title, now?, items: [] }].
function groupAppointments(list, tab) {
    const today    = getTodayLocal();
    const tomorrow = addDaysLocal(today, 1);
    const groups   = [];
    const byKey    = {};

    const put = (key, title, b, extra = {}) => {
        if (!byKey[key]) { byKey[key] = { key, title, items: [], ...extra }; groups.push(byKey[key]); }
        byKey[key].items.push(b);
    };

    sortForTab(list, tab).forEach(b => {
        if (!b.date_raw) { put('other', 'Other', b); return; }
        const day = localDateFromRaw(b.date_raw);

        if (tab === 'upcoming') {
            const dayLabel = day.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
            if (isBookingHappeningNow(b))      put('now', 'Happening now', b, { now: true });
            else if (b.date_raw === today)     put(b.date_raw, `Today · ${dayLabel}`, b);
            else if (b.date_raw === tomorrow)  put(b.date_raw, `Tomorrow · ${dayLabel}`, b);
            else put(b.date_raw, day.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' }), b);
        } else {
            put(b.date_raw.slice(0, 7), day.toLocaleDateString('en-US', { month: 'long', year: 'numeric' }), b);
        }
    });

    // "Happening now" always leads, whatever its date.
    groups.sort((a, b) => (b.now ? 1 : 0) - (a.now ? 1 : 0));
    return groups;
}

function openAppointmentsModal() {
    _apReturnFocus = document.activeElement;
    document.getElementById('appointmentsModal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    loadAppointments();
    requestAnimationFrame(() => document.getElementById('apTitle')?.focus());
}

function closeAppointmentsModal() {
    document.getElementById('appointmentsModal').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    if (_apReturnFocus && document.contains(_apReturnFocus)) _apReturnFocus.focus();
    _apReturnFocus = null;
}

function loadAppointments() {
    fetch('/my-appointments')
        .then(r => r.json())
        .then(data => {
            allAppointments = data;
            updateTabCounts();
            renderTab(currentTab);

            const upcomingCount = data.filter(b => appointmentTab(b) === 'upcoming').length;
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

// Counts sit on the tabs only when there is something to count.
function updateTabCounts() {
    ['upcoming', 'past', 'cancelled'].forEach(tab => {
        const el = document.getElementById(`tab-count-${tab}`);
        if (!el) return;
        const n = allAppointments.filter(b => appointmentTab(b) === tab).length;
        el.textContent = n > 99 ? '99+' : n;
        el.classList.toggle('hidden', n === 0);
    });
}

// Tabs follow the ARIA tabs pattern: state lives on aria-selected (styled in
// landing.css), and Left/Right/Home/End move between tabs.
function switchTab(tab, focusTab = false) {
    currentTab = tab;
    ['upcoming', 'past', 'cancelled'].forEach(t => {
        const el = document.getElementById(`tab-${t}`);
        if (!el) return;
        el.setAttribute('aria-selected', t === tab ? 'true' : 'false');
        el.tabIndex = t === tab ? 0 : -1;
        if (t === tab && focusTab) el.focus();
    });
    document.getElementById('appointmentsContent')?.setAttribute('aria-labelledby', `tab-${tab}`);
    renderTab(tab);
}

document.querySelector('#appointmentsModal [role="tablist"]')?.addEventListener('keydown', (e) => {
    const order = ['upcoming', 'past', 'cancelled'];
    const i = order.indexOf(currentTab);
    const next = { ArrowRight: order[(i + 1) % 3], ArrowLeft: order[(i + 2) % 3], Home: order[0], End: order[2] }[e.key];
    if (!next) return;
    e.preventDefault();
    switchTab(next, true);
});

// Keep Tab inside the panel while it is open (Booking details, when open on
// top, has its own trap).
document.getElementById('appointmentsModal')?.addEventListener('keydown', (e) => {
    if (e.key !== 'Tab') return;
    const focusable = [...document.querySelectorAll(
        '#apPanel button:not([disabled]):not([tabindex="-1"]), #apPanel a[href]'
    )].filter(el => el.offsetParent !== null);
    if (!focusable.length) return;
    const first = focusable[0];
    const last  = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
});

const APT_EMPTY = {
    upcoming:  { icon: 'fa-calendar-plus', title: 'Book your next treatment',
                 text: 'Your upcoming appointments will show here.', cta: true },
    past:      { icon: 'fa-clock-rotate-left', title: 'No past visits yet',
                 text: 'Appointments you\'ve had will show here, so you can rate them.' },
    cancelled: { icon: 'fa-calendar-xmark', title: 'No cancelled appointments',
                 text: 'Anything that gets cancelled will show here.' },
};

// "Book your next treatment": close the panel and bring the spa list into view.
function browseSpasFromAppointments() {
    closeAppointmentsModal();
    document.getElementById('browseSections')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function renderAppointmentRow(b, i, tab, mapName = '_appointmentMap') {
    const st        = customerStatus(b);
    const isHome    = !!b.is_home_service;
    const therapist = b.therapist_card ? b.therapist_card.name
        : (b.therapist && b.therapist !== 'Not Assigned' ? b.therapist : '');
    const verified  = !!(b.therapist_card && b.therapist_card.verified);
    const canRate   = b.status === 'completed' && !b.has_rating;
    const hasRating = b.has_rating === true;
    const day       = b.date_raw ? localDateFromRaw(b.date_raw) : null;

    // Upcoming groups already say the day, so the left column is the time.
    // Past/Cancelled are grouped by month, so it shows the date too.
    const when = tab === 'upcoming'
        ? `<span class="apt-when-main">${formatTime(b.start_time)}</span>`
        : `<span class="apt-when-main">${day ? day.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : ''}</span>
           <span class="apt-when-sub">${formatTime(b.start_time)}</span>`;

    // The tab already implies the usual status; show a pill only when it differs.
    const usual = { upcoming: 'confirmed', past: 'completed', cancelled: 'cancelled' }[tab];
    const chips = [];
    if (st.key !== usual) chips.push(customerStatusPill(b));
    if (isHome) chips.push(`<span class="apt-chip"><i class="fa-solid fa-house" aria-hidden="true"></i>Home service</span>`);
    if (therapist) {
        chips.push(`<span class="apt-chip${verified ? ' apt-chip--verified' : ''}"><i class="fa-solid ${verified ? 'fa-shield-halved' : 'fa-user'}" aria-hidden="true"></i>${escapeHtml(therapist)}${verified ? '<span class="sr-only"> (identity verified)</span>' : ''}</span>`);
    } else if (tab === 'upcoming') {
        chips.push(`<span class="apt-chip"><i class="fa-solid fa-user" aria-hidden="true"></i>Therapist not assigned yet</span>`);
    }
    if (rescheduleWaiting(b)) {
        chips.push(`<span class="apt-chip apt-chip--warn"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>Reschedule requested</span>`);
    }

    const stars = n => [1, 2, 3, 4, 5]
        .map(k => `<i class="fa-solid fa-star${k <= n ? ' is-on' : ''}" aria-hidden="true"></i>`).join('');

    const actions = canRate
        ? `<div class="apt-actions">
               <button type="button" class="apt-rate"
                   onclick="openRatingModal(${mapName}[${i}].id, ${mapName}[${i}].therapist, ${mapName}[${i}].spa_name, ${mapName}[${i}].branch_name, ${mapName}[${i}].branch_location)">
                   <i class="fa-regular fa-star" aria-hidden="true"></i>Rate your visit
               </button>
           </div>`
        : hasRating
        ? `<div class="apt-actions">
               <span class="apt-rated"><span class="sr-only">You rated this ${b.rating_value} out of 5.</span><span aria-hidden="true">You rated</span>${stars(b.rating_value)}</span>
           </div>`
        : '';

    return `
        <li class="apt-item">
            <button type="button" class="apt-row" onclick="openBookingDetailsModal(${mapName}[${i}])">
                <span class="apt-when">${when}</span>
                <span class="apt-main">
                    <span class="apt-title">${escapeHtml(b.treatment)}</span>
                    <span class="apt-sub">${escapeHtml(b.spa_name)} · ${escapeHtml(b.branch_location ?? b.branch_name)}</span>
                    ${chips.length ? `<span class="apt-chips">${chips.join('')}</span>` : ''}
                </span>
                <i class="apt-chev fa-solid fa-chevron-right" aria-hidden="true"></i>
            </button>
            ${actions}
        </li>`;
}

function renderTab(tab) {
    const filtered = allAppointments.filter(b => appointmentTab(b) === tab);

    Object.keys(_appointmentMap).forEach(k => delete _appointmentMap[k]);

    const container = document.getElementById('appointmentsContent');
    if (!filtered.length) {
        const e = APT_EMPTY[tab];
        container.innerHTML = `
            <div class="apt-empty">
                <span class="apt-empty-icon"><i class="fa-solid ${e.icon}" aria-hidden="true"></i></span>
                <p class="apt-empty-title">${e.title}</p>
                <p class="apt-empty-text">${e.text}</p>
                ${e.cta ? `<button type="button" class="apt-empty-cta booking-btn" onclick="browseSpasFromAppointments()">
                    <i class="fa-solid fa-spa" aria-hidden="true"></i>Browse spas</button>` : ''}
            </div>`;
        return;
    }

    let i = 0;
    container.innerHTML = groupAppointments(filtered, tab).map(g => {
        const rows = g.items.map(b => { _appointmentMap[i] = b; return renderAppointmentRow(b, i++, tab); }).join('');
        const id   = `apt-group-${g.key}`;
        return `
            <section class="apt-group" aria-labelledby="${id}">
                <h3 id="${id}" class="apt-group-title${g.now ? ' apt-group-title--now' : ''}">${escapeHtml(g.title)}</h3>
                <ul class="apt-list">${rows}</ul>
            </section>`;
    }).join('');
}

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

// =====================================================
// MY SCHEDULE MODAL
// =====================================================
/*
  My schedule (UI rework Phase 4): the customer's upcoming appointments by
  month, as a list grouped by day or as a calendar with the chosen day's
  appointments under it. Rows are the same .apt-row buttons as My
  appointments (renderAppointmentRow), so a booking looks the same in both.
  The /my-schedule feed holds active bookings from today on, so months before
  this one are always empty and Previous stops at the current month.
*/
let scheduleBookings  = [];
let calendarDate      = new Date();
calendarDate.setDate(1);
let scheduleMap       = {};
let scheduleViewMode  = 'list';      // 'list' | 'calendar'
let scheduleSelected  = null;        // 'YYYY-MM-DD' in calendar view
let _scReturnFocus    = null;

try {
    const saved = localStorage.getItem('levictas.scheduleView');
    if (saved === 'list' || saved === 'calendar') scheduleViewMode = saved;
} catch (e) { /* storage blocked: keep the default */ }

function openScheduleModal() {
    _scReturnFocus = document.activeElement;
    document.getElementById('scheduleModal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    calendarDate = new Date();
    calendarDate.setDate(1);
    scheduleSelected = null;
    applyScheduleView();
    loadSchedule();
    requestAnimationFrame(() => document.getElementById('scTitle')?.focus());
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    if (_scReturnFocus && document.contains(_scReturnFocus)) _scReturnFocus.focus();
    _scReturnFocus = null;
}

function loadSchedule() {
    fetch('/my-schedule')
        .then(r => r.json())
        .then(data => {
            scheduleBookings = data;
            renderSchedule();
        });
}

function scheduleMonthKey(date = calendarDate) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function scheduleInMonth() {
    const key = scheduleMonthKey();
    return scheduleBookings.filter(b => (b.date_raw || '').startsWith(key));
}

function renderSchedule() {
    const now = new Date();
    document.getElementById('calendarTitle').textContent =
        calendarDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    // Nothing before this month can be in the feed.
    document.getElementById('scPrevMonth').disabled =
        calendarDate.getFullYear() === now.getFullYear() && calendarDate.getMonth() === now.getMonth();

    if (scheduleViewMode === 'list') renderScheduleList();
    else renderCalendar();
}

function setScheduleView(mode) {
    scheduleViewMode = mode;
    try { localStorage.setItem('levictas.scheduleView', mode); } catch (e) { /* ignore */ }
    applyScheduleView();
    renderSchedule();
}

function applyScheduleView() {
    const list = scheduleViewMode === 'list';
    document.getElementById('scheduleListView')?.classList.toggle('hidden', !list);
    document.getElementById('scheduleCalendarView')?.classList.toggle('hidden', list);
    document.getElementById('scViewList')?.setAttribute('aria-pressed', list ? 'true' : 'false');
    document.getElementById('scViewCalendar')?.setAttribute('aria-pressed', list ? 'false' : 'true');
}

// Always step from the 1st, so Jan 31 + 1 month is February, not March 3.
function changeMonth(dir) {
    calendarDate = new Date(calendarDate.getFullYear(), calendarDate.getMonth() + dir, 1);
    scheduleSelected = null;
    document.getElementById('scBody').scrollTop = 0;
    renderSchedule();
}

function jumpToScheduleMonth(dateRaw) {
    const [y, m] = dateRaw.split('-').map(Number);
    calendarDate = new Date(y, m - 1, 1);
    scheduleSelected = scheduleViewMode === 'calendar' ? dateRaw : null;
    renderSchedule();
}

// The first booking after the month on screen, for the "Go to …" button.
function nextScheduleBookingAfterMonth() {
    const key = scheduleMonthKey();
    return [...scheduleBookings]
        .filter(b => (b.date_raw || '').slice(0, 7) > key)
        .sort((a, b) => `${a.date_raw}${a.start_time}`.localeCompare(`${b.date_raw}${b.start_time}`))[0] || null;
}

function scheduleEmptyMonth() {
    const month = calendarDate.toLocaleDateString('en-US', { month: 'long' });
    const next  = nextScheduleBookingAfterMonth();
    const nextLabel = next
        ? localDateFromRaw(next.date_raw).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })
        : '';
    return `
        <div class="apt-empty">
            <span class="apt-empty-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span>
            <p class="apt-empty-title">Nothing booked in ${escapeHtml(month)}</p>
            ${next
                ? `<p class="apt-empty-text">Your next appointment is on ${escapeHtml(nextLabel)}.</p>
                   <button type="button" class="sc-next" onclick="jumpToScheduleMonth('${next.date_raw}')">
                       Go to ${escapeHtml(nextLabel)}<i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>`
                : `<p class="apt-empty-text">When you book a treatment, it will show here.</p>
                   <button type="button" class="apt-empty-cta booking-btn" onclick="closeScheduleModal(); document.getElementById('browseSections')?.scrollIntoView({ behavior: 'smooth', block: 'start' })">
                       <i class="fa-solid fa-spa" aria-hidden="true"></i>Browse spas</button>`}
        </div>`;
}

// Same day groups and rows as the Upcoming tab of My appointments.
function renderScheduleRows(list) {
    Object.keys(scheduleMap).forEach(k => delete scheduleMap[k]);
    let i = 0;
    return groupAppointments(list, 'upcoming').map(g => {
        const rows = g.items.map(b => { scheduleMap[i] = b; return renderAppointmentRow(b, i++, 'upcoming', 'scheduleMap'); }).join('');
        const id   = `sc-group-${g.key}`;
        return `
            <section class="apt-group" aria-labelledby="${id}">
                <h3 id="${id}" class="apt-group-title${g.now ? ' apt-group-title--now' : ''}">${escapeHtml(g.title)}</h3>
                <ul class="apt-list">${rows}</ul>
            </section>`;
    }).join('');
}

// ── LIST VIEW: this month, grouped by day ───────────────────────────────────
function renderScheduleList() {
    const container = document.getElementById('scheduleListContent');
    if (!container) return;
    const monthBookings = scheduleInMonth();
    container.innerHTML = monthBookings.length ? renderScheduleRows(monthBookings) : scheduleEmptyMonth();
}

// ── CALENDAR VIEW: month grid, then the chosen day's appointments ──────────
function renderCalendar() {
    const year        = calendarDate.getFullYear();
    const month       = calendarDate.getMonth();
    const today       = getTodayLocal();
    const firstDay    = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const monthList   = scheduleInMonth();
    const counts      = {};
    monthList.forEach(b => { counts[b.date_raw] = (counts[b.date_raw] || 0) + 1; });

    // Default day: today if it's in this month, else the month's first booking.
    if (!scheduleSelected || !scheduleSelected.startsWith(scheduleMonthKey())) {
        const firstBooked = Object.keys(counts).sort()[0] || null;
        scheduleSelected = today.startsWith(scheduleMonthKey()) ? today : firstBooked;
    }

    const cells = [];
    for (let i = 0; i < firstDay; i++) cells.push('<span aria-hidden="true"></span>');
    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const n       = counts[dateStr] || 0;
        const label   = localDateFromRaw(dateStr).toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })
            + (dateStr === today ? ', today' : '')
            + (n ? `, ${n} appointment${n > 1 ? 's' : ''}` : '');
        const classes = ['sc-day', dateStr === today ? 'is-today' : '', n ? 'has-booking' : ''].filter(Boolean).join(' ');
        cells.push(`<button type="button" class="${classes}" data-date="${dateStr}"
            aria-pressed="${dateStr === scheduleSelected ? 'true' : 'false'}" aria-label="${label}"
            ${dateStr < today ? 'disabled' : ''} onclick="selectDay('${dateStr}')">${d}</button>`);
    }
    document.getElementById('calendarGrid').innerHTML = cells.join('');
    renderSelectedDay();
}

function selectDay(dateStr) {
    scheduleSelected = dateStr;
    document.querySelectorAll('#calendarGrid .sc-day').forEach(el =>
        el.setAttribute('aria-pressed', el.dataset.date === dateStr ? 'true' : 'false'));
    renderSelectedDay();
}

function renderSelectedDay() {
    const wrap = document.getElementById('selectedDayBookings');
    if (!wrap) return;
    if (!scheduleInMonth().length) { wrap.innerHTML = scheduleEmptyMonth(); return; }

    const dayList = scheduleBookings.filter(b => b.date_raw === scheduleSelected);
    if (!dayList.length) {
        const day = scheduleSelected
            ? localDateFromRaw(scheduleSelected).toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' })
            : '';
        wrap.innerHTML = `<p class="sc-empty-day">${day ? `Nothing booked on ${escapeHtml(day)}.` : ''} Days with a dot have appointments.</p>`;
        return;
    }
    wrap.innerHTML = renderScheduleRows(dayList);
}

// Keep Tab inside the panel while it is open.
document.getElementById('scheduleModal')?.addEventListener('keydown', (e) => {
    if (e.key !== 'Tab') return;
    const focusable = [...document.querySelectorAll('#scPanel button:not([disabled])')]
        .filter(el => el.offsetParent !== null);
    if (!focusable.length) return;
    const first = focusable[0];
    const last  = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
});

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

/*
  Booking details (UI rework Phase 1): one panel with two views, "details" and
  "reschedule". The reschedule form used to be a second modal stacked on top;
  openRescheduleModal / closeRescheduleModal keep their names (onclick + window
  exports) but now switch views inside the same panel.
  Every class toggled here is written out in welcome.blade.php, because this
  file is not in Tailwind's content globs.
*/
let _bdReturnFocus = null;       // element to refocus when the panel closes
let _bdFormBookingId = null;     // keeps typed form values if the customer goes back and forth

// Toggles 'hidden' and, for flex items, the display class with it.
function bdShow(el, on, display = null) {
    if (!el) return;
    el.classList.toggle('hidden', !on);
    if (display) el.classList.toggle(display, on);
}

// Start of the appointment in the customer's local time (null when unknown).
function bookingStartDate(b) {
    if (!b.date_raw || !b.start_time) return null;
    return new Date(`${b.date_raw}T${b.start_time.slice(0, 8)}`);
}

// Mirrors Booking::canRequestReschedule() on the server, which has the final say.
function bookingCanReschedule(b) {
    const start = bookingStartDate(b);
    const serverSays = b.can_reschedule ?? (b.status === 'reserved');
    return serverSays && b.status === 'reserved' && !!start && start.getTime() > Date.now();
}

function openBookingDetailsModal(booking) {
    selectedBooking = booking;
    _bdReturnFocus  = document.activeElement;

    document.getElementById('detailTreatment').textContent = booking.treatment;
    document.getElementById('detailDate').textContent      = booking.date;
    document.getElementById('detailTime').textContent      =
        `${formatTime(booking.start_time)} – ${formatTime(booking.end_time)}`;
    document.getElementById('detailSpaName').textContent   =
        `${booking.spa_name} · ${booking.branch_name}`;

    const serviceRow = document.getElementById('detailServiceRow');
    document.getElementById('detailServiceType').textContent = booking.service_type || '';
    bdShow(serviceRow, !!booking.is_home_service, 'flex');

    const statusEl = document.getElementById('detailStatus');
    const st = customerStatus(booking);
    statusEl.textContent = st.label;
    statusEl.className   = `apt-pill apt-pill--${st.key}`;

    renderDetailTherapist(booking);
    renderDetailReschedule(booking);

    bdShowView('details', false);
    document.getElementById('bookingDetailsModal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    document.getElementById('bdBody').scrollTop = 0;

    // Land keyboard and screen-reader users on the title, inside the dialog,
    // without a focus ring flashing on the close button.
    requestAnimationFrame(() => document.getElementById('bdTitle')?.focus());
}

function renderDetailReschedule(booking) {
    const wrap  = document.getElementById('detailRescheduleStatus');
    const icon  = document.getElementById('detailRescheduleIcon');
    const text  = document.getElementById('detailRescheduleStatusText');
    const btn   = document.getElementById('openRescheduleBtn');
    const closed = document.getElementById('detailRescheduleClosed');

    const notices = {
        pending: {
            box:  'flex items-start gap-3 p-3 rounded-2xl ring-1 bg-amber-50 ring-amber-200 dark:bg-amber-900/20 dark:ring-amber-800',
            icon: 'mt-0.5 fa-solid fa-clock-rotate-left text-amber-700 dark:text-amber-300',
            text: 'text-sm text-amber-800 dark:text-amber-200',
            msg:  'Your reschedule request is waiting for the branch to review it.',
        },
        approved: {
            box:  'flex items-start gap-3 p-3 rounded-2xl ring-1 bg-emerald-50 ring-emerald-200 dark:bg-emerald-900/20 dark:ring-emerald-800',
            icon: 'mt-0.5 fa-solid fa-circle-check text-emerald-700 dark:text-emerald-300',
            text: 'text-sm text-emerald-800 dark:text-emerald-200',
            msg:  'Your reschedule was approved. The date and time above are the new ones.',
        },
        rejected: {
            box:  'flex items-start gap-3 p-3 rounded-2xl ring-1 bg-red-50 ring-red-200 dark:bg-red-900/20 dark:ring-red-800',
            icon: 'mt-0.5 fa-solid fa-circle-xmark text-red-700 dark:text-red-300',
            text: 'text-sm text-red-800 dark:text-red-200',
            msg:  'Your last reschedule request was declined. Your original schedule still stands.',
        },
        // Booking lifecycle fix, Phase 3 (D5): nobody reviewed it before the
        // original start time. Not a decline, so not red.
        expired: {
            box:  'flex items-start gap-3 p-3 rounded-2xl ring-1 bg-gray-50 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700',
            icon: 'mt-0.5 fa-solid fa-hourglass-end text-gray-500 dark:text-gray-400',
            text: 'text-sm text-gray-700 dark:text-gray-300',
            msg:  "Your reschedule request wasn't reviewed before your appointment, so your original schedule stands.",
        },
    };

    // A pending request past its deadline counts as expired even before the
    // server job marks it (reschedule_pending is false then).
    const shownStatus = booking.reschedule_status === 'pending' && !rescheduleWaiting(booking)
        ? 'expired'
        : booking.reschedule_status;
    const notice = notices[shownStatus] || null;
    if (notice) {
        wrap.className   = notice.box;
        icon.className   = notice.icon;
        text.className   = notice.text;
        text.textContent = notice.msg;
    } else {
        wrap.className = 'hidden';
    }

    const active      = ['reserved', 'pending', 'ongoing'].includes(booking.status);
    const canAsk      = bookingCanReschedule(booking);
    const waiting     = rescheduleWaiting(booking);
    const showButton  = canAsk && !waiting;

    const showCancel  = bookingCanCancel(booking);

    bdShow(btn, showButton, 'inline-flex');
    bdShow(document.getElementById('openCancelBtn'), showCancel, 'inline-flex');
    bdShow(document.getElementById('bdFooter'), showButton || showCancel);
    renderDetailCancel(booking);

    // Started (or past its start) but not finished: tell them how to change it.
    bdShow(closed, active && !canAsk && !waiting);
    document.getElementById('detailRescheduleClosedPhone').textContent =
        booking.branch_phone ? ` at ${booking.branch_phone}` : '';
}

// Therapist card + arrival code (staff verification D6, D7).
function renderDetailTherapist(booking) {
    const card     = booking.therapist_card || null;
    const nameEl   = document.getElementById('detailTherapist');
    const photo    = document.getElementById('detailTherapistPhoto');
    const initials = document.getElementById('detailTherapistInitials');
    const icon     = document.getElementById('detailTherapistIcon');
    const badge    = document.getElementById('detailTherapistVerified');
    const note     = document.getElementById('detailTherapistNote');

    const active   = ['reserved', 'pending', 'ongoing'].includes(booking.status);
    const homeCard = !!booking.is_home_service && active;
    const assigned = !!card || (booking.therapist_assigned ?? (booking.therapist && booking.therapist !== 'Not Assigned'));

    // Everything except an active home visit: a plain therapist row.
    document.getElementById('detailTherapistPlain').textContent =
        card ? card.name : (booking.therapist || 'Not assigned yet');
    bdShow(document.getElementById('detailTherapistRow'), !homeCard);

    bdShow(document.getElementById('detailArrival'), homeCard);
    if (!homeCard) return;

    // Step 1: who to expect.
    nameEl.textContent = card ? card.name : 'Not assigned yet';

    const hasPhoto = !!(card && card.photo_url);
    if (hasPhoto) {
        photo.src = card.photo_url;
        photo.alt = `Photo of ${card.name}`;
    } else {
        photo.removeAttribute('src');
        photo.alt = '';
    }
    bdShow(photo, hasPhoto);
    initials.textContent = card ? (card.initials || '') : '';
    bdShow(initials, !!card && !hasPhoto, 'flex');
    bdShow(icon, !card, 'flex');
    bdShow(badge, !!(card && card.verified), 'inline-flex');

    note.textContent = assigned ? '' : 'Their photo will show here once the branch assigns your therapist.';
    bdShow(note, note.textContent !== '');

    // Step 2: the code, one digit per box.
    const code   = (booking.arrival_code || '').toString();
    const digits = document.querySelectorAll('#detailArrivalCode [data-code-digit]');
    digits.forEach((el, i) => { el.textContent = code[i] ?? ''; });
    document.getElementById('detailArrivalCodeSr').textContent =
        code ? `Arrival code: ${code.split('').join(' ')}` : '';
    bdShow(document.getElementById('detailArrivalCodeWrap'), code !== '', 'flex');

    // After the branch confirms arrival, the code is no longer sent.
    const done = document.getElementById('detailArrivalDone');
    document.getElementById('detailArrivalDoneText').textContent =
        booking.arrival_confirmed_at ? `Arrival confirmed at ${booking.arrival_confirmed_at}.` : '';
    bdShow(done, !!booking.arrival_confirmed_at, 'flex');
    bdShow(document.getElementById('detailArrivalSafety'), !booking.arrival_confirmed_at, 'flex');

    const call = document.getElementById('detailArrivalCall');
    if (booking.branch_phone) {
        call.href = `tel:${booking.branch_phone.replace(/[^\d+]/g, '')}`;
        call.setAttribute('aria-label', `Call the branch at ${booking.branch_phone}`);
    } else {
        call.removeAttribute('href');
    }
    bdShow(call, !!booking.branch_phone, 'inline-flex');
}

// Switches the panel between its three views: details, the reschedule form
// and the cancel confirmation (customer cancellation, Nov 2026).
function bdShowView(view, moveFocus = true) {
    const form    = view === 'reschedule';
    const cancel  = view === 'cancel';
    const details = !form && !cancel;

    bdShow(document.getElementById('bdDetailsView'), details);
    bdShow(document.getElementById('bdRescheduleView'), form);
    bdShow(document.getElementById('bdCancelView'), cancel);
    bdShow(document.getElementById('bdBackBtn'), !details, 'flex');
    bdShow(document.getElementById('detailStatus'), details);
    document.getElementById('bdTitle').textContent =
        form ? 'Request reschedule' : cancel ? 'Cancel booking' : 'Booking details';

    bdShow(document.getElementById('bdDetailActions'), details, 'flex');
    bdShow(document.getElementById('bdCancelActions'), cancel, 'flex');
    bdShow(document.getElementById('rescheduleSubmitBtn'), form, 'inline-flex');
    if (!details) bdShow(document.getElementById('bdFooter'), true);
    else if (selectedBooking) renderDetailReschedule(selectedBooking);

    document.getElementById('bdBody').scrollTop = 0;
    if (moveFocus) {
        const visible = (id) => document.getElementById(id)?.offsetParent ? document.getElementById(id) : null;
        requestAnimationFrame(() => (form
            ? document.getElementById('rescheduleDate')
            : cancel
                ? document.getElementById('bdTitle')
                : visible('openRescheduleBtn') || visible('openCancelBtn') || document.getElementById('bdTitle'))?.focus());
    }
}

function bdIsFormView() {
    return !document.getElementById('bdRescheduleView').classList.contains('hidden')
        || !document.getElementById('bdCancelView').classList.contains('hidden');
}

// Backdrop: closes the details view, but never discards a half-typed form.
function bdBackdropClick() {
    if (bdIsFormView()) return;
    closeBookingDetailsModal();
}

// Escape: form → back to details; details → close.
function bdEscape() {
    if (bdIsFormView()) closeRescheduleModal();
    else closeBookingDetailsModal();
}

function closeBookingDetailsModal() {
    document.getElementById('bookingDetailsModal').classList.add('hidden');
    bdShowView('details', false);

    // Only unlock the page if no other overlay is still open underneath.
    const stillOpen = ['appointmentsModal', 'scheduleModal']
        .some(id => !document.getElementById(id)?.classList.contains('hidden'));
    if (!stillOpen) document.body.classList.remove('overflow-hidden');

    if (_bdReturnFocus && document.contains(_bdReturnFocus)) _bdReturnFocus.focus();
    _bdReturnFocus = null;
}

// Keep Tab inside the panel while it is open.
document.getElementById('bookingDetailsModal')?.addEventListener('keydown', (e) => {
    if (e.key !== 'Tab') return;
    const focusable = [...document.querySelectorAll(
        '#bdPanel a[href], #bdPanel button:not([disabled]), #bdPanel input:not([type="hidden"]):not([disabled]), #bdPanel textarea:not([disabled])'
    )].filter(el => el.offsetParent !== null);
    if (!focusable.length) return;

    const first = focusable[0];
    const last  = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
});

// =====================================================
// RESCHEDULE (a view inside the booking details panel)
// =====================================================
function openRescheduleModal() {
    if (!selectedBooking || !bookingCanReschedule(selectedBooking)) return;

    document.getElementById('rescheduleCurrentSchedule').textContent =
        `${selectedBooking.date} at ${formatTime(selectedBooking.start_time)}`;

    // Fresh form for a different booking; keep what was typed for the same one.
    if (_bdFormBookingId !== selectedBooking.id) {
        _bdFormBookingId = selectedBooking.id;
        document.getElementById('rescheduleBookingId').value = selectedBooking.id;

        const timeInput = document.getElementById('rescheduleTime');
        document.getElementById('rescheduleDate').min   = getTodayLocal();
        document.getElementById('rescheduleDate').value = '';
        timeInput.value    = '';
        timeInput.disabled = false;
        timeInput.removeAttribute('min');
        timeInput.removeAttribute('max');
        document.getElementById('rescheduleReason').value = '';
        updateRescheduleReasonCount();

        document.getElementById('rescheduleError').classList.add('hidden');
        document.getElementById('rescheduleTimeError').classList.add('hidden');
        document.getElementById('rescheduleSubmitBtn').disabled = false;
    }

    bdShowView('reschedule');
}

// "Back": returns to the details view without losing what was typed.
function closeRescheduleModal() {
    bdShowView('details');
}

function updateRescheduleReasonCount() {
    const len = document.getElementById('rescheduleReason').value.length;
    document.getElementById('rescheduleReasonCount').textContent =
        `At least 10 characters · ${len} / 1000`;
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

document.getElementById('rescheduleReason')?.addEventListener('input', updateRescheduleReasonCount);

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
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> <span>Sending…</span>';

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

        _bdFormBookingId = null;   // next time, start with an empty form
        closeBookingDetailsModal();
        showSpaToast('Reschedule request sent. The branch will review it.', 'success');

        loadAppointments();
        loadSchedule();

    } catch (err) {
        errorText.textContent = 'Network error. Please try again.';
        errorEl.classList.remove('hidden');
    } finally {
        submitBtn.disabled  = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane" aria-hidden="true"></i> <span>Send request</span>';
    }
}

// =====================================================
// CANCEL BOOKING (customer cancellation, Nov 2026)
// A third view inside the booking details panel. The rule and the amounts
// come from the server (booking.cancel, CustomerCancellation); this file only
// shows them. The server checks everything again on submit.
// =====================================================
let _bdCancelBookingId = null;   // keeps a typed reason if the customer goes back and forth

function bookingCanCancel(b) {
    const start = bookingStartDate(b || {});
    return !!(b && b.cancel && b.cancel.can_cancel) && b.status === 'reserved'
        && !!start && start.getTime() > Date.now();
}

// Why it isn't free, as a sentence (null when it is free).
function cancelNotFreeReason(q) {
    switch (q.reason) {
        case 'window_closed': return 'Your free cancellation window has ended.';
        case 'free_used':     return "You've already used your free cancellation in the last 30 days.";
        case 'no_window':     return 'This booking was made less than 3 hours before the appointment.';
        default:              return null;
    }
}

function renderDetailCancel(booking) {
    const note     = document.getElementById('detailCancelNote');
    const noteIcon = document.getElementById('detailCancelNoteIcon');
    const noteText = document.getElementById('detailCancelNoteText');
    const q        = booking.cancel || {};

    // While a cancel is possible: always say what it would cost.
    if (bookingCanCancel(booking)) {
        const fee = formatPeso(q.kept || 0);
        if (q.free) {
            noteIcon.className   = 'mt-0.5 fa-solid fa-circle-check text-emerald-700 dark:text-emerald-300';
            noteText.textContent = `Free cancellation until ${formatCancelDateTime(new Date(q.free_until))}. You'd get back everything you paid.`;
        } else {
            noteIcon.className = 'mt-0.5 fa-solid fa-circle-info text-[#8B7355] dark:text-[#C4A97D]';
            const lead = {
                free_used:     `You've used your free cancellation. It's available again on ${q.free_again_on ? formatCancelDate(new Date(q.free_again_on)) : 'a later date'}.`,
                window_closed: `Free cancellation ended ${q.free_until ? formatCancelDateTime(new Date(q.free_until)) : 'earlier'}.`,
                no_window:     "This booking can't be cancelled for free; it was made less than 3 hours before the appointment.",
            }[q.reason] || '';
            const cost = q.refund > 0
                ? `Cancelling now keeps the ${fee} reservation fee and refunds ${formatPeso(q.refund)}.`
                : `Cancelling now keeps the ${fee} reservation fee.`;
            noteText.textContent = `${lead} ${cost}`;
        }
    }
    bdShow(note, bookingCanCancel(booking), 'flex');

    // After a cancel: who did it, and where the money stands.
    const box = document.getElementById('detailCancelled');
    const c   = booking.cancelled || null;
    bdShow(box, !!c);
    if (!c) return;

    document.getElementById('detailCancelledTitle').textContent =
        c.by === 'customer' ? `You cancelled this booking${c.on ? ` on ${c.on}` : ''}.`
        : c.by === 'staff'  ? 'The spa cancelled this booking.'
        : c.by === 'no_show' ? "You didn't arrive for this appointment, so it was marked as a no-show."
        : 'This booking was cancelled.';

    const refundRow  = document.getElementById('detailCancelledRefund');
    const refundIcon = document.getElementById('detailCancelledRefundIcon');
    const refundText = document.getElementById('detailCancelledRefundText');
    if (c.refund_amount) {
        const sent = c.refund_status === 'sent';
        refundRow.className  = `flex items-start gap-2 text-sm ${sent ? 'text-emerald-800 dark:text-emerald-300' : 'text-amber-800 dark:text-amber-300'}`;
        refundIcon.className = `mt-0.5 fa-solid ${sent ? 'fa-circle-check' : 'fa-clock'}`;
        refundText.textContent = sent
            ? `Refund of ${formatPeso(c.refund_amount)} sent${c.refund_sent_on ? ` on ${c.refund_sent_on}` : ''}.`
            : `Refund of ${formatPeso(c.refund_amount)} is being processed. Levictas will send it back to you.`;
    } else {
        refundRow.className = 'hidden';
    }

    const kept = document.getElementById('detailCancelledKept');
    kept.textContent = c.kept > 0 ? `The ${formatPeso(c.kept)} reservation fee was kept.` : '';
    bdShow(kept, c.kept > 0);
}

function openCancelView() {
    if (!selectedBooking || !bookingCanCancel(selectedBooking)) return;
    const b = selectedBooking;
    const q = b.cancel;

    document.getElementById('cancelBookingSummary').textContent =
        `${b.treatment} · ${b.date} at ${formatTime(b.start_time)}`;

    const why = cancelNotFreeReason(q);
    document.getElementById('cancelExplain').textContent = q.free
        ? "You're within your free cancellation window, so you'll get back everything you paid. Free cancellation can be used once every 30 days."
        : q.refund > 0
            ? `${why} The reservation fee is kept and the rest of what you paid is refunded.`
            : `${why} The reservation fee you paid is kept, so nothing is refunded.`;

    document.getElementById('cancelPaid').textContent   = formatPeso(q.paid);
    document.getElementById('cancelKept').textContent   = formatPeso(q.kept);
    document.getElementById('cancelRefund').textContent = formatPeso(q.refund);
    bdShow(document.getElementById('cancelKeptRow'), q.kept > 0, 'flex');
    bdShow(document.getElementById('cancelRefundHow'), q.refund > 0);

    if (_bdCancelBookingId !== b.id) {
        _bdCancelBookingId = b.id;
        document.getElementById('cancelReason').value = '';
    }
    document.getElementById('cancelError').classList.add('hidden');
    document.getElementById('cancelSubmitBtn').disabled = false;

    bdShowView('cancel');
}

function closeCancelView() {
    bdShowView('details');
}

async function submitCancelBooking() {
    if (!selectedBooking) return;

    const btn       = document.getElementById('cancelSubmitBtn');
    const errorEl   = document.getElementById('cancelError');
    const errorText = document.getElementById('cancelErrorText');
    const reason    = document.getElementById('cancelReason').value.trim();

    errorEl.classList.add('hidden');
    btn.disabled  = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> <span>Cancelling…</span>';

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const response  = await fetch(`/appointments/${selectedBooking.id}/cancel`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken ?? '',
            },
            body: JSON.stringify({ reason: reason || null }),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            errorText.textContent = data.message ?? 'Something went wrong. Please try again.';
            errorEl.classList.remove('hidden');
            return;
        }

        // The booking form's note should know the free cancellation is used.
        const note = document.getElementById('recapCancelNote');
        if (note) note.dataset.freeAgainOn = data.free_again_on || '';

        _bdCancelBookingId = null;
        closeBookingDetailsModal();
        showSpaToast(data.message || 'Booking cancelled.', 'success');

        loadAppointments();
        loadSchedule();
    } catch (err) {
        errorText.textContent = 'Network error. Please try again.';
        errorEl.classList.remove('hidden');
    } finally {
        btn.disabled  = false;
        btn.innerHTML = '<i class="fa-solid fa-ban" aria-hidden="true"></i> <span>Cancel booking</span>';
    }
}

// =====================================================
// SPAS NEAR YOU
// =====================================================

async function fetchNearbySpas(lat = null, lng = null, locationSource = 'saved') {
    try {
        const params = new URLSearchParams();

        if (lat !== null && lng !== null) {
            params.set('lat', lat);
            params.set('lng', lng);
        }

        const url = params.toString()
            ? `/web-api/spas/nearby?${params.toString()}`
            : '/web-api/spas/nearby';

        const res = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
        }

        const data = await res.json();

        const section = document.getElementById('nearbySection');
        const grid = document.getElementById('nearbyGrid');
        const locationText = document.getElementById('nearbyLocationText');

        if (!section || !grid) return;

        if (!Array.isArray(data) || data.length === 0) {
            section.classList.add('hidden');
            return;
        }

        if (locationText) {
            const locationLabels = {
                current: 'Based on your current location.',
                pinned: 'Based on your pinned location.',
                saved: 'Based on your saved location.',
            };

            locationText.textContent =
                locationLabels[locationSource]
                ?? 'Spas near your location.';
        }

        grid.innerHTML = data.map(spa => {
            const thumb = spaCardThumb(spa);
            const addr = spa.address ?? '';
            const parts = addr.split(',').map(s => s.trim());

            const addrSummary = parts.length >= 3
                ? parts.slice(0, parts.length - 2).slice(-3).join(', ')
                : (addr || 'Location unavailable');

            const escapedData = JSON.stringify(spa).replace(/'/g, '&#39;');

            return `
                <button
                    type="button"
                    class="w-full overflow-hidden text-left transition bg-white dark:bg-gray-800 shadow-sm group rounded-3xl ring-1 ring-black/5 dark:ring-white/10 hover:shadow-2xl"
                    data-open-spa-modal
                    data-spa='${escapedData}'>

                    <div class="relative overflow-hidden">
                        <img
                            src="${thumb}"
                            class="h-56 w-full object-cover transition duration-500 group-hover:scale-[1.04]"
                            alt="${escapeHtml(spa.name)}">

                        <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-black/0 to-transparent"></div>

                        <div class="absolute top-3 left-3 flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/80 dark:bg-gray-900/70 text-[#6F5430] dark:text-[#C4A97D] text-[11px] font-semibold backdrop-blur-sm ring-1 ring-black/5 dark:ring-white/10">
                            <i class="fa-solid fa-location-dot text-[#8B7355] dark:text-[#C4A97D] text-[10px]"></i>
                            ${spa.distance_km} km away
                        </div>
                    </div>

                    <div class="p-5">
                        <h3 class="text-[15px] font-semibold text-[#3C2F23] dark:text-white leading-tight">
                            ${escapeHtml(spa.name)}
                        </h3>

                        ${spa.rating_avg ? `
                            <div class="flex items-center gap-1 mt-1">
                                <i class="fa-solid fa-star text-[#D2A85B] text-xs"></i>

                                <span class="text-xs font-semibold text-[#3C2F23] dark:text-white">
                                    ${spa.rating_avg}
                                </span>

                                <span class="text-xs text-gray-400">
                                    (${spa.rating_count})
                                </span>
                            </div>
                        ` : ''}

                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            ${escapeHtml(addrSummary)}
                        </p>
                    </div>
                </button>
            `;
        }).join('');

        grid.querySelectorAll('[data-open-spa-modal]').forEach(btn => {
            btn.addEventListener('click', () => {
                try {
                    openSpaModal(
                        JSON.parse(btn.getAttribute('data-spa'))
                    );
                } catch (e) {
                    console.error('Invalid nearby spa data', e);
                }
            });
        });

        section.classList.remove('hidden');

    } catch (err) {
        console.warn('Nearby spas unavailable:', err);
    }
}

// =====================================================
// SPAS NEAR YOU: which location to use (UI rework Phase 5)
// =====================================================
/*
  "Find spas near" is chosen per device in the profile window: the saved home
  location (default) or where the device is right now. The browser is never
  asked for location on page load (Chrome flags that, and it's what used to
  overwrite the saved home location). "Right now" only uses GPS silently if
  this site already has permission; otherwise it falls back to home.
*/
const NEARBY_PREF_KEY = 'levictas.nearbyFrom';

function nearbyPref() {
    try { return localStorage.getItem(NEARBY_PREF_KEY) === 'here' ? 'here' : 'home'; }
    catch (e) { return 'home'; }
}

function setNearbyPref(value) {
    try { localStorage.setItem(NEARBY_PREF_KEY, value); } catch (e) { /* storage blocked */ }
}

function getDevicePosition() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) { reject({ code: 'unsupported' }); return; }
        navigator.geolocation.getCurrentPosition(
            p => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }),
            reject,
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 300000 }
        );
    });
}

async function geolocationAlreadyAllowed() {
    try {
        const status = await navigator.permissions?.query({ name: 'geolocation' });
        return status?.state === 'granted';
    } catch (e) { return false; }
}

async function loadNearbySpas() {
    if (!document.getElementById('nearbySection')) return;

    if (nearbyPref() === 'here' && await geolocationAlreadyAllowed()) {
        try {
            const pos = await getDevicePosition();
            fetchNearbySpas(pos.lat, pos.lng, 'current');
            return;
        } catch (e) { /* fall through to home */ }
    }
    fetchNearbySpas(null, null, 'saved');
}

document.addEventListener('DOMContentLoaded', loadNearbySpas);

document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('myAppointmentsBadge')) {
        loadAppointments();
    }
});

// =====================================================
// PROFILE WINDOW (UI rework Phase 5)
// =====================================================
/*
  Opens read-only. Name, phone and home location each have their own editor
  that replaces the main view (Back returns, nothing is kept until Save).
  Each Save sends only that section (ProfileController::updateCustomer) and
  updates the rows in place, without reloading. Photo upload still posts the
  old form (the page reloads and this window reopens).
*/
let pfUser          = null;
let pfView          = 'main';
let pfReturnFocus   = null;
let pfPreviewMap    = null;
let pfPreviewMarker = null;
let pfEditMap       = null;
let pfEditMarker    = null;
let pfDraft         = { address: '', lat: null, lng: null, addressFromPin: false };
let pfLastGeocodeAt = 0;

const PF_VIEWS = {
    main:     { el: 'pfMain',         title: 'Your profile',  save: null },
    name:     { el: 'pfEditName',     title: 'Name',          save: 'Save name' },
    phone:    { el: 'pfEditPhone',    title: 'Phone',         save: 'Save phone' },
    location: { el: 'pfEditLocation', title: 'Home location', save: 'Save location' },
};

function pfLoadUser() {
    if (pfUser) return pfUser;
    try { pfUser = JSON.parse(document.getElementById('pfData')?.textContent || 'null'); }
    catch (e) { pfUser = null; }
    return pfUser;
}

function pfInCavite(lat, lng) {
    return lat >= PROFILE_CAVITE_LAT_MIN && lat <= PROFILE_CAVITE_LAT_MAX
        && lng >= PROFILE_CAVITE_LNG_MIN && lng <= PROFILE_CAVITE_LNG_MAX;
}

function pfFormatPhone(p) {
    return /^09\d{9}$/.test(p || '') ? `${p.slice(0, 4)} ${p.slice(4, 7)} ${p.slice(7)}` : (p || '');
}

function pfToggle(el, on, display = null) {
    if (!el) return;
    el.classList.toggle('hidden', !on);
    if (display) el.classList.toggle(display, on);
}

function pfSetMuted(el, text, muted) {
    el.textContent = text;
    el.classList.toggle('text-gray-500', muted);
    el.classList.toggle('dark:text-gray-400', muted);
    el.classList.toggle('font-normal', muted);
}

// Fills the read-only rows from pfUser.
function pfRenderMain() {
    const u = pfLoadUser();
    if (!u) return;

    document.getElementById('pfRowName').textContent = u.name || '';
    pfRenderNameRule();
    const heading = document.getElementById('pfNameHeading');
    if (heading) heading.textContent = u.name || '';
    document.querySelectorAll('[data-account-name]').forEach(el => { el.textContent = u.name || ''; });

    pfSetMuted(document.getElementById('pfRowPhone'), u.phone ? pfFormatPhone(u.phone) : 'Not added', !u.phone);

    const hasPin = u.latitude !== null && u.longitude !== null;
    pfSetMuted(document.getElementById('pfRowLocation'), u.address || (hasPin ? 'Pinned on the map' : 'Not set'), !u.address && !hasPin);
    pfToggle(document.getElementById('pfMapPreviewWrap'), hasPin);
    if (hasPin) requestAnimationFrame(() => pfRenderPreviewMap(u.latitude, u.longitude));

    // Find spas near
    const pref = nearbyPref();
    document.querySelectorAll('input[name="pfNearbyFrom"]').forEach(r => { r.checked = r.value === pref; });
    pfNearbyHint(pref);
}

// Name: once every 30 days, with 24 hours to fix a typo (User::guardNameChange).
function pfDate(iso, withTime = false) {
    const d = new Date(iso);
    const date = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    return withTime ? `${date}, ${d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}` : date;
}

function pfRenderNameRule() {
    const u = pfLoadUser();
    const locked = !!u.name_locked_until && new Date(u.name_locked_until) > new Date();
    const fixUntil = u.name_correction_until && new Date(u.name_correction_until) > new Date() ? u.name_correction_until : null;
    const days = u.name_change_days || 30;

    pfToggle(document.getElementById('pfNameEdit'), !locked, 'inline-flex');
    pfToggle(document.getElementById('pfNameLock'), locked, 'inline-flex');

    const note = document.getElementById('pfNameNote');
    note.textContent = locked
        ? `You can change your name again on ${pfDate(u.name_locked_until)}.`
        : (fixUntil ? `Made a typo? You can still fix it until ${pfDate(fixUntil, true)}.` : '');
    pfToggle(note, note.textContent !== '');

    document.getElementById('pfNameRuleText').textContent = fixUntil
        ? `You can fix your name until ${pfDate(fixUntil, true)} without waiting. After that, you can change it once every ${days} days.`
        : `You can change your name once every ${days} days. If you make a typo, you'll have 24 hours to fix it.`;
}

// One line under the switch that says what the current choice means.
function pfNearbyHint(pref) {
    const u = pfLoadUser();
    const hasPin = u && u.latitude !== null && u.longitude !== null;
    document.getElementById('pfNearbyHomeHint').textContent = pref === 'here'
        ? "Showing spas near where this device is right now. Your saved home location doesn't change."
        : (hasPin ? 'Showing spas near your saved home location.' : 'Add your home location above to see spas near you.');
}

// A small map that only shows the pin: no dragging, zooming or tapping.
function pfRenderPreviewMap(lat, lng) {
    const el = document.getElementById('pfMapPreview');
    if (!el || typeof L === 'undefined') return;
    if (!pfPreviewMap) {
        pfPreviewMap = L.map(el, {
            dragging: false, touchZoom: false, scrollWheelZoom: false, doubleClickZoom: false,
            boxZoom: false, keyboard: false, zoomControl: false, attributionControl: true,
        });
        pfPreviewMap.attributionControl.setPrefix(false);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19, attribution: '&copy; OpenStreetMap',
        }).addTo(pfPreviewMap);
    }
    pfPreviewMap.setView([lat, lng], 15);
    if (pfPreviewMarker) pfPreviewMarker.setLatLng([lat, lng]);
    else pfPreviewMarker = L.marker([lat, lng], { interactive: false, keyboard: false }).addTo(pfPreviewMap);
    pfPreviewMap.invalidateSize();
}

function openProfileModal() {
    const modal = document.getElementById('profileModal');
    if (!modal) return;
    pfReturnFocus = document.activeElement;
    pfLoadUser();
    pfNearbyStatus('', 'ok');   // don't show a message left over from last time
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    pfShowView('main', false);
    requestAnimationFrame(() => document.getElementById('pfNameHeading')?.focus());
}

function closeProfileModal() {
    const modal = document.getElementById('profileModal');
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    pfView = 'main';
    if (pfReturnFocus && document.contains(pfReturnFocus)) pfReturnFocus.focus();
    pfReturnFocus = null;
}

// Backdrop closes the main view; in an editor it does nothing, so a stray
// tap can't throw away what was typed.
function pfBackdropClick() {
    if (pfView === 'main') closeProfileModal();
}

function pfEscape() {
    if (pfView === 'main') closeProfileModal();
    else pfShowView('main');
}

function pfClearErrors() {
    document.querySelectorAll('#pfPanel [id^="pfErr-"]').forEach(el => { el.textContent = ''; el.classList.add('hidden'); });
    document.querySelectorAll('#pfPanel [aria-invalid]').forEach(el => el.removeAttribute('aria-invalid'));
}

// Moves focus to the first field with an error, so keyboard and screen-reader
// users land on what needs fixing.
function pfFocusFirstError() {
    const input = document.querySelector('#pfPanel [aria-invalid="true"]');
    if (input && input.offsetParent !== null) input.focus();
    else document.querySelector('#pfPanel [id^="pfErr-"]:not(.hidden)')?.scrollIntoView({ block: 'nearest' });
}

function pfShowError(field, message) {
    const el = document.getElementById(`pfErr-${field}`);
    if (!el) return false;
    el.textContent = message;
    el.classList.remove('hidden');
    const input = { first_name: 'pfFirstName', middle_name: 'pfMiddleName', last_name: 'pfLastName', suffix: 'pfSuffix', phone: 'pfPhone', address: 'address' }[field];
    document.getElementById(input)?.setAttribute('aria-invalid', 'true');
    return true;
}

// Switches between the main view and one editor. Editors always start from
// what's saved, so Back/Cancel is a true discard.
function pfShowView(view, moveFocus = true) {
    const u = pfLoadUser();
    pfView = view;
    Object.entries(PF_VIEWS).forEach(([key, v]) => pfToggle(document.getElementById(v.el), key === view));
    document.getElementById('pfTitle').textContent = PF_VIEWS[view].title;
    pfToggle(document.getElementById('pfBar'), view !== 'main', 'flex');     // the main view has the warm header instead
    pfToggle(document.getElementById('pfBackBtn'), view !== 'main', 'flex');
    pfToggle(document.getElementById('pfFooter'), view !== 'main');
    document.getElementById('pfSaveLabel').textContent = PF_VIEWS[view].save || 'Save';
    document.getElementById('pfSaveBtn').disabled = false;
    document.getElementById('pfBody').scrollTop = 0;
    pfClearErrors();

    if (view === 'main') pfRenderMain();
    if (view === 'name') {
        document.getElementById('pfFirstName').value  = u.first_name || '';
        document.getElementById('pfMiddleName').value = u.middle_name || '';
        document.getElementById('pfLastName').value   = u.last_name || '';
        document.getElementById('pfSuffix').value     = u.suffix || '';
    }
    if (view === 'phone') document.getElementById('pfPhone').value = u.phone || '';
    if (view === 'location') pfOpenLocationEditor();

    if (!moveFocus) return;
    const first = { main: 'pfNameHeading', name: 'pfFirstName', phone: 'pfPhone', location: 'pfUseHere' }[view];
    requestAnimationFrame(() => document.getElementById(first)?.focus());
}

// ── Saving ─────────────────────────────────────────────────────────────────
function pfPayload() {
    if (pfView === 'name') return {
        section: 'name',
        first_name:  document.getElementById('pfFirstName').value.trim(),
        middle_name: document.getElementById('pfMiddleName').value.trim(),
        last_name:   document.getElementById('pfLastName').value.trim(),
        suffix:      document.getElementById('pfSuffix').value.trim(),
    };
    if (pfView === 'phone') return { section: 'phone', phone: document.getElementById('pfPhone').value.trim() };
    if (pfView === 'location') return {
        section: 'location',
        address:   document.getElementById('address').value.trim(),
        latitude:  pfDraft.lat,
        longitude: pfDraft.lng,
    };
    return null;
}

// Checks the browser can do before sending; the server checks again.
function pfCheck(data) {
    const problems = [];
    if (data.section === 'name') {
        if (!data.first_name) problems.push(['first_name', 'Enter your first name.']);
        if (!data.last_name)  problems.push(['last_name', 'Enter your last name.']);
    }
    if (data.section === 'phone' && data.phone && !/^09\d{9}$/.test(data.phone)) {
        problems.push(['phone', 'Enter 11 digits starting with 09, like 09171234567.']);
    }
    if (data.section === 'location' && (data.latitude === null || data.longitude === null)) {
        problems.push(['latitude', 'Place your pin on the map, search for your address, or use your current location.']);
    }
    problems.forEach(([field, message]) => pfShowError(field, message));
    pfFocusFirstError();
    return problems.length === 0;
}

async function pfSave() {
    const data = pfPayload();
    if (!data) return;
    pfClearErrors();
    if (!pfCheck(data)) return;

    const btn   = document.getElementById('pfSaveBtn');
    const label = document.getElementById('pfSaveLabel');
    const idle  = label.textContent;
    btn.disabled = true;
    label.textContent = 'Saving…';

    try {
        const res = await fetch(pfLoadUser().save_url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            body: JSON.stringify(data),
        });
        const body = await res.json().catch(() => ({}));

        if (res.status === 422) {
            const errors = body.errors || {};
            const shown = Object.entries(errors).map(([f, m]) => pfShowError(f, m[0])).some(Boolean);
            if (!shown) showSpaToast(body.message || 'Please check what you entered.', 'error');
            pfFocusFirstError();
            return;
        }
        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        pfApplySaved(body.user || {}, data.section);
        showSpaToast({ name: 'Name saved.', phone: 'Phone saved.', location: 'Home location saved.' }[data.section], 'success');
        pfShowView('main');
    } catch (err) {
        console.error('Profile save failed:', err);
        showSpaToast("Couldn't save. Check your connection and try again.", 'error');
    } finally {
        btn.disabled = false;
        label.textContent = idle;
    }
}

// Updates the page after a save: the rows, the nav name, and the booking
// form's default phone and address (only where the customer hadn't typed
// something else).
function pfApplySaved(saved, section) {
    const u = pfLoadUser();
    const before = { ...u };
    Object.assign(u, saved);

    if (section === 'phone') {
        const phone = document.getElementById('bookingCustomerPhone');
        if (phone) {
            if (!phone.value || phone.value === before.phone) phone.value = u.phone || '';
            phone.dataset.defaultPhone = u.phone || '';
        }
    }
    if (section === 'location') {
        const addr = document.getElementById('bookingAddressInput');
        if (addr) {
            if (!addr.value || addr.value === before.address) addr.value = u.address || '';
            addr.dataset.defaultAddress = u.address || '';
        }
        if (nearbyPref() === 'home') fetchNearbySpas(null, null, 'saved');
    }
}

// Enter saves in the name and phone editors.
document.getElementById('pfEditName')?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); pfSave(); } });
document.getElementById('pfEditPhone')?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); pfSave(); } });
document.getElementById('pfPhone')?.addEventListener('input', function () { this.value = this.value.replace(/\D/g, '').slice(0, 11); });

// ── Home location editor ──────────────────────────────────────────────────
function pfOpenLocationEditor() {
    const u = pfLoadUser();
    pfDraft = { address: u.address || '', lat: u.latitude, lng: u.longitude, addressFromPin: false };
    document.getElementById('address').value = pfDraft.address;
    pfClearResults();
    pfLocStatus('Tap the map to place your pin, then drag it to fine-tune. Cavite only.');

    requestAnimationFrame(() => {
        pfInitEditMap();
        if (!pfEditMap) return;
        pfEditMap.invalidateSize();
        if (pfDraft.lat !== null && pfDraft.lng !== null) {
            pfSetEditPin(pfDraft.lat, pfDraft.lng);
            pfEditMap.setView([pfDraft.lat, pfDraft.lng], 16);
        } else {
            if (pfEditMarker) { pfEditMap.removeLayer(pfEditMarker); pfEditMarker = null; }
            pfEditMap.setView([14.2456, 120.8786], 11);   // Cavite
        }
    });
}

function pfInitEditMap() {
    if (pfEditMap || typeof L === 'undefined' || !document.getElementById('map')) return;
    const bounds = L.latLngBounds([PROFILE_CAVITE_LAT_MIN, PROFILE_CAVITE_LNG_MIN], [PROFILE_CAVITE_LAT_MAX, PROFILE_CAVITE_LNG_MAX]);
    pfEditMap = L.map('map', { maxBounds: bounds, maxBoundsViscosity: 1.0, scrollWheelZoom: false })
        .setView([14.2456, 120.8786], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(pfEditMap);
    pfEditMap.on('click', e => pfMovePin(e.latlng.lat, e.latlng.lng));
}

function pfSetEditPin(lat, lng) {
    if (!pfEditMap) return;
    if (pfEditMarker) { pfEditMarker.setLatLng([lat, lng]); return; }
    pfEditMarker = L.marker([lat, lng], { draggable: true, keyboard: true, title: 'Your home location' }).addTo(pfEditMap);
    pfEditMarker.on('dragend', () => {
        const p = pfEditMarker.getLatLng();
        pfMovePin(p.lat, p.lng);
    });
}

function pfLocStatus(text, tone = 'muted') {
    const el = document.getElementById('pfLocStatus');
    if (!el) return;
    el.textContent = text;
    el.className = 'mt-2 text-xs ' + ({
        muted: 'text-gray-600 dark:text-gray-400',
        ok:    'text-emerald-700 dark:text-emerald-300',
        error: 'text-red-700 dark:text-red-400',
    }[tone]);
}

// A tap or drag inside the editor: move the pin; fill the address only if
// the customer hasn't typed their own.
function pfMovePin(lat, lng) {
    if (!pfInCavite(lat, lng)) {
        pfLocStatus("That spot is outside Cavite. Choose a place within the province.", 'error');
        if (pfEditMarker && pfDraft.lat !== null) pfEditMarker.setLatLng([pfDraft.lat, pfDraft.lng]);
        return;
    }
    pfDraft.lat = lat;
    pfDraft.lng = lng;
    pfSetEditPin(lat, lng);
    pfClearErrors();
    pfLocStatus('Pin placed. Save location to keep it.', 'ok');

    const field = document.getElementById('address');
    if (!field.value.trim() || pfDraft.addressFromPin) pfFillAddressFromPin(lat, lng);
}

// OpenStreetMap's free geocoder allows at most one request per second and no
// search-as-you-type, so lookups only happen on a tap, a drag or Search.
async function pfGeocode(url) {
    const wait = 1100 - (Date.now() - pfLastGeocodeAt);
    if (wait > 0) await new Promise(r => setTimeout(r, wait));
    pfLastGeocodeAt = Date.now();
    const res = await fetch(url, { headers: { 'Accept': 'application/json', 'Accept-Language': 'en' } });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}

async function pfFillAddressFromPin(lat, lng) {
    try {
        const data = await pfGeocode(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&zoom=18&addressdetails=1`);
        if (data?.display_name && pfDraft.lat === lat && pfDraft.lng === lng) {
            document.getElementById('address').value = data.display_name;
            pfDraft.addressFromPin = true;
        }
    } catch (e) {
        console.warn('Reverse geocoding failed:', e);
    }
}

document.getElementById('address')?.addEventListener('input', () => { pfDraft.addressFromPin = false; });
document.getElementById('address')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); pfSearchAddress(); }
});

function pfClearResults() {
    const list = document.getElementById('pfSearchResults');
    if (list) { list.innerHTML = ''; list.classList.add('hidden'); }
}

async function pfSearchAddress() {
    const query = document.getElementById('address').value.trim();
    const btn   = document.getElementById('pfSearchBtn');
    const list  = document.getElementById('pfSearchResults');
    pfClearErrors();
    if (query.length < 3) { pfShowError('address', 'Type at least 3 letters of your barangay, subdivision or street.'); return; }

    btn.disabled = true;
    try {
        const data = await pfGeocode(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query + ', Cavite, Philippines')}&format=json&limit=5&viewbox=${PROFILE_CAVITE_VIEWBOX}&bounded=1&addressdetails=1`);
        const results = (data || []).filter(r => pfInCavite(parseFloat(r.lat), parseFloat(r.lon)));
        list.innerHTML = results.length
            ? results.map((r, i) => `
                <li><button type="button" class="pf-result" data-i="${i}">
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>${escapeHtml(r.display_name)}</span>
                </button></li>`).join('')
            : `<li class="px-3 py-3 text-sm text-gray-600 dark:text-gray-300">No matches in Cavite. Try a nearby landmark, or tap the map.</li>`;
        list.classList.remove('hidden');
        list.querySelectorAll('.pf-result').forEach(b => b.addEventListener('click', () => {
            const r = results[parseInt(b.dataset.i, 10)];
            const lat = parseFloat(r.lat), lng = parseFloat(r.lon);
            document.getElementById('address').value = r.display_name;
            pfDraft.addressFromPin = false;
            pfDraft.lat = lat; pfDraft.lng = lng;
            pfSetEditPin(lat, lng);
            pfEditMap?.setView([lat, lng], 17);
            pfClearResults();
            pfLocStatus('Check the pin and drag it onto your home if needed.', 'ok');
        }));
        list.querySelector('.pf-result')?.focus();
    } catch (e) {
        console.warn('Address search failed:', e);
        pfShowError('address', "Search isn't available right now. Tap the map to place your pin instead.");
    } finally {
        btn.disabled = false;
    }
}

// "Use my current location": the browser asks only now, after the tap.
async function pfUseCurrentLocation() {
    const btn = document.getElementById('pfUseHere');
    const label = btn.querySelector('span');
    btn.disabled = true;
    label.textContent = 'Finding you…';
    try {
        const pos = await getDevicePosition();
        if (!pfInCavite(pos.lat, pos.lng)) {
            pfLocStatus("You seem to be outside Cavite right now. Search for your address or tap the map instead.", 'error');
            return;
        }
        document.getElementById('address').value = '';
        pfDraft.addressFromPin = true;
        pfMovePin(pos.lat, pos.lng);
        pfEditMap?.setView([pos.lat, pos.lng], 17);
        pfLocStatus("Pinned where you are now. Check it's your home, then save.", 'ok');
    } catch (err) {
        pfLocStatus(err?.code === 1
            ? "Location access is blocked for this site. Allow it in your browser's site settings, or search instead."
            : "Couldn't get your location. Search for your address or tap the map instead.", 'error');
    } finally {
        btn.disabled = false;
        label.textContent = 'Use my current location';
    }
}

// ── Find spas near (per device) ───────────────────────────────────────────
function pfNearbyStatus(text, tone) {
    const el = document.getElementById('pfNearbyStatus');
    if (!el) return;
    el.textContent = text;
    el.className = 'mt-2 text-xs ' + (tone === 'error' ? 'text-red-700 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-300');
    el.classList.toggle('hidden', !text);
}

async function pfSetNearbyFrom(value) {
    const u = pfLoadUser();
    if (value === 'home') {
        setNearbyPref('home');
        pfNearbyHint('home');
        fetchNearbySpas(null, null, 'saved');
        pfNearbyStatus(u.latitude !== null ? 'Spas near you now use your home location.' : 'Add your home location to see spas near you.', 'ok');
        return;
    }
    pfNearbyStatus('Asking for your location…', 'ok');
    try {
        const pos = await getDevicePosition();
        setNearbyPref('here');
        pfNearbyHint('here');
        fetchNearbySpas(pos.lat, pos.lng, 'current');
        pfNearbyStatus('Spas near you now use where you are right now.', 'ok');
    } catch (err) {
        setNearbyPref('home');
        pfNearbyHint('home');
        document.querySelectorAll('input[name="pfNearbyFrom"]').forEach(r => { r.checked = r.value === 'home'; });
        pfNearbyStatus(err?.code === 1
            ? "Location access is blocked for this site, so your home location is used. Allow it in your browser's site settings to change this."
            : "Couldn't get your location, so your home location is used.", 'error');
    }
}

// Keep Tab inside the window while it's open.
document.getElementById('profileModal')?.addEventListener('keydown', (e) => {
    if (e.key !== 'Tab') return;
    const focusable = [...document.querySelectorAll(
        '#pfPanel a[href], #pfPanel button:not([disabled]), #pfPanel input:not([type="hidden"]):not([disabled]), #pfPanel label[title]'
    )].filter(el => el.offsetParent !== null && !el.closest('.leaflet-control-container'));
    if (!focusable.length) return;
    const first = focusable[0];
    const last  = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
});

// =====================================================
// KEYBOARD: Escape closes all modals
// =====================================================
/*
  Top-most wins: one Escape closes exactly one layer. The previous version ran
  every check in a single pass, so Escape inside a stacked overlay (the photo
  lightbox over spaModal, or bookingModal over spaModal) dropped the user all
  the way back to the page. Order below is by z-index, highest first — keep it
  in sync if you add a modal.
*/
const escapeCloseOrder = [
    ['photoLightbox',       () => closeLightbox()],          // z-[155]
    ['spaMapFull',          () => closeSpaMapFull()],        // z-[150]
    // logoutModal (z-[145]) is handled in landing-nav.js.
    ['reviewsModal',        () => closeReviewsModal()],      // z-[145]
    ['profileModal',        () => pfEscape()],               // z-[130]; an editor goes back first
    ['bookingDetailsModal', () => bdEscape()],               // z-[125]; the form view goes back first
    ['appointmentsModal',   () => closeAppointmentsModal()], // z-[120]
    ['scheduleModal',       () => closeScheduleModal()],     // z-[120]
    ['termsModal',          () => closeTermsModal()],        // z-[120]
    ['applicationModal',    () => closeApplicationModal()],  // z-[115]
    ['bookingModal',        () => closeBookingModal()],      // z-[110]
    ['spaModal',            () => closeSpaModal()],          // z-[100]
];

window.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;

    for (const [id, close] of escapeCloseOrder) {
        const el = document.getElementById(id);
        if (el && !el.classList.contains('hidden')) {
            close();
            return;
        }
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
window.browseSpasFromAppointments = browseSpasFromAppointments;
window.selectDay                = selectDay;
window.changeMonth              = changeMonth;
window.openProfileModal         = openProfileModal;
window.submitCustomerPhoto      = submitCustomerPhoto;
window.closeProfileModal        = closeProfileModal;
window.pfShowView               = pfShowView;
window.pfSave                   = pfSave;
window.pfBackdropClick          = pfBackdropClick;
window.pfSearchAddress          = pfSearchAddress;
window.pfUseCurrentLocation     = pfUseCurrentLocation;
window.pfSetNearbyFrom          = pfSetNearbyFrom;
window.showSpaToast             = showSpaToast;
window.openTermsModal           = openTermsModal;
window.closeTermsModal          = closeTermsModal;
window.acceptTermsModal         = acceptTermsModal;
window.openBookingDetailsModal  = openBookingDetailsModal;
window.closeBookingDetailsModal = closeBookingDetailsModal;
window.openRescheduleModal      = openRescheduleModal;
window.closeRescheduleModal     = closeRescheduleModal;
window.bdBackdropClick          = bdBackdropClick;
window.submitRescheduleRequest  = submitRescheduleRequest;
window.openCancelView           = openCancelView;
window.closeCancelView          = closeCancelView;
window.submitCancelBooking      = submitCancelBooking;
window.openRatingModal          = openRatingModal;
window.closeRatingModal         = closeRatingModal;
window.openReviewsModal         = openReviewsModal;
window.openReviewsModalFromSpa  = openReviewsModalFromSpa;
window.closeReviewsModal        = closeReviewsModal;
window.selectReviewFilter       = selectReviewFilter;
window.submitRating             = submitRating;
window.setRating                = setRating;
window.setSpaRating             = setSpaRating;
window.openReviewPhoto          = openReviewPhoto;
window.removeSpaRatingPhoto     = removeSpaRatingPhoto;
window.scheduleMap              = scheduleMap;
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
window.setScheduleView          = setScheduleView;
window.jumpToScheduleMonth      = jumpToScheduleMonth;
window.openLightbox             = openLightbox;
window.closeLightbox            = closeLightbox;

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
    resetSpaRatingPhotos();

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
    resetSpaRatingPhotos();
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
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> <span>Sending…</span>';
    }

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        const formData = new FormData();
        formData.append('booking_id', bookingId);
        formData.append('rating', rating);
        formData.append('comment', comment);
        formData.append('feedback', feedback);
        formData.append('spa_rating', spaRating);
        formData.append('spa_comment', spaComment);
        spaRatingPhotoFiles.forEach((file, i) => formData.append(`photos[${i}]`, file));

        const response = await fetch('/ratings', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken || '',
                'Accept': 'application/json'
                // No Content-Type — FormData sets its own multipart boundary.
            },
            body: formData
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

// =====================================================
// DEEP LINKS FROM OTHER PAGES  (/?open=appointments|schedule|profile)
// =====================================================
/*
  My Appointments, My Schedule and Profile are modals on / only. On the public
  pages the navbar and footer link to /?open=<name> instead (landing footer
  Phase 1, Q1-A); this opens the matching modal once, then removes the
  parameter so a refresh or Back doesn't open it again. Only customers have
  these modals (the profile dropdown is the marker); anyone else just gets
  the parameter removed.
*/
document.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    const target = params.get('open');
    if (!target) return;

    params.delete('open');
    const query = params.toString();
    window.history.replaceState(null, '', window.location.pathname + (query ? `?${query}` : '') + window.location.hash);

    if (!document.getElementById('profileDropdownBtn')) return;

    const openers = {
        appointments: openAppointmentsModal,
        schedule:     openScheduleModal,
        profile:      openProfileModal,
    };
    if (Object.prototype.hasOwnProperty.call(openers, target)) openers[target]();
});

// =====================================================
// KEEP /#browseSections IN VIEW WHILE THE PAGE SETTLES
// =====================================================
/*
  "Book Now" and "Browse spas" from other pages land on /#browseSections. The browser jumps
  once on load, but content above can still grow afterwards (Spas near you
  appears after the browser shares its location; images load), pushing the
  target down. Re-align on every layout change for a few seconds, and stop
  as soon as the visitor scrolls, clicks or types (landing footer Phase 3).
*/
(function keepHashTargetInView() {
    const id = decodeURIComponent(window.location.hash.slice(1));
    if (id !== 'browseSections') return;
    const target = document.getElementById(id);
    if (!target || typeof ResizeObserver === 'undefined') return;

    let settled = false;
    const stop = () => { settled = true; observer.disconnect(); };
    ['wheel', 'touchstart', 'keydown', 'mousedown'].forEach(evt =>
        window.addEventListener(evt, stop, { once: true, passive: true }));

    const observer = new ResizeObserver(() => {
        if (!settled) target.scrollIntoView({ block: 'start' });
    });
    observer.observe(document.body);
    setTimeout(stop, 5000);
})();