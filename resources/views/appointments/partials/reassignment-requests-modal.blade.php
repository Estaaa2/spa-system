{{--
    Reassignment Requests (Approver side: owner / manager / receptionist)

    Include this ONCE from appointments.blade.php, right after the closing
    </div> of the existing "Needs Attention Right Now" section
    (id="needsAttentionSection"), before "Today's Appointments":

        @include('appointments.partials.reassignment-requests-modal')

    Gated by $canEdit, which is already computed at the top of
    appointments.blade.php from the 'edit appointments' permission — the
    same permission owner/manager/receptionist already hold, so no new
    permission is needed on this side.
--}}
@if($canEdit)
<div id="reassignmentSection" class="hidden overflow-hidden bg-white border shadow-sm border-red-200 rounded-2xl dark:bg-gray-800 dark:border-red-800">
    <div class="flex items-center justify-between px-6 py-4 border-b border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/10">
        <div>
            <h2 class="text-lg font-semibold text-red-900 dark:text-red-200">Reassignment Requested</h2>
            <p class="text-sm text-red-700 dark:text-red-300">
                A therapist has flagged they can't make an appointment — pick a replacement or reject the request.
            </p>
        </div>
        <span id="reassignmentBadge"
              class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
            0 Pending
        </span>
    </div>
    <div id="reassignmentList" class="p-6 space-y-4"></div>
</div>

{{-- Review modal --}}
<div id="reassignReviewModal" class="fixed inset-0 z-50 hidden p-4 bg-black/50">
    <div class="w-full max-w-lg mx-auto mt-16 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Review Reassignment</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Choose a replacement therapist or reject the request.</p>
            </div>
            <button type="button" onclick="closeReassignReviewModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">✕</button>
        </div>

        <div class="px-6 py-6 space-y-4">
            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-900/40">
                <p class="text-sm font-semibold text-gray-900 dark:text-white" id="reassign_review_customer"></p>
                <p class="text-sm text-gray-500 dark:text-gray-400" id="reassign_review_treatment"></p>
                <p class="mt-1 text-xs text-gray-400" id="reassign_review_datetime"></p>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Currently assigned: <span class="font-medium text-gray-700 dark:text-gray-300" id="reassign_review_old_therapist"></span>
                </p>
                <p class="mt-2 text-sm italic text-gray-600 dark:text-gray-300" id="reassign_review_reason"></p>
            </div>

            <div>
                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Replacement Therapist</label>
                <select id="reassign_review_therapist" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">Loading available therapists…</option>
                </select>
                <p id="reassign_review_hint" class="hidden mt-1 text-xs text-amber-600 dark:text-amber-400">
                    No therapist auto-matched for this slot — pick one manually or reject the request.
                </p>
            </div>

            <div id="reassignReviewError" class="hidden p-3 text-sm text-red-600 rounded-xl bg-red-50 ring-1 ring-red-200 dark:bg-red-900/20 dark:ring-red-800">
                <span id="reassignReviewErrorText"></span>
            </div>

            <div class="flex justify-between gap-2 pt-2">
                <button type="button" id="reassignRejectBtn" onclick="rejectReassignment()"
                        class="px-4 py-2 text-sm text-white bg-red-600 rounded-xl hover:bg-red-700">
                    Reject
                </button>
                <div class="flex gap-2">
                    <button type="button" onclick="closeReassignReviewModal()"
                            class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                        Cancel
                    </button>
                    <button type="button" id="reassignApproveBtn" onclick="approveReassignment()"
                            class="rounded-xl bg-[#8B7355] px-4 py-2 text-sm font-medium text-white hover:bg-[#7A6348] disabled:opacity-50 disabled:cursor-not-allowed">
                        Confirm Reassignment
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Reject reason modal — rejecting needs its own required reason, same rule as reschedule rejection --}}
<div id="reassignRejectModal" class="fixed inset-0 z-[60] hidden p-4 bg-black/50">
    <div class="w-full max-w-md mx-auto mt-24 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Reject Reassignment Request</h3>
        </div>
        <div class="px-6 py-4 space-y-3">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reason <span class="text-red-500">*</span></label>
            <textarea id="reassign_reject_reason" rows="3" minlength="5" maxlength="500"
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white resize-none"
                placeholder="Why is this request being rejected?"></textarea>
        </div>
        <div class="flex justify-end gap-2 px-6 py-4 bg-gray-50 dark:bg-gray-900/40 rounded-b-2xl">
            <button type="button" onclick="closeRejectReasonModal()"
                    class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                Cancel
            </button>
            <button type="button" onclick="confirmRejectReassignment()"
                    class="px-4 py-2 text-sm text-white bg-red-600 rounded-xl hover:bg-red-700">
                Confirm Reject
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    let currentReassignment = null; // holds the record for the request currently under review

    function esc(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    }

    function renderReassignmentList(requests) {
        const section = document.getElementById('reassignmentSection');
        const list    = document.getElementById('reassignmentList');
        const badge   = document.getElementById('reassignmentBadge');

        if (!requests.length) {
            section.classList.add('hidden');
            return;
        }

        section.classList.remove('hidden');
        badge.textContent = `${requests.length} Pending`;

        list.innerHTML = requests.map(r => `
            <div class="p-4 border rounded-2xl border-red-200 bg-red-50/60 dark:border-red-800 dark:bg-red-900/10">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="grid flex-1 grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Customer</p>
                            <p class="mt-1 font-medium text-gray-900 dark:text-white">${esc(r.customer_name)}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Service</p>
                            <p class="mt-1 font-medium text-gray-900 dark:text-white">${esc(r.treatment)}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Schedule</p>
                            <p class="mt-1 font-medium text-gray-900 dark:text-white">${esc(r.appointment_date)} · ${esc(r.start_time_fmt)}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Requested by</p>
                            <p class="mt-1 font-medium text-gray-900 dark:text-white">${esc(r.old_therapist)}</p>
                        </div>
                    </div>
                    <button type="button" onclick="openReassignReviewModal(this)"
                            data-id="${r.id}"
                            data-customer="${esc(r.customer_name)}"
                            data-treatment="${esc(r.treatment)}"
                            data-treatment-code="${esc(r.treatment_code)}"
                            data-appointment-date="${esc(r.appointment_date)}"
                            data-appointment-date-raw="${esc(r.appointment_date_raw)}"
                            data-start-time="${esc(r.start_time)}"
                            data-start-time-fmt="${esc(r.start_time_fmt)}"
                            data-old-therapist-id="${r.old_therapist_id}"
                            data-old-therapist="${esc(r.old_therapist)}"
                            data-reason="${esc(r.reason)}"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white rounded-xl bg-red-600 hover:bg-red-700 flex-shrink-0">
                        Review
                    </button>
                </div>
            </div>`).join('');
    }

    async function loadReassignments() {
        try {
            const res = await fetch('{{ route('reassignment.index') }}', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) return;
            renderReassignmentList(await res.json());
        } catch (err) {
            console.warn('Reassignment list poll failed:', err);
        }
    }

    window.openReassignReviewModal = async function (btn) {
        const d = btn.dataset;
        currentReassignment = {
            id: d.id,
            customer_name: d.customer,
            treatment: d.treatment,
            treatment_code: d.treatmentCode,
            appointment_date: d.appointmentDate,
            appointment_date_raw: d.appointmentDateRaw,
            start_time: d.startTime,
            start_time_fmt: d.startTimeFmt,
            old_therapist_id: d.oldTherapistId,
            old_therapist: d.oldTherapist,
            reason: d.reason,
        };

        document.getElementById('reassign_review_customer').textContent      = currentReassignment.customer_name;
        document.getElementById('reassign_review_treatment').textContent     = currentReassignment.treatment;
        document.getElementById('reassign_review_datetime').textContent      = `${currentReassignment.appointment_date} at ${currentReassignment.start_time_fmt}`;
        document.getElementById('reassign_review_old_therapist').textContent = currentReassignment.old_therapist;
        document.getElementById('reassign_review_reason').textContent        = `"${currentReassignment.reason}"`;
        document.getElementById('reassignReviewError').classList.add('hidden');

        const select = document.getElementById('reassign_review_therapist');
        select.innerHTML = '<option value="">Loading available therapists…</option>';
        document.getElementById('reassign_review_hint').classList.add('hidden');

        document.getElementById('reassignReviewModal').classList.remove('hidden');

        // Reuses the existing staff-booking availability endpoint — same
        // treatment/date/time as the original appointment — then drops the
        // therapist who's already flagged as unavailable from the results.
        try {
            const params = new URLSearchParams({
                treatment: currentReassignment.treatment_code,
                appointment_date: currentReassignment.appointment_date_raw,
                start_time: currentReassignment.start_time,
            });
            const res  = await fetch(`{{ route('booking.available-therapists') }}?${params}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();

            const available = (data.therapists || []).filter(t => Number(t.id) !== Number(currentReassignment.old_therapist_id));

            if (!available.length) {
                select.innerHTML = '<option value="">No therapist available for this slot</option>';
                document.getElementById('reassign_review_hint').classList.remove('hidden');
                return;
            }

            select.innerHTML = available.map(t =>
                `<option value="${t.id}" ${Number(data.recommended_id) === Number(t.id) ? 'selected' : ''}>${esc(t.name)}</option>`
            ).join('');
        } catch (err) {
            select.innerHTML = '<option value="">Failed to load therapists</option>';
        }
    };

    window.closeReassignReviewModal = function () {
        document.getElementById('reassignReviewModal').classList.add('hidden');
        currentReassignment = null;
    };

    window.approveReassignment = async function () {
        if (!currentReassignment) return;
        const therapistId = document.getElementById('reassign_review_therapist').value;
        const errorBox  = document.getElementById('reassignReviewError');
        const errorText = document.getElementById('reassignReviewErrorText');

        if (!therapistId) {
            errorText.textContent = 'Please select a replacement therapist.';
            errorBox.classList.remove('hidden');
            return;
        }

        const btn = document.getElementById('reassignApproveBtn');
        btn.disabled = true;
        btn.textContent = 'Saving…';

        try {
            const res = await fetch(`/reassignment-requests/${currentReassignment.id}/approve`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ new_therapist_id: therapistId }),
            });
            const data = await res.json();

            if (!res.ok) {
                errorText.textContent = data.message ?? 'Something went wrong.';
                errorBox.classList.remove('hidden');
                return;
            }

            closeReassignReviewModal();
            if (typeof showSpaToast === 'function') showSpaToast(data.message, 'success');
            loadReassignments();

        } catch (err) {
            errorText.textContent = 'Network error. Please try again.';
            errorBox.classList.remove('hidden');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Confirm Reassignment';
        }
    };

    window.rejectReassignment = function () {
        document.getElementById('reassign_reject_reason').value = '';
        document.getElementById('reassignRejectModal').classList.remove('hidden');
    };

    window.closeRejectReasonModal = function () {
        document.getElementById('reassignRejectModal').classList.add('hidden');
    };

    window.confirmRejectReassignment = async function () {
        if (!currentReassignment) return;
        const reason = document.getElementById('reassign_reject_reason').value.trim();

        if (reason.length < 5) {
            if (typeof showSpaToast === 'function') showSpaToast('Reason must be at least 5 characters.', 'error');
            return;
        }

        try {
            const res = await fetch(`/reassignment-requests/${currentReassignment.id}/reject`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ rejection_reason: reason }),
            });
            const data = await res.json();

            if (!res.ok) {
                if (typeof showSpaToast === 'function') showSpaToast(data.message ?? 'Something went wrong.', 'error');
                return;
            }

            closeRejectReasonModal();
            closeReassignReviewModal();
            if (typeof showSpaToast === 'function') showSpaToast(data.message, 'success');
            loadReassignments();

        } catch (err) {
            if (typeof showSpaToast === 'function') showSpaToast('Network error. Please try again.', 'error');
        }
    };

    // Initial load, then poll on the same cadence as this page's other live data.
    loadReassignments();
    setInterval(loadReassignments, 30000);
})();
</script>
@endif