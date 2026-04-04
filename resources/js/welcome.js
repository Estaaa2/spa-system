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
const treatmentSelect      = document.getElementById('bookingTreatmentSelect');
const bookingDateInput     = document.getElementById('bookingDateInput');
const bookingTimeInput     = document.getElementById('bookingTimeInput');
const addressWrapper       = document.getElementById('addressWrapper');
const addressInput         = document.getElementById('bookingAddressInput');
const bookingForm          = document.querySelector('#bookingModal form');

function clearBookingSelections() {
    if (treatmentSelect) {
        treatmentSelect.innerHTML = '<option value="">Select treatment or package</option>';
        treatmentSelect.value = '';
    }
    if (bookingBranchIdInput) bookingBranchIdInput.value = '';
    resetServiceType();
    if (bookingDateInput) bookingDateInput.value = '';
    if (bookingTimeInput) {
        bookingTimeInput.value = '';
        bookingTimeInput.disabled = false;
        bookingTimeInput.removeAttribute('min');
        bookingTimeInput.removeAttribute('max');
    }
    if (addressInput) {
        addressInput.value = '';
        addressInput.required = false;
    }
    if (addressWrapper) addressWrapper.classList.add('hidden');

    const timeError = document.getElementById('bookingTimeError');
    const submitBtn = document.getElementById('bookingSubmitBtn');
    if (timeError) { timeError.textContent = ''; timeError.classList.add('hidden'); }
    if (submitBtn) submitBtn.disabled = false;
}

function populateTreatmentsForSelectedBranch() {
    if (!selectedSpa || !treatmentSelect) return;

    treatmentSelect.innerHTML = '<option value="">Select treatment or package</option>';

    (selectedSpa.treatments ?? []).forEach(t => {
        const option = document.createElement('option');
        option.value = `treatment_${t.id}`;
        option.textContent = t.price !== null && t.price !== undefined
            ? `${t.name} — ₱${parseFloat(t.price).toLocaleString()}`
            : t.name;
        option.dataset.serviceType = t.service_type ?? 'in_branch_only';
        option.dataset.itemType    = 'treatment';
        treatmentSelect.appendChild(option);
    });

    (selectedSpa.packages ?? []).forEach(p => {
        const option = document.createElement('option');
        option.value = `package_${p.id}`;
        option.textContent = p.price !== null && p.price !== undefined
            ? `${p.name} (Package) — ₱${parseFloat(p.price).toLocaleString()}`
            : `${p.name} (Package)`;
        option.dataset.serviceType = p.service_type ?? 'in_branch_only';
        option.dataset.itemType    = 'package';
        treatmentSelect.appendChild(option);
    });

    resetServiceType();
}

function openTermsModal() {
    document.getElementById('termsModal').classList.remove('hidden');
}

function closeTermsModal() {
    document.getElementById('termsModal').classList.add('hidden');
}

function resetServiceType() {
    if (!serviceTypeSelect) return;
    serviceTypeSelect.innerHTML = '<option value="">Select service type</option>';
    serviceTypeSelect.value = '';
    if (serviceTypeHint) serviceTypeHint.textContent = '';
    if (addressWrapper) addressWrapper.classList.add('hidden');
    if (addressInput) addressInput.required = false;
}

function populateServiceTypeOptions() {
    resetServiceType();
    if (!treatmentSelect || !serviceTypeSelect) return;

    const selectedOption = treatmentSelect.options[treatmentSelect.selectedIndex];
    if (!selectedOption || !selectedOption.value) return;

    const serviceType = selectedOption.dataset.serviceType || 'in_branch_only';

    if (serviceType === 'in_branch_only') {
        serviceTypeSelect.innerHTML = `<option value="in_branch">In-Branch</option>`;
        serviceTypeSelect.value = 'in_branch';
        if (serviceTypeHint) serviceTypeHint.textContent = 'This selection is available for in-branch service only.';
    } else if (serviceType === 'in_branch_and_home') {
        serviceTypeSelect.innerHTML = `
            <option value="">Select service type</option>
            <option value="in_branch">In-Branch</option>
            <option value="in_home">Home Service</option>
        `;
        if (serviceTypeHint) serviceTypeHint.textContent = 'This selection is available for both in-branch and home service.';
    }

    toggleAddressField();
}

function toggleAddressField() {
    const isHome = serviceTypeSelect && serviceTypeSelect.value === 'in_home';
    if (addressWrapper) addressWrapper.classList.toggle('hidden', !isHome);
    if (addressInput) addressInput.required = isHome;
}

async function updateAvailableTimes() {
    const branchId  = bookingBranchIdInput?.value;
    const dateValue = bookingDateInput?.value;
    if (!branchId || !dateValue || !bookingTimeInput) return;

    const day = new Date(dateValue + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long' });

    try {
        const response = await fetch(`/api/operating-hours/${branchId}/${day}`);
        const data = await response.json();

        if (data.is_closed) {
            bookingTimeInput.value = '';
            bookingTimeInput.disabled = true;
            bookingTimeInput.removeAttribute('min');
            bookingTimeInput.removeAttribute('max');
            const errorEl  = document.getElementById('bookingTimeError');
            const submitBtn = document.getElementById('bookingSubmitBtn');
            if (errorEl)  { errorEl.textContent = 'This branch is closed on the selected day.'; errorEl.classList.remove('hidden'); }
            if (submitBtn) submitBtn.disabled = true;
            showSpaToast('This branch is closed on the selected day.', 'error');
            return;
        }

        bookingTimeInput.disabled = false;
        const errorEl  = document.getElementById('bookingTimeError');
        const submitBtn = document.getElementById('bookingSubmitBtn');
        if (errorEl)  { errorEl.textContent = ''; errorEl.classList.add('hidden'); }
        if (submitBtn) submitBtn.disabled = false;

        const openingTime = (data.opening_time || '').slice(0, 5);
        const closingTime = (data.closing_time || '').slice(0, 5);
        bookingTimeInput.min = openingTime;
        bookingTimeInput.max = closingTime;
        if (bookingTimeInput.value) validateBookingTime();

    } catch (error) {
        console.error('Failed to load operating hours:', error);
        showSpaToast('Unable to check branch operating hours right now.', 'error');
    }

    validateBookingTime();
}

function formatTime12Hour(time) {
    if (!time) return '';
    const [hour, minute] = time.split(':').map(Number);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const h12  = hour % 12 || 12;
    return `${h12}:${String(minute).padStart(2, '0')} ${ampm}`;
}

function validateBookingTime() {
    const dateValue = bookingDateInput?.value;
    const timeValue = bookingTimeInput?.value;
    const errorEl   = document.getElementById('bookingTimeError');
    const submitBtn = document.getElementById('bookingSubmitBtn');

    if (!dateValue || !timeValue) {
        if (errorEl) { errorEl.textContent = ''; errorEl.classList.add('hidden'); }
        if (submitBtn) submitBtn.disabled = false;
        return true;
    }

    const today        = getTodayLocal();
    const selectedTime = timeValue.slice(0, 5);
    const openingTime  = (bookingTimeInput.min || '').slice(0, 5);
    const closingTime  = (bookingTimeInput.max || '').slice(0, 5);

    if (openingTime && selectedTime < openingTime) {
        if (errorEl) { errorEl.textContent = `Selected time must be within branch hours only (${formatTime12Hour(openingTime)} to ${formatTime12Hour(closingTime)}).`; errorEl.classList.remove('hidden'); }
        if (submitBtn) submitBtn.disabled = true;
        return false;
    }

    if (closingTime && selectedTime > closingTime) {
        if (errorEl) { errorEl.textContent = `Selected time must be within branch hours only (${formatTime12Hour(openingTime)} to ${formatTime12Hour(closingTime)}).`; errorEl.classList.remove('hidden'); }
        if (submitBtn) submitBtn.disabled = true;
        return false;
    }

    if (dateValue === today) {
        const now = new Date();
        const [hh, mm] = selectedTime.split(':').map(Number);
        const selected  = new Date();
        selected.setHours(hh, mm, 0, 0);
        if (selected <= now) {
            if (errorEl) { errorEl.textContent = 'Please select a future time.'; errorEl.classList.remove('hidden'); }
            if (submitBtn) submitBtn.disabled = true;
            return false;
        }
    }

    if (errorEl) { errorEl.textContent = ''; errorEl.classList.add('hidden'); }
    if (submitBtn) submitBtn.disabled = false;
    return true;
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

    bookingModal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeBookingModal() {
    if (!bookingModal) return;
    bookingModal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

openBookingBtn?.addEventListener('click', openBookingModal);
closeBookingBtns.forEach(btn => btn.addEventListener('click', closeBookingModal));

treatmentSelect?.addEventListener('change', populateServiceTypeOptions);
serviceTypeSelect?.addEventListener('change', toggleAddressField);
bookingDateInput?.addEventListener('change', updateAvailableTimes);
bookingTimeInput?.addEventListener('change', validateBookingTime);
bookingTimeInput?.addEventListener('input', validateBookingTime);

bookingForm?.addEventListener('submit', function (e) {
    const isValid = validateBookingTime();
    if (!isValid) { e.preventDefault(); return; }
    if (bookingTimeInput?.disabled) {
        e.preventDefault();
        showSpaToast('Please select a valid booking date and time.', 'error');
    }
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
// KEYBOARD: Escape closes all modals
// =====================================================
window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (!document.getElementById('rescheduleModal')?.classList.contains('hidden'))     closeRescheduleModal();
        if (!document.getElementById('bookingDetailsModal')?.classList.contains('hidden')) closeBookingDetailsModal();
        if (!document.getElementById('termsModal')?.classList.contains('hidden'))          closeTermsModal();
        if (!spaModal?.classList.contains('hidden'))                                        closeSpaModal();
        if (!bookingModal?.classList.contains('hidden'))                                    closeBookingModal();
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

    // Store the booking ID
    document.getElementById('ratingBookingId').value = bookingId;

    // Display therapist name
    document.getElementById('ratingTherapistName').innerText = therapistName;

    // Display branch location instead of duplicate spa name
    const locationText = branchLocation || branchName || 'Branch location unavailable';
    document.getElementById('ratingBranchLocation').innerText = locationText;

    // Reset rating stars
    resetStars();

    // Clear previous comment
    document.getElementById('ratingComment').value = '';
    document.getElementById('ratingFeedback').value = '';

    // Reset character counts
    if (document.getElementById('ratingCommentCount')) {
        document.getElementById('ratingCommentCount').textContent = '0';
    }
    if (document.getElementById('ratingFeedbackCount')) {
        document.getElementById('ratingFeedbackCount').textContent = '0';
    }

    // Enable submit button
    const submitBtn = document.getElementById('ratingSubmitBtn');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        submitBtn.innerHTML = '<i class="mr-2 fa-solid fa-paper-plane"></i> Submit Rating';
    }

    // Show modal
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
    const bookingId = document.getElementById('ratingBookingId').value;
    const rating    = document.getElementById('selectedRating').value;
    const comment   = document.getElementById('ratingComment').value;
    const feedback  = document.getElementById('ratingFeedback').value;

    if (!rating || rating == 0) {
        showSpaToast('Please select a rating', 'error');
        return;
    }

    const submitBtn = document.getElementById('ratingSubmitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Submitting...';

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        // Use web route (not /api/ratings) for web requests
        const response = await fetch('/ratings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
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
            submitBtn.disabled  = false;
            submitBtn.innerHTML = '<i class="mr-2 fa-solid fa-paper-plane"></i> Submit Rating';
        }
    } catch (error) {
        console.error('Error submitting rating:', error);
        showSpaToast('Network error. Please try again.', 'error');
        submitBtn.disabled  = false;
        submitBtn.innerHTML = '<i class="mr-2 fa-solid fa-paper-plane"></i> Submit Rating';
    }
}

document.getElementById('ratingComment')?.addEventListener('input', function () {
    const el = document.getElementById('ratingCommentCount');
    if (el) el.textContent = this.value.length;
});

document.getElementById('ratingFeedback')?.addEventListener('input', function () {
    const el = document.getElementById('ratingFeedbackCount');
    if (el) el.textContent = this.value.length;
});
