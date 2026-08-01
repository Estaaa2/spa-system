{{--
    Reassignment Flag Modal (Therapist side)

    Include this ONCE from dashboard.blade.php, anywhere inside the
    @if($canMyToday) ... @endif block (it's only relevant to therapists).
    Suggested spot: right after the closing </div> of "my-next-wrapper".

        @include('dashboard.partials.reassignment-flag-modal') 
--}}
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
        document.getElementById('reassignFlagError').classList.add('hidden');

        document.getElementById('reassignFlagModal').classList.remove('hidden');
    };

    window.closeReassignFlagModal = function () {
        document.getElementById('reassignFlagModal').classList.add('hidden');
        reassignFlagBookingId = null;
    };

    document.getElementById('reassignFlagForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!reassignFlagBookingId) return;

        const reason    = document.getElementById('reassign_flag_reason').value.trim();
        const errorBox  = document.getElementById('reassignFlagError');
        const errorText = document.getElementById('reassignFlagErrorText');
        const submitBtn = document.getElementById('reassignFlagSubmitBtn');

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
                body: JSON.stringify({ reason }),
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

            // Grey out the flag button on this row so the therapist can't double-submit
            // before the dashboard's next 60s poll cycle catches up.
            const flagBtn = document.querySelector(`[data-reassign-flag-btn="${reassignFlagBookingId}"]`);
            if (flagBtn) {
                flagBtn.disabled = true;
                flagBtn.textContent = 'Request Sent';
                flagBtn.classList.add('opacity-50', 'cursor-not-allowed');
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