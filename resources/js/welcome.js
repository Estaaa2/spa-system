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
    if (timeError) {
        timeError.textContent = '';
        timeError.classList.add('hidden');
    }
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

    const day = new Date(dateValue).toLocaleDateString('en-US', { weekday: 'long' });

    try {
        const response = await fetch(`/api/operating-hours/${branchId}/${day}`);
        const data = await response.json();

        if (data.is_closed) {
            bookingTimeInput.value = '';
            bookingTimeInput.disabled = true;
            bookingTimeInput.removeAttribute('min');
            bookingTimeInput.removeAttribute('max');

            const errorEl = document.getElementById('bookingTimeError');
            const submitBtn = document.getElementById('bookingSubmitBtn');

            if (errorEl) {
                errorEl.textContent = 'This branch is closed on the selected day.';
                errorEl.classList.remove('hidden');
            }

            if (submitBtn) submitBtn.disabled = true;

            showSpaToast('This branch is closed on the selected day.', 'error');
            return;
        } else {
            bookingTimeInput.disabled = false;

            const errorEl = document.getElementById('bookingTimeError');
            const submitBtn = document.getElementById('bookingSubmitBtn');

            if (errorEl) {
                errorEl.textContent = '';
                errorEl.classList.add('hidden');
            }
            if (submitBtn) submitBtn.disabled = false;

            const openingTime = (data.opening_time || '').slice(0, 5);
            const closingTime = (data.closing_time || '').slice(0, 5);

            bookingTimeInput.min = openingTime;
            bookingTimeInput.max = closingTime;

            if (bookingTimeInput.value) {
                validateBookingTime();
            }
        }
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
    const h12 = hour % 12 || 12;
    return `${h12}:${String(minute).padStart(2, '0')} ${ampm}`;
}

function validateBookingTime() {
    const dateValue = bookingDateInput?.value;
    const timeValue = bookingTimeInput?.value;
    const errorEl   = document.getElementById('bookingTimeError');
    const submitBtn = document.getElementById('bookingSubmitBtn');

    if (!dateValue || !timeValue) {
        if (errorEl) {
            errorEl.textContent = '';
            errorEl.classList.add('hidden');
        }
        if (submitBtn) submitBtn.disabled = false;
        return true;
    }

    const today = new Date().toISOString().split('T')[0];
    const selectedTime = timeValue.slice(0, 5);
    const openingTime = (bookingTimeInput.min || '').slice(0, 5);
    const closingTime = (bookingTimeInput.max || '').slice(0, 5);

    if (openingTime && selectedTime < openingTime) {
        if (errorEl) {
            errorEl.textContent = `Selected time must be within branch hours only (${formatTime12Hour(openingTime)} to ${formatTime12Hour(closingTime)}).`;
            errorEl.classList.remove('hidden');
        }
        if (submitBtn) submitBtn.disabled = true;
        return false;
    }

    if (closingTime && selectedTime > closingTime) {
        if (errorEl) {
            errorEl.textContent = `Selected time must be within branch hours only (${formatTime12Hour(openingTime)} to ${formatTime12Hour(closingTime)}).`;
            errorEl.classList.remove('hidden');
        }
        if (submitBtn) submitBtn.disabled = true;
        return false;
    }

    if (dateValue === today) {
        const now = new Date();
        const [hh, mm] = selectedTime.split(':').map(Number);
        const selected = new Date();
        selected.setHours(hh, mm, 0, 0);

        if (selected <= now) {
            if (errorEl) {
                errorEl.textContent = 'Please select a future time.';
                errorEl.classList.remove('hidden');
            }
            if (submitBtn) submitBtn.disabled = true;
            return false;
        }
    }

    if (errorEl) {
        errorEl.textContent = '';
        errorEl.classList.add('hidden');
    }
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

    const today = new Date().toISOString().split('T')[0];
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

    if (!isValid) {
        e.preventDefault();
        return;
    }

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
    const today = new Date().toISOString().split('T')[0];
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
    const today  = new Date().toISOString().split('T')[0];
    let filtered = [];
    if (tab === 'upcoming') {
        filtered = allAppointments.filter(b => ['reserved', 'confirmed'].includes(b.status) && b.date_raw >= today);
    } else if (tab === 'past') {
        filtered = allAppointments.filter(b => b.status === 'completed' || (['reserved', 'pending'].includes(b.status) && b.date_raw < today));
    } else {
        filtered = allAppointments.filter(b => b.status === 'cancelled');
    }

    const container = document.getElementById('appointmentsContent');
    if (!filtered.length) {
        container.innerHTML = `
            <div class="py-12 text-center text-gray-400">
                <i class="mb-3 text-3xl fa-solid fa-calendar-xmark"></i>
                <p class="text-sm">No ${tab} appointments</p>
            </div>`;
        return;
    }
    container.innerHTML = filtered.map(b => `
        <div class="p-4 mb-3 border border-black/5 rounded-2xl bg-[#F6EFE6]/40 ring-1 ring-black/5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="font-semibold text-[#3C2F23]">${b.spa_name}</p>
                    <p class="text-xs text-gray-500">${b.branch_name} • ${b.service_type}</p>
                </div>
                <span class="px-2 py-1 text-[10px] font-semibold rounded-full ${statusBadge(b.status)}">
                    ${b.status.charAt(0).toUpperCase() + b.status.slice(1)}
                </span>
            </div>
            <div class="grid grid-cols-2 gap-2 mt-3 text-xs text-gray-600">
                <div class="flex items-center gap-1"><i class="fa-solid fa-spa text-[#8B7355]"></i> ${b.treatment}</div>
                <div class="flex items-center gap-1"><i class="fa-solid fa-user-nurse text-[#8B7355]"></i> ${b.therapist}</div>
                <div class="flex items-center gap-1"><i class="fa-solid fa-calendar text-[#8B7355]"></i> ${b.date}</div>
                <div class="flex items-center gap-1"><i class="fa-solid fa-clock text-[#8B7355]"></i> ${formatTime(b.start_time)} – ${formatTime(b.end_time)}</div>
            </div>
        </div>
    `).join('');
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
            console.log('Schedule data:', data);
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
    const today = new Date().toISOString().split('T')[0];
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
    const title = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', {
        weekday: 'long', month: 'long', day: 'numeric'
    });
    document.getElementById('selectedDayTitle').textContent = title;
    document.getElementById('selectedDayContent').innerHTML = dayBookings.map(b => `
        <div class="p-3 mb-3 border border-black/5 rounded-xl bg-[#F6EFE6]/50 ring-1 ring-black/5">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-[#3C2F23]">${b.spa_name}</p>
                <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full ${statusBadge(b.status)}">${b.status}</span>
            </div>
            <p class="mt-1 text-xs text-gray-500">${b.branch_name} • ${b.treatment}</p>
            <p class="mt-1 text-xs text-gray-500">
                <i class="fa-solid fa-clock text-[#8B7355]"></i>
                ${formatTime(b.start_time)} – ${formatTime(b.end_time)} • ${b.therapist}
            </p>
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

window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (!spaModal?.classList.contains('hidden'))     closeSpaModal();
        if (!bookingModal?.classList.contains('hidden')) closeBookingModal();
    }
});

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
