{{--
    Home Visits (staff verification Phase 5): online home-service bookings.

    - The assigned therapist sees their visits for today and the next 7 days, with
      the customer's name, phone, address and whether the customer's account email
      is verified (D8), and enters the arrival code the customer tells them (D7).
    - Staff who can edit appointments see today's visits at their branch, whether
      each therapist has arrived, and can confirm by hand with a reason (Q42a).

    The arrival code itself is never sent to this page. Included from dashboard
    and appointments; renders nothing when there are no visits.
--}}
@php
    $visitUser = auth()->user();
    $visits = $visitUser ? app(\App\Services\Bookings\ArrivalService::class)->visitsFor($visitUser) : ['mine' => collect(), 'branch' => collect()];
    $myVisits = $visits['mine'];
    $branchVisits = $visits['branch']->reject(fn ($b) => (int) $b->therapist_id === (int) $visitUser?->id)->values();
    $arrivalErrors = $errors->arrival;
    $maxTries = \App\Services\Bookings\ArrivalService::MAX_ATTEMPTS;

    $arrivalStatus = function ($b) use ($maxTries) {
        if ($b->arrival_confirmed_at) {
            $how = $b->arrival_method === 'manual'
                ? 'confirmed by ' . ($b->arrivalConfirmedBy?->first_name ?? 'the branch')
                : 'code entered';

            return ['Arrived ' . $b->arrival_confirmed_at->format('g:i A') . ' (' . $how . ')', 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'];
        }

        if ($b->arrival_locked_at) {
            return ['Code locked after ' . $maxTries . ' wrong tries', 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'];
        }

        if (! $b->appointment_date->isToday()) {
            return ['Upcoming', 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'];
        }

        return ['Not arrived yet', 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'];
    };
@endphp

@if ($myVisits->isNotEmpty() || $branchVisits->isNotEmpty())
<div id="home-visits" class="overflow-hidden bg-white border border-gray-200 shadow-sm scroll-mt-6 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
    <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Home Visits</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Online home-service bookings. On arrival, the therapist asks the customer for their 4-digit arrival code.
        </p>
    </div>

    @if ($arrivalErrors && $arrivalErrors->any())
        <div class="px-4 pt-4 sm:px-6">
            <p class="p-3 text-sm text-red-700 border border-red-200 bg-red-50 rounded-xl dark:bg-red-900/10 dark:border-red-800 dark:text-red-300" role="alert">
                {{ $arrivalErrors->first() }}
            </p>
        </div>
    @endif

    {{-- ── My visits (assigned therapist) ── --}}
    @if ($myVisits->isNotEmpty())
        <div class="px-4 pt-4 sm:px-6">
            <h3 class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Your visits</h3>
        </div>
        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach ($myVisits as $visit)
                @php
                    [$statusText, $statusClass] = $arrivalStatus($visit);
                    $canEnter = $visit->appointment_date->isToday() && ! $visit->arrival_confirmed_at && ! $visit->arrival_locked_at;
                @endphp
                <li class="flex flex-col gap-3 px-4 py-4 sm:px-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="grid flex-1 grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Customer</p>
                            <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $visit->customer_name }}</p>
                            @if ($visit->customer?->hasVerifiedEmail())
                                <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 text-[11px] font-semibold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300"
                                      title="The customer booked from an account whose email address was confirmed. This is not an ID check.">
                                    <i class="fa-solid fa-envelope-circle-check" aria-hidden="true"></i> Email verified
                                </span>
                            @endif
                            @if ($visit->customer_phone)
                                <a href="tel:{{ $visit->customer_phone }}" class="flex items-center gap-1 mt-1 min-h-[44px] text-[#8B7355] hover:text-[#6F5430] dark:text-[#C4A97D] dark:hover:text-[#D8C09A]">
                                    <i class="fa-solid fa-phone" aria-hidden="true"></i> {{ $visit->customer_phone }}
                                </a>
                            @endif
                        </div>
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Address</p>
                            <p class="mt-1 text-gray-900 dark:text-white">{{ $visit->customer_address ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">When</p>
                            <p class="mt-1 text-gray-900 dark:text-white">
                                {{ $visit->appointment_date->isToday() ? 'Today' : $visit->appointment_date->format('D, M j') }},
                                {{ \Carbon\Carbon::parse($visit->start_time)->format('g:i A') }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $visit->treatment_label }}</p>
                            <span class="inline-flex mt-1 px-2.5 py-1 text-xs font-medium rounded-full {{ $statusClass }}">{{ $statusText }}</span>
                        </div>
                    </div>
                    @if ($canEnter)
                        <button type="button"
                                data-arrival-open="code"
                                data-action="{{ route('appointments.arrival', $visit) }}"
                                data-customer="{{ $visit->customer_name }}"
                                data-tries="{{ $maxTries - $visit->arrival_attempts }}"
                                class="inline-flex items-center justify-center gap-2 min-h-[44px] rounded-xl bg-[#8B7355] px-4 py-2 text-sm font-medium text-white hover:bg-[#7A6348] shrink-0">
                            <i class="fa-solid fa-key" aria-hidden="true"></i> Enter arrival code
                        </button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    {{-- ── Branch view (edit appointments) ── --}}
    @if ($branchVisits->isNotEmpty())
        <div class="px-4 pt-4 sm:px-6 {{ $myVisits->isNotEmpty() ? 'border-t border-gray-200 dark:border-gray-700' : '' }}">
            <h3 class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Today at this branch</h3>
        </div>
        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach ($branchVisits as $visit)
                @php
                    [$statusText, $statusClass] = $arrivalStatus($visit);
                    $canManual = ! $visit->arrival_confirmed_at && $visit->therapist_id;
                @endphp
                <li class="flex flex-col gap-3 px-4 py-4 sm:px-6 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm">
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ \Carbon\Carbon::parse($visit->start_time)->format('g:i A') }} · {{ $visit->customer_name }}
                        </p>
                        <p class="text-gray-500 dark:text-gray-400">
                            {{ $visit->therapist ? trim($visit->therapist->first_name . ' ' . $visit->therapist->last_name) : 'No therapist assigned' }}
                            · {{ $visit->customer_address }}
                        </p>
                        <span class="inline-flex mt-1 px-2.5 py-1 text-xs font-medium rounded-full {{ $statusClass }}">{{ $statusText }}</span>
                        @if ($visit->arrival_method === 'manual' && $visit->arrival_manual_reason)
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Reason: {{ $visit->arrival_manual_reason }}</p>
                        @endif
                    </div>
                    @if ($canManual)
                        <button type="button"
                                data-arrival-open="manual"
                                data-action="{{ route('appointments.arrival.manual', $visit) }}"
                                data-customer="{{ $visit->customer_name }}"
                                class="inline-flex items-center justify-center gap-2 min-h-[44px] px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 shrink-0">
                            <i class="fa-solid fa-user-check" aria-hidden="true"></i> Confirm without code
                        </button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>

{{-- ═══ Enter arrival code ═══ --}}
<div id="arrivalCodeModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="arrivalCodeTitle"
             class="w-full max-w-sm bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div>
                    <h2 id="arrivalCodeTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Enter arrival code</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Ask <span data-arrival-customer></span> for the 4-digit code on their booking.</p>
                </div>
                <button type="button" data-arrival-close aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <form method="POST" class="px-4 py-6 space-y-4 sm:px-6" data-arrival-form>
                @csrf
                <div>
                    <label for="arrival_code_input" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Arrival code</label>
                    <input id="arrival_code_input" name="arrival_code" type="text" inputmode="numeric" pattern="[0-9]{4}" maxlength="4"
                           autocomplete="one-time-code" required placeholder="••••"
                           class="w-full min-h-[44px] px-3 py-2 text-2xl font-semibold tracking-[0.5em] text-center border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><span data-arrival-tries></span> tries left before the code locks.</p>
                </div>
                <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                    <button type="button" data-arrival-close
                            class="min-h-[44px] px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                        Cancel
                    </button>
                    <button type="submit" class="min-h-[44px] rounded-xl bg-[#8B7355] px-4 py-2 text-sm font-medium text-white hover:bg-[#7A6348]">
                        Confirm arrival
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═══ Confirm without code (branch) ═══ --}}
<div id="arrivalManualModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="arrivalManualTitle"
             class="w-full max-w-md bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div>
                    <h2 id="arrivalManualTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Confirm arrival without the code</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">For <span data-arrival-customer></span>. Your name and the reason are recorded.</p>
                </div>
                <button type="button" data-arrival-close aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <form method="POST" class="px-4 py-6 space-y-4 sm:px-6" data-arrival-form>
                @csrf
                <div class="flex items-start gap-3 p-3 border border-amber-200 bg-amber-50 rounded-xl dark:bg-amber-900/10 dark:border-amber-800">
                    <i class="fa-solid fa-phone mt-0.5 text-amber-600 dark:text-amber-400" aria-hidden="true"></i>
                    <p class="text-sm text-amber-800 dark:text-amber-300">Call the customer first and check that the right person arrived.</p>
                </div>
                <div>
                    <label for="arrival_reason_input" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Why couldn't the code be used?</label>
                    <textarea id="arrival_reason_input" name="arrival_reason" rows="3" maxlength="500" required minlength="5"
                              placeholder="e.g. Customer's phone is dead; called them and they confirmed the therapist arrived."
                              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-700 dark:text-white"></textarea>
                </div>
                <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                    <button type="button" data-arrival-close
                            class="min-h-[44px] px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                        Cancel
                    </button>
                    <button type="submit" class="min-h-[44px] rounded-xl bg-[#8B7355] px-4 py-2 text-sm font-medium text-white hover:bg-[#7A6348]">
                        Confirm arrival
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    // Self-contained (this partial is included on several pages): open/close,
    // Escape, focus in and back out, double-submit guard. Same behaviour as the
    // modals in appointments.blade.php.
    const MODALS = { code: 'arrivalCodeModal', manual: 'arrivalManualModal' };
    let openId = null;
    let returnFocus = null;

    function open(kind, btn) {
        const modal = document.getElementById(MODALS[kind]);
        if (!modal) return;
        returnFocus = btn;
        modal.querySelector('form').action = btn.dataset.action;
        modal.querySelectorAll('[data-arrival-customer]').forEach(el => { el.textContent = btn.dataset.customer || 'the customer'; });
        modal.querySelectorAll('[data-arrival-tries]').forEach(el => { el.textContent = btn.dataset.tries || ''; });
        modal.querySelectorAll('input[type=text], textarea').forEach(el => { el.value = ''; });
        modal.classList.remove('hidden');
        openId = modal.id;
        modal.querySelector('input[type=text], textarea')?.focus();
    }

    function close() {
        if (!openId) return;
        document.getElementById(openId).classList.add('hidden');
        openId = null;
        if (returnFocus && document.contains(returnFocus)) returnFocus.focus();
        returnFocus = null;
    }

    document.querySelectorAll('[data-arrival-open]').forEach(btn =>
        btn.addEventListener('click', () => open(btn.dataset.arrivalOpen, btn)));
    document.querySelectorAll('[data-arrival-close]').forEach(btn => btn.addEventListener('click', close));

    document.addEventListener('keydown', e => {
        if (!openId) return;
        if (e.key === 'Escape') { e.preventDefault(); close(); return; }
        if (e.key !== 'Tab') return;
        const items = Array.from(document.getElementById(openId).querySelectorAll('button:not([disabled]), input, textarea'))
            .filter(el => el.offsetParent !== null);
        if (!items.length) return;
        const first = items[0], last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });

    // Digits only in the code box.
    document.getElementById('arrival_code_input')?.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 4);
    });

    document.querySelectorAll('form[data-arrival-form]').forEach(form => {
        form.addEventListener('submit', e => {
            if (form.dataset.submitting === 'true') { e.preventDefault(); return; }
            if (!form.checkValidity()) return;
            form.dataset.submitting = 'true';
            const btn = form.querySelector('button[type=submit]');
            if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; btn.classList.add('opacity-70', 'cursor-not-allowed'); }
        });
    });
}());
</script>
@endif
