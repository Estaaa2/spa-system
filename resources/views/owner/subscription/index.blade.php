@extends('layouts.app')

@section('title', 'Subscription')
@section('content')

{{--
    Wrapper stays max-w-3xl rather than canon's max-w-7xl. This is a single-column
    settings page; canon only covers wide data pages, so the narrow measure is a
    deliberate choice here, not drift. Padding and vertical rhythm follow canon.
--}}
<div class="max-w-3xl p-4 mx-auto space-y-6 sm:p-6">

    <x-page-header
        title="Subscription"
        subtitle="Manage your spa subscription plan."
    />

    {{-- ── CURRENT PLAN ── --}}
    <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">

        <h2 class="text-base font-semibold text-gray-900 dark:text-white">
            Current Plan
        </h2>

        <div class="flex items-center justify-between gap-3 mt-4">

            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Tier
                </p>

                <p class="text-xl font-bold text-gray-900 capitalize dark:text-white">
                    {{ $spa->business_tier }}
                </p>
            </div>

            <div class="text-right">
                @if($spa->business_tier === 'professional')
                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                        Active
                    </span>
                @else
                    <span class="px-3 py-1 text-sm font-semibold text-gray-700 bg-gray-200 rounded-full dark:bg-gray-700 dark:text-gray-200">
                        Basic
                    </span>
                @endif
            </div>

        </div>

        {{-- Expiry line only appears while the subscription is still in date. --}}
        @if($subscription && $subscription->expires_at && $subscription->expires_at->isFuture())
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                Subscription expires on
                <span class="font-semibold">
                    {{ $subscription->expires_at->format('F d, Y') }}
                </span>
            </p>
        @endif

    </div>


    {{-- ── UPGRADE (BASIC ONLY) ── --}}
    @if($spa->business_tier !== 'professional')

    <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">

        <h2 class="text-base font-semibold text-gray-900 dark:text-white">
            Upgrade to Professional
        </h2>

        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            Unlock advanced features to grow your spa business.
        </p>

        <ul class="mt-6 space-y-2 text-sm text-gray-600 list-disc list-inside dark:text-gray-300">
            <li>Branch public listing</li>
            <li>Customer online reservation</li>
            <li>Enhanced decision support tools</li>
            <li>Priority support</li>
            <li>Unlimited staff and branches</li>
        </ul>

        <p class="mt-6 text-xl font-bold text-gray-900 dark:text-white">
            ₱200 / month
        </p>

        <form action="{{ route('owner.subscription.checkout') }}" method="POST" class="mt-6">
            @csrf

            <button
                type="submit"
                class="w-full min-h-[44px] px-4 py-2 text-sm font-medium text-white bg-[#8B7355] rounded-xl hover:bg-[#7A6348]
                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800">
                Upgrade to Professional
            </button>
        </form>

    </div>

    @endif


    {{-- ── CANCEL (PROFESSIONAL ONLY) ── --}}
    @if($spa->business_tier === 'professional')

    <div class="p-4 border shadow-sm sm:p-5 border-red-200 bg-red-50 rounded-2xl dark:border-red-800 dark:bg-red-900/10">

        <h2 class="text-base font-semibold text-red-900 dark:text-red-200">
            Cancel Subscription
        </h2>

        <p class="mt-2 text-sm text-red-700 dark:text-red-300">
            Cancelling will downgrade your spa to the Basic tier immediately, and you will lose access to all Professional features.
        </p>

        <button
            type="button"
            onclick="openCancelSubModal()"
            class="min-h-[44px] mt-4 px-4 py-2 text-sm font-medium text-white bg-red-700 rounded-xl hover:bg-red-800
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
            Cancel Subscription
        </button>

    </div>

    @endif

</div>


{{--
    The modal sits outside the max-w-3xl wrapper on purpose. That wrapper uses
    space-y-6, which would put a margin-top on a fixed inset-0 element and shift
    the overlay down by 24px.
--}}
@if($spa->business_tier === 'professional')

{{-- ── CANCEL CONFIRMATION ── --}}
<div id="cancelSubModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="alertdialog" aria-modal="true" aria-labelledby="cancelSubModalTitle" aria-describedby="cancelSubModalDesc"
             class="w-full max-w-md p-6 bg-white shadow-xl rounded-2xl dark:bg-gray-800">

            <h2 id="cancelSubModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">
                Downgrade to Basic?
            </h2>

            <p id="cancelSubModalDesc" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                This immediately downgrades your spa from Professional to the Basic tier. You'll lose
                branch listing, online reservations, decision support tools, priority support, and
                unlimited staff/branches right away. Your spa account itself stays open — only the
                plan changes. This cannot be undone.
            </p>

            <div class="flex flex-col-reverse gap-2 mt-6 sm:flex-row sm:justify-end">

                <button
                    type="button"
                    onclick="closeCancelSubModal()"
                    class="min-h-[44px] px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-xl hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                    Keep Professional
                </button>

                <form action="{{ route('owner.subscription.cancel-subscription') }}" method="POST">
                    @csrf
                    <button
                        type="submit"
                        class="w-full min-h-[44px] px-4 py-2 text-sm font-medium text-white bg-red-700 rounded-xl hover:bg-red-800 sm:w-auto">
                        Yes, Downgrade to Basic
                    </button>
                </form>

            </div>

        </div>
    </div>
</div>

<script>
// Modal behaviour ported from appointments.blade.php: Escape closes, Tab is kept
// inside the dialog, focus returns to whatever opened it. Backdrop click closes
// too — this dialog holds no typed input, so a stray tap costs nothing.
(function () {
    'use strict';

    const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    const modal = document.getElementById('cancelSubModal');
    let lastFocused = null;

    if (!modal) return;

    function open() {
        lastFocused = document.activeElement;
        modal.classList.remove('hidden');
        // Land on "Keep Professional", not the destructive button.
        const target = modal.querySelector(FOCUSABLE);
        if (target) target.focus();
    }

    function close() {
        modal.classList.add('hidden');
        if (lastFocused && document.contains(lastFocused)) lastFocused.focus();
        lastFocused = null;
    }

    document.addEventListener('keydown', function (e) {
        if (modal.classList.contains('hidden')) return;

        if (e.key === 'Escape') {
            e.preventDefault();
            close();
            return;
        }
        if (e.key !== 'Tab') return;

        const items = Array.from(modal.querySelectorAll(FOCUSABLE))
            .filter(el => el.offsetParent !== null);
        if (!items.length) return;

        const first = items[0];
        const last  = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault(); last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault(); first.focus();
        }
    });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) close();
    });

    window.openCancelSubModal  = open;
    window.closeCancelSubModal = close;
})();
</script>

@endif

@endsection