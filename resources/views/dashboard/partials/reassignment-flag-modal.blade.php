<!-- Reassignment Flag Modal -->
<div id="reassignFlagModal" class="fixed inset-0 z-50 hidden p-4 bg-black/50">
    <div class="w-full max-w-lg mx-auto mt-16 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Can't Make This Appointment?</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">The front desk will be notified immediately and will assign a replacement.</p>
            </div>
            <button type="button" onclick="closeReassignFlagModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">✕</button>
        </div>

        <form id="reassignFlagForm" class="px-6 py-6 space-y-4">
            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-900/40">
                <p class="text-sm font-semibold text-gray-900 dark:text-white" id="reassign_flag_customer"></p>
                <p class="text-sm text-gray-500 dark:text-gray-400" id="reassign_flag_treatment"></p>
                <p class="mt-1 text-xs text-gray-400" id="reassign_flag_datetime"></p>
            </div>

            <div>
                <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                    Reason <span class="text-red-500">*</span>
                </label>
                <textarea id="reassign_flag_reason" name="reason" rows="3" minlength="10" maxlength="1000" required
                    placeholder="e.g. Family emergency, need to leave early today..."
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white resize-none"></textarea>
                <p class="mt-1 text-xs text-gray-400">Minimum 10 characters. This is shown to whoever approves the reassignment.</p>
            </div>

            <div class="p-3 border border-dashed rounded-xl border-gray-300 dark:border-gray-600">
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" id="reassign_also_leave" onchange="toggleReassignLeaveType()"
                        class="mt-0.5 border-gray-300 rounded text-[#8B7355] focus:ring-[#8B7355]">
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        Also request leave for this day — this submits a leave request using the same reason above, so your attendance reflects it too.
                    </span>
                </label>
                <div id="reassign_leave_type_wrapper" class="hidden mt-3">
                    <label class="block mb-1 text-xs font-medium text-gray-600 dark:text-gray-400">Leave Type</label>
                    <select id="reassign_leave_type" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="sick">Sick</option>
                        <option value="emergency">Emergency</option>
                        <option value="personal">Personal</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>

            <div id="reassignFlagError" class="hidden p-3 text-sm text-red-600 rounded-xl bg-red-50 ring-1 ring-red-200 dark:bg-red-900/20 dark:ring-red-800">
                <span id="reassignFlagErrorText"></span>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeReassignFlagModal()"
                        class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                    Cancel
                </button>
                <button type="submit" id="reassignFlagSubmitBtn"
                        class="rounded-xl bg-[#8B7355] px-4 py-2 text-sm font-medium text-white hover:bg-[#7A6348] disabled:opacity-50 disabled:cursor-not-allowed">
                    Submit Request
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    let reassignFlagBookingId = null;

    window.openReassignFlagModal = function (btn) {
        const d = btn.dataset;
        reassignFlagBookingId = d.id;

        document.getElementById('reassign_flag_customer').textContent  = d.customer || 'Customer';
        document.getElementById('reassign_flag_treatment').textContent = d.treatment || '';
        document.getElementById('reassign_flag_datetime').textContent  = `${d.date || ''} at ${d.time || ''}`;
        document.getElementById('reassign_flag_reason').value = '';
        document.getElementById('reassign_also_leave').checked = false;
        document.getElementById('reassign_leave_type_wrapper').classList.add('hidden');
        document.getElementById('reassignFlagError').classList.add('hidden');

        document.getElementById('reassignFlagModal').classList.remove('hidden');
    };

    window.closeReassignFlagModal = function () {
        document.getElementById('reassignFlagModal').classList.add('hidden');
        reassignFlagBookingId = null;
    };

    window.toggleReassignLeaveType = function () {
        const checked = document.getElementById('reassign_also_leave').checked;
        document.getElementById('reassign_leave_type_wrapper').classList.toggle('hidden', !checked);
    };

    document.getElementById('reassignFlagForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!reassignFlagBookingId) return;

        const reason    = document.getElementById('reassign_flag_reason').value.trim();
        const errorBox  = document.getElementById('reassignFlagError');
        const errorText = document.getElementById('reassignFlagErrorText');
        const submitBtn = document.getElementById('reassignFlagSubmitBtn');
        const alsoLeave = document.getElementById('reassign_also_leave').checked;
        const leaveType = document.getElementById('reassign_leave_type').value;

        if (reason.length < 10) {
            errorText.textContent = 'Please provide at least 10 characters explaining why.';
            errorBox.classList.remove('hidden');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch(`/appointments/${reassignFlagBookingId}/reassignment-requests`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken ?? '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    reason,
                    request_leave_too: alsoLeave,
                    leave_type: alsoLeave ? leaveType : null,
                }),
            });

            const data = await res.json();

            if (!res.ok) {
                errorText.textContent = data.message ?? 'Something went wrong. Please try again.';
                errorBox.classList.remove('hidden');
                return;
            }

            closeReassignFlagModal();
            if (typeof showSpaToast === 'function') {
                showSpaToast(data.message ?? 'Reassignment request submitted.', 'success');
            }

            const flagBtn = document.querySelector(`[data-reassign-flag-btn="${reassignFlagBookingId}"]`);
            if (flagBtn) {
                const badge = document.createElement('span');
                badge.className = 'mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 text-[10px] font-semibold text-amber-700 bg-amber-100 rounded-lg dark:bg-amber-900/30 dark:text-amber-300';
                badge.innerHTML = '<i class="fa-solid fa-clock"></i> Reassignment Pending';
                flagBtn.replaceWith(badge);
            }

        } catch (err) {
            errorText.textContent = 'Network error. Please try again.';
            errorBox.classList.remove('hidden');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Request';
        }
    });
})();
</script>