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
    document.getElementById('profileModal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');

    setTimeout(() => {
        if (window.profileMap) {
            window.profileMap.invalidateSize();
        }

        // If user already has a pinned location, show it
        const savedLat = parseFloat(document.getElementById('latitude').value);
        const savedLng = parseFloat(document.getElementById('longitude').value);
        if (savedLat && savedLng && window.profileMap) {
            window.profileMap.setView([savedLat, savedLng], 15);
            if (marker) window.profileMap.removeLayer(marker);
            marker = L.marker([savedLat, savedLng]).addTo(window.profileMap);
        }
    }, 300);
}

function closeProfileModal() {
    document.getElementById('profileModal').classList.add('hidden');
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
        ? `Starts at ₱${spaData.price_note}`
        : 'Prices vary per treatment';
    const hiringBlock = document.getElementById('spaModalHiring');
    const hiringNote   = document.getElementById('spaModalHiringNote');
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
                            <div class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl bg-[#F6EFE6]/70 border border-[#8B7355]/10 outline-none shadow-none">
                                <div class="flex items-center justify-center flex-shrink-0 bg-white rounded-lg w-7 h-7 border border-black/5">
                                    <i class="fa-solid fa-spa text-[#8B7355] text-xs"></i>
                                </div>
                                <span class="text-xs font-medium text-[#3C2F23]">${label}</span>
                            </div>`;
                    }).join('')}
                </div>`;
        } else {
            amenitiesContainer.innerHTML = `<p class="text-sm italic text-gray-400">No amenities listed yet.</p>`;
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
                circle.className = 'flex items-center justify-center w-8 h-8 text-xs font-semibold text-white rounded-full bg-[#8B7355] transition-colors';
                circle.innerHTML = '<i class="fa-solid fa-check text-[10px]"></i>';
            } else if (i === step) {
                circle.className = 'flex items-center justify-center w-8 h-8 text-xs font-semibold text-white rounded-full bg-[#8B7355] transition-colors';
                circle.textContent = i;
            } else {
                circle.className = 'flex items-center justify-center w-8 h-8 text-xs font-semibold text-gray-400 transition-colors bg-gray-200 rounded-full';
                circle.textContent = i;
            }
        }
        if (label) label.className = `ml-2 text-xs transition-colors ${i <= step ? 'font-semibold text-[#3C2F23]' : 'font-medium text-gray-400'}`;
        if (bar)   bar.className   = `w-10 h-0.5 mx-3 transition-colors rounded sm:w-16 ${i < step ? 'bg-[#8B7355]' : 'bg-gray-200'}`;
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

    const input = document.createElement('input');
    input.type = 'radio';
    input.name = 'treatment';
    input.value = value;
    input.className = 'sr-only';
    input.dataset.serviceType = item.service_type ?? 'in_branch_only';
    input.dataset.itemType    = kind;
    input.dataset.price       = item.price ?? '';
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

    const priceEl = document.createElement('span');
    priceEl.className = 'svc-card-price';
    priceEl.textContent = (item.price !== null && item.price !== undefined) ? formatPeso(item.price) : 'Price varies';

    topRow.appendChild(nameEl);
    topRow.appendChild(priceEl);

    const descEl = document.createElement('p');
    descEl.className = 'svc-card-desc';
    const desc = (item.description ?? '').toString().trim();
    descEl.textContent = desc.length ? desc : 'No description yet.';

    content.appendChild(topRow);
    content.appendChild(descEl);

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
            grid.innerHTML = '<p class="col-span-3 py-6 text-sm text-center text-gray-400 sm:col-span-4">This branch is closed on the selected day.</p>';
            return;
        }

        if (!data.slots?.length) {
            grid.innerHTML = '<p class="col-span-3 py-6 text-sm text-center text-gray-400 sm:col-span-4">No time slots available for this day.</p>';
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
        grid.innerHTML = '<p class="col-span-3 py-6 text-sm text-center text-red-400 sm:col-span-4">Unable to load availability. Please try again.</p>';
    } finally {
        loading?.classList.add('hidden');
    }
}

// ── Step 3: recap ────────────────────────────────────────────────────────────
function buildBookingRecap() {
    const checked = getCheckedTreatmentInput();

    document.getElementById('recapService').textContent = checked?.dataset.name || '—';
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
    document.getElementById('bookingSlotGrid').innerHTML = '<p class="col-span-3 py-6 text-sm text-center text-gray-400 sm:col-span-4">Pick a date to see available times.</p>';
    document.getElementById('bookingSlotLegend')?.classList.add('hidden');
    if (addressInput) {
        addressInput.value = '';
        addressInput.required = false;
    }
    if (addressWrapper) addressWrapper.classList.add('hidden');
    if (bookingCustomerPhone) bookingCustomerPhone.value = '';
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

    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeApplicationModal() {
    const modal = document.getElementById('applicationModal');
    if (!modal) return;
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

function closeBookingModal() {
    if (!bookingModal) return;
    bookingModal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

openBookingBtn?.addEventListener('click', openBookingModal);
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

            const today = getTodayLocal();
            const upcomingCount = data.filter(b =>
                ['reserved', 'confirmed'].includes(b.status) && b.date_raw >= today
            ).length;
            updateAppointmentsBadge(upcomingCount);
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
    const today = getTodayLocal();
    document.getElementById('tab-count-upcoming').textContent =
        allAppointments.filter(b => ['reserved', 'confirmed'].includes(b.status) && b.date_raw >= today).length;
    document.getElementById('tab-count-past').textContent =
        allAppointments.filter(b => b.status === 'completed' || (['reserved', 'pending', 'completed'].includes(b.status) && b.date_raw < today)).length;
    document.getElementById('tab-count-cancelled').textContent =
        allAppointments.filter(b => b.status === 'cancelled').length;
}

function switchTab(tab) {
    currentTab = tab;
    ['upcoming', 'past', 'cancelled'].forEach(t => {
        const el = document.getElementById(`tab-${t}`);
        if (t === tab) {
            el.classList.add('border-[#8B7355]', 'text-[#8B7355]');
            el.classList.remove('border-transparent', 'text-gray-500');
        } else {
            el.classList.remove('border-[#8B7355]', 'text-[#8B7355]');
            el.classList.add('border-transparent', 'text-gray-500');
        }
    });
    renderTab(tab);
}

function renderTab(tab) {
    const today  = getTodayLocal();
    let filtered = [];
    if (tab === 'upcoming') {
        filtered = allAppointments.filter(b => ['reserved', 'confirmed'].includes(b.status) && b.date_raw >= today);
    } else if (tab === 'past') {
        filtered = allAppointments.filter(b => b.status === 'completed' || (['reserved', 'pending'].includes(b.status) && b.date_raw < today));
    } else {
        filtered = allAppointments.filter(b => b.status === 'cancelled');
    }

    Object.keys(_appointmentMap).forEach(k => delete _appointmentMap[k]);
    filtered.forEach((b, i) => { _appointmentMap[i] = b; });

    const container = document.getElementById('appointmentsContent');
    if (!filtered.length) {
        container.innerHTML = `
            <div class="py-12 text-center text-gray-400">
                <i class="mb-3 text-3xl fa-solid fa-calendar-xmark"></i>
                <p class="text-sm">No ${tab} appointments</p>
            </div>`;
        return;
    }

    container.innerHTML = filtered.map((b, i) => {
        const canRate   = b.status === 'completed' && !b.has_rating;
        const hasRating = b.has_rating === true;

        return `
        <div class="p-4 mb-3 border border-black/5 rounded-2xl bg-[#F6EFE6]/40 ring-1 ring-black/5 transition hover:shadow-md">
            <div onclick="openBookingDetailsModal(_appointmentMap[${i}])" class="cursor-pointer">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="font-semibold text-[#3C2F23]">${escapeHtml(b.spa_name)}</p>
                        <p class="text-xs text-gray-500">${escapeHtml(b.branch_location ?? b.branch_name)} • ${b.service_type}</p>
                    </div>
                    <span class="px-2 py-1 text-[10px] font-semibold rounded-full ${statusBadge(b.status)}">
                        ${b.status.charAt(0).toUpperCase() + b.status.slice(1)}
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-2 mt-3 text-xs text-gray-600">
                    <div class="flex items-center gap-1"><i class="fa-solid fa-spa text-[#8B7355]"></i> ${escapeHtml(b.treatment)}</div>
                    <div class="flex items-center gap-1"><i class="fa-solid fa-user-nurse text-[#8B7355]"></i> ${escapeHtml(b.therapist)}</div>
                    <div class="flex items-center gap-1"><i class="fa-solid fa-calendar text-[#8B7355]"></i> ${b.date}</div>
                    <div class="flex items-center gap-1"><i class="fa-solid fa-clock text-[#8B7355]"></i> ${formatTime(b.start_time)} – ${formatTime(b.end_time)}</div>
                </div>
                ${b.reschedule_status === 'pending' ? `
                <div class="mt-2 text-[11px] font-semibold text-yellow-600 flex items-center gap-1">
                    <i class="fa-solid fa-clock-rotate-left"></i> Reschedule request pending
                </div>` : ''}
            </div>
            ${canRate ? `
            <div class="mt-3 pt-3 border-t border-gray-200">
                <button onclick="openRatingModal(_appointmentMap[${i}].id, _appointmentMap[${i}].therapist, _appointmentMap[${i}].spa_name, _appointmentMap[${i}].branch_name, _appointmentMap[${i}].branch_location)"
                    class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white transition rounded-xl bg-[#8B7355] hover:bg-[#6F5430] w-full justify-center">
                    <i class="fa-solid fa-star"></i>
                    Rate Your Experience
                </button>
            </div>` : ''}
            ${hasRating ? `
            <div class="mt-3 pt-3 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-green-600 text-sm"></i>
                        <span class="text-sm font-semibold text-green-600">Thank you for rating!</span>
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
        stars += `<i class="fa-solid fa-star ${i <= rating ? 'text-yellow-400' : 'text-gray-300'} text-xs"></i>`;
    }
    return stars;
}

function statusBadge(status) {
    const map = {
        reserved:  'bg-blue-100 text-blue-700',
        ongoing:   'bg-green-100 text-green-700',
        completed: 'bg-gray-100 text-gray-600',
        cancelled: 'bg-red-100 text-red-600',
        pending:   'bg-yellow-100 text-yellow-700',
    };
    return map[status] ?? 'bg-gray-100 text-gray-600';
}

// =====================================================
// MY SCHEDULE MODAL
// =====================================================
let scheduleBookings = [];
let calendarDate     = new Date();
let _dayBookingMap   = {};

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
            renderCalendar();
        });
}

function changeMonth(dir) {
    calendarDate.setMonth(calendarDate.getMonth() + dir);
    renderCalendar();
    document.getElementById('selectedDayBookings').classList.add('hidden');
}

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
                ${hasBooking && !isToday ? 'bg-[#F6EFE6] text-[#6F5430] font-semibold ring-1 ring-[#8B7355]/30' : ''}
                ${isPast && !isToday ? 'text-gray-300 cursor-default' : 'hover:bg-[#F6EFE6]'}
                ${!hasBooking && !isToday && !isPast ? 'text-gray-700' : ''}">
                ${d}
                ${hasBooking ? `<span class="absolute bottom-1 w-1 h-1 rounded-full ${isToday ? 'bg-white' : 'bg-[#8B7355]'}"></span>` : ''}
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
        <div class="p-3 mb-3 border border-black/5 rounded-xl bg-[#F6EFE6]/50 ring-1 ring-black/5 cursor-pointer hover:shadow-md transition"
            onclick="openBookingDetailsModal(_dayBookingMap[${i}])">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-[#3C2F23]">${escapeHtml(b.spa_name)}</p>
                <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full ${statusBadge(b.status)}">${b.status}</span>
            </div>
            <p class="mt-1 text-xs text-gray-500">${escapeHtml(b.branch_name)} • ${escapeHtml(b.treatment)}</p>
            <p class="mt-1 text-xs text-gray-500">
                <i class="fa-solid fa-clock text-[#8B7355]"></i>
                ${formatTime(b.start_time)} – ${formatTime(b.end_time)} • ${escapeHtml(b.therapist)}
            </p>
            ${b.reschedule_status === 'pending' ? `
            <div class="mt-2 text-[11px] font-semibold text-yellow-600 flex items-center gap-1">
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
        rescheduleStatusEl.className     = 'p-3 rounded-xl ring-1 bg-yellow-50 ring-yellow-200';
        rescheduleStatusText.textContent = '⏳ Reschedule request is pending approval.';
        rescheduleStatusText.className   = 'text-sm font-semibold text-yellow-700';
        rescheduleBtn.disabled           = true;
        rescheduleBtn.classList.add('opacity-50', 'cursor-not-allowed');
    } else if (booking.reschedule_status === 'approved') {
        rescheduleStatusEl.classList.remove('hidden');
        rescheduleStatusEl.className     = 'p-3 rounded-xl ring-1 bg-green-50 ring-green-200';
        rescheduleStatusText.textContent = '✅ Your reschedule was approved.';
        rescheduleStatusText.className   = 'text-sm font-semibold text-green-700';
        rescheduleBtn.disabled           = false;
        rescheduleBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    } else if (booking.reschedule_status === 'rejected') {
        rescheduleStatusEl.classList.remove('hidden');
        rescheduleStatusEl.className     = 'p-3 rounded-xl ring-1 bg-red-50 ring-red-200';
        rescheduleStatusText.textContent = '❌ Your last reschedule request was rejected.';
        rescheduleStatusText.className   = 'text-sm font-semibold text-red-600';
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
        reserved:  'text-blue-600',
        pending:   'text-yellow-600',
        ongoing:   'text-green-600',
        completed: 'text-gray-500',
        cancelled: 'text-red-500',
    };
    return map[status] ?? 'text-gray-600';
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
                    class="w-full overflow-hidden text-left transition bg-white shadow-sm group rounded-3xl ring-1 ring-black/5 hover:shadow-2xl"
                    data-open-spa-modal
                    data-spa='${escapedData}'>
                    <div class="relative overflow-hidden">
                        <img src="${thumb}" class="h-56 w-full object-cover transition duration-500 group-hover:scale-[1.04]" alt="${spa.name}">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-black/0 to-transparent"></div>
                        <div class="absolute top-3 left-3 flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/80 text-[#6F5430] text-[11px] font-semibold backdrop-blur-sm ring-1 ring-black/5">
                            <i class="fa-solid fa-location-dot text-[#8B7355] text-[10px]"></i>
                            ${spa.distance_km} km away
                        </div>
                    </div>
                    <div class="p-5">
                        <h3 class="text-[15px] font-semibold text-[#3C2F23] leading-tight">${spa.name}</h3>
                        <p class="mt-1 text-xs text-gray-500">${addrSummary}</p>
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
// MAP (For Profile Modal) - FIXED with container check
// =====================================================

// Wait for DOM to be fully loaded before initializing map
document.addEventListener('DOMContentLoaded', function() {
    const mapContainer = document.getElementById('map');

    if (mapContainer) {
        // Initialize map only if container exists
        window.profileMap = L.map('map').setView([14.3715407, 120.9388733], 11);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(window.profileMap);

        let marker;

        window.profileMap.on('click', function (e) {
            let lat = e.latlng.lat;
            let lng = e.latlng.lng;

            if (marker) {
                window.profileMap.removeLayer(marker);
            }

            marker = L.marker([lat, lng]).addTo(window.profileMap);

            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');
            if (latInput) latInput.value = lat;
            if (lngInput) lngInput.value = lng;

            // reverse geocoding (optional)
            fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json`)
                .then(res => res.json())
                .then(data => {
                    const addressField = document.getElementById('address');
                    if (addressField) addressField.value = data.display_name;
                })
                .catch(err => console.warn('Reverse geocoding failed:', err));
        });
    } else {
        console.log('Map container not found on this page - skipping map initialization');
    }
});

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
window.submitRating             = submitRating;
window.setRating                = setRating;
window._dayBookingMap           = _dayBookingMap;
window._appointmentMap          = _appointmentMap;
window.openApplicationModal     = openApplicationModal;
window.closeApplicationModal    = closeApplicationModal;
window.openLogoutModal          = openLogoutModal;
window.closeLogoutModal         = closeLogoutModal;

// =====================================================
// TOAST
// =====================================================
function showSpaToast(message, type = 'success') {
    const isSuccess = type === 'success';
    Toastify({
        text: `
            <div style="display:flex;align-items:center;gap:12px;padding:2px 0;">
                <div style="width:36px;height:36px;border-radius:50%;background:${isSuccess ? '#f0fdf4' : '#fef2f2'};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="${isSuccess ? 'fa-solid fa-spa' : 'fa-solid fa-circle-xmark'}" style="color:${isSuccess ? '#16a34a' : '#dc2626'};font-size:15px;"></i>
                </div>
                <div style="display:flex;flex-direction:column;gap:2px;">
                    <span style="font-size:11px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:${isSuccess ? '#15803d' : '#b91c1c'};">${isSuccess ? 'Success' : 'Error'}</span>
                    <span style="font-size:13px;color:#374151;font-weight:400;line-height:1.4;">${message}</span>
                </div>
            </div>`,
        duration: 3500,
        gravity: 'top',
        position: 'right',
        close: false,
        escapeMarkup: false,
        style: {
            background: '#ffffff',
            border: isSuccess ? '1px solid #bbf7d0' : '1px solid #fecaca',
            borderLeft: isSuccess ? '4px solid #16a34a' : '4px solid #dc2626',
            borderRadius: '10px',
            minWidth: '300px',
            maxWidth: '360px',
            padding: '14px 18px',
            boxShadow: '0 10px 30px rgba(0,0,0,0.08)',
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

    if (ratingBookingId) ratingBookingId.value = bookingId;
    if (ratingTherapistName) ratingTherapistName.innerText = therapistName;

    const locationText = branchLocation || branchName || 'Branch location unavailable';
    if (ratingBranchLocation) ratingBranchLocation.innerText = locationText;

    resetStars();

    const ratingComment = document.getElementById('ratingComment');
    const ratingFeedback = document.getElementById('ratingFeedback');
    if (ratingComment) ratingComment.value = '';
    if (ratingFeedback) ratingFeedback.value = '';

    const commentCount = document.getElementById('ratingCommentCount');
    const feedbackCount = document.getElementById('ratingFeedbackCount');
    if (commentCount) commentCount.textContent = '0';
    if (feedbackCount) feedbackCount.textContent = '0';

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
}

function resetStars() {
    for (let i = 1; i <= 5; i++) {
        const star = document.getElementById(`star-${i}`);
        if (!star) continue;
        star.classList.remove('text-yellow-400');
        star.classList.add('text-gray-300');
    }
    const selectedRating = document.getElementById('selectedRating');
    if (selectedRating) selectedRating.value = 0;
}

function setRating(rating) {
    const selectedRating = document.getElementById('selectedRating');
    if (selectedRating) selectedRating.value = rating;

    for (let i = 1; i <= 5; i++) {
        const star = document.getElementById(`star-${i}`);
        if (!star) continue;
        if (i <= rating) {
            star.classList.remove('text-gray-300');
            star.classList.add('text-yellow-400');
        } else {
            star.classList.remove('text-yellow-400');
            star.classList.add('text-gray-300');
        }
    }
}

async function submitRating() {
    const bookingId = document.getElementById('ratingBookingId')?.value;
    const rating    = document.getElementById('selectedRating')?.value;
    const comment   = document.getElementById('ratingComment')?.value || '';
    const feedback  = document.getElementById('ratingFeedback')?.value || '';

    if (!rating || rating == 0) {
        showSpaToast('Please select a rating', 'error');
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
                booking_id: bookingId,
                rating:     parseInt(rating),
                comment:    comment,
                feedback:   feedback
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
