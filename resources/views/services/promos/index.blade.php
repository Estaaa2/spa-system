@extends('layouts.app')

@section('title', 'Promos & Discounts')

@section('content')
@php
    // ─────────────────────────────────────────────────────────────────────
    // ⚠️ RBAC — UNRESOLVED, DELIBERATELY NOT WIRED (finding B1).
    //
    // services/index.blade.php gates its link here on a *view* permission,
    // then this page renders create / edit / delete unconditionally. The
    // correct permission strings must come from RolePermissionSeeder — they
    // are not guessed here.
    //
    // These three flags are the single wiring point. They are hard-coded to
    // true so behaviour is IDENTICAL to the version this replaces (no silent
    // lockout). Replace each with the real check once the strings are
    // confirmed, e.g.:
    //     $canCreatePromos = auth()->user()?->hasBranchPermission('<name>') ?? false;
    // ─────────────────────────────────────────────────────────────────────
    $canCreatePromos = true;
    $canEditPromos   = true;
    $canDeletePromos = true;

    $showRowActions = $canEditPromos || $canDeletePromos;

    // Status pill colours. Defined once here and read by Blade only — this page
    // has no polling script rebuilding rows, so there is no JS copy to drift.
    // Values follow design-language §6; the "Inactive" arm uses the slate
    // fallback recommended in N13 rather than a bare gray.
    $promoStatusClasses = [
        'inactive' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
        'expired'  => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        'upcoming' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        'active'   => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    ];

    $promoStatusLabels = [
        'inactive' => 'Inactive',
        'expired'  => 'Expired',
        'upcoming' => 'Upcoming',
        'active'   => 'Active',
    ];

    // Button tokens, matching appointments.blade.php's $btnBase / $btn map.
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $btn = [
        'primary'   => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'secondary' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                     . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        'remove'    => $btnBase . ' bg-red-700 text-white hover:bg-red-800',
    ];

    // Icon-only row actions. Same 44px floor, no label, so they carry aria-label.
    $iconBtnBase = 'inline-flex items-center justify-center min-h-[44px] min-w-[44px] rounded-xl transition-colors '
                 . 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] '
                 . 'focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $iconBtn = [
        'edit'   => $iconBtnBase . ' text-gray-500 hover:bg-gray-100 hover:text-gray-700 '
                  . 'dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200',
        'delete' => $iconBtnBase . ' text-red-600 hover:bg-red-50 hover:text-red-700 '
                  . 'dark:text-red-400 dark:hover:bg-red-900/20 dark:hover:text-red-300',
    ];

    $inputBase = 'w-full min-h-[44px] px-3 py-2 mt-1 text-sm border border-gray-300 rounded-xl '
               . 'focus:border-[#8B7355] focus:ring-[#8B7355] '
               . 'dark:border-gray-600 dark:bg-gray-700 dark:text-white';

    // Named route for update is not confirmed to exist; promos.store and
    // promos.destroy are (both were already in use in this file). If the name
    // is missing we fall back to the path the previous version hard-coded, so
    // nothing breaks — but confirm with `php artisan route:list --name=promos`
    // and delete the fallback once you know.
    $promoUpdateUrl = \Illuminate\Support\Facades\Route::has('promos.update')
        ? route('promos.update', '__ID__')
        : url('/services/promos/__ID__');

    // The page is only reachable from Services and previously had no way back.
    // Guarded so an unexpected route name can't 500 the page.
    $servicesUrl = \Illuminate\Support\Facades\Route::has('services.index')
        ? route('services.index')
        : null;
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">

    {{-- ── Header + toolbar ── --}}
    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            @if($servicesUrl)
                {{-- Negative margin keeps the 44px hit area from making the row taller. --}}
                <a href="{{ $servicesUrl }}"
                   class="inline-flex items-center gap-2 min-h-[44px] -my-2 px-2 text-sm font-medium rounded-xl
                          text-[#8B7355] hover:text-[#7A6348] dark:text-[#C4A97D]
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355]
                          focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <i class="text-xs fa-solid fa-arrow-left" aria-hidden="true"></i>
                    Back to Services
                </a>
            @else
                <span></span>
            @endif

            @if($canCreatePromos)
                <button type="button" data-promo-open class="{{ $btn['primary'] }}">
                    <i class="text-xs fa-solid fa-plus" aria-hidden="true"></i>
                    New Promo
                </button>
            @endif
        </div>

        <x-page-header
            title="Promos & Discounts"
            subtitle="Run limited-time discounts on specific treatments or packages."
        />
    </div>

    {{-- ── Promo list ── --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        @if($promos->isEmpty())
            <div class="flex flex-col items-center justify-center px-4 py-16 text-center">
                <i class="mb-3 text-3xl text-gray-400 fa-solid fa-tag dark:text-gray-500" aria-hidden="true"></i>
                <p class="text-sm text-gray-500 dark:text-gray-400">No promos yet.</p>
                @if($canCreatePromos)
                    <button type="button" data-promo-open
                            class="inline-flex items-center min-h-[44px] mt-2 px-2 text-sm font-semibold rounded-xl
                                   text-[#8B7355] hover:text-[#7A6348] dark:text-[#C4A97D]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355]
                                   focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800">
                        Create your first promo
                        <i class="ml-1.5 text-xs fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </button>
                @endif
            </div>
        @else
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($promos as $promo)
                    @php
                        $start = \Illuminate\Support\Carbon::parse($promo->start_date);
                        $end   = \Illuminate\Support\Carbon::parse($promo->end_date);

                        $statusKey = ! $promo->is_active ? 'inactive'
                            : ($end->isPast() ? 'expired'
                            : ($start->isFuture() ? 'upcoming' : 'active'));

                        // Reads the relations already loaded for the "Applies to" line —
                        // no extra query, unlike the old ->load() inside the loop.
                        $treatmentIds = $promo->treatments->pluck('id')->values();
                        $packageIds   = $promo->packages->pluck('id')->values();
                        $appliesTo    = $promo->treatments->pluck('name')
                                            ->merge($promo->packages->pluck('name'));

                        // Only the fields the edit form actually needs, rather than the
                        // whole model. Dates are pre-formatted because <input type="date">
                        // silently blanks anything that isn't exactly YYYY-MM-DD.
                        $promoPayload = [
                            'id'             => $promo->id,
                            'name'           => $promo->name,
                            'discount_type'  => $promo->discount_type,
                            'discount_value' => $promo->discount_value,
                            'start_date'     => $start->format('Y-m-d'),
                            'end_date'       => $end->format('Y-m-d'),
                            'is_active'      => (bool) $promo->is_active,
                            'treatment_ids'  => $treatmentIds,
                            'package_ids'    => $packageIds,
                        ];
                    @endphp

                    <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-6">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-semibold text-gray-900 truncate dark:text-white">{{ $promo->name }}</p>
                                <span class="shrink-0 px-2 py-0.5 text-[11px] font-semibold rounded-full {{ $promoStatusClasses[$statusKey] }}">
                                    {{ $promoStatusLabels[$statusKey] }}
                                </span>
                            </div>

                            <div class="flex flex-wrap items-center mt-1 text-xs text-gray-500 gap-x-2 gap-y-0.5 dark:text-gray-400">
                                <span>{{ $promo->discount_type === 'percent'
                                    ? $promo->discount_value . '% off'
                                    : '₱' . number_format($promo->discount_value, 2) . ' off' }}</span>
                                <span aria-hidden="true">•</span>
                                <span>{{ $start->format('M d, Y') }} – {{ $end->format('M d, Y') }}</span>
                            </div>

                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Applies to: {{ $appliesTo->isNotEmpty() ? $appliesTo->implode(', ') : '—' }}
                            </p>
                        </div>

                        @if($showRowActions)
                            <div class="flex items-center gap-2 shrink-0">
                                @if($canEditPromos)
                                    <button type="button"
                                            data-promo-edit='@json($promoPayload)'
                                            aria-label="Edit promo {{ $promo->name }}"
                                            class="{{ $iconBtn['edit'] }}">
                                        <i class="text-sm fa-solid fa-pen" aria-hidden="true"></i>
                                    </button>
                                @endif

                                @if($canDeletePromos)
                                    <button type="button"
                                            data-promo-delete="{{ $promo->id }}"
                                            data-promo-name="{{ $promo->name }}"
                                            aria-label="Delete promo {{ $promo->name }}"
                                            class="{{ $iconBtn['delete'] }}">
                                        <i class="text-sm fa-solid fa-trash" aria-hidden="true"></i>
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>{{-- end max-w-7xl --}}


{{-- ═══════════════════════════════════════════════════
     PROMO MODAL (create + edit, shared)
     ═══════════════════════════════════════════════════ --}}
@if($canCreatePromos || $canEditPromos)
<div id="promoModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="promoModalTitle"
             class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">

            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div>
                    <h2 id="promoModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">New Promo</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Set the discount, the dates, and what it applies to.</p>
                </div>
                <button type="button" data-promo-close aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <form id="promoForm" method="POST">
                @csrf
                <input type="hidden" name="_method" id="promoFormMethod" value="POST">

                <div class="px-4 py-6 space-y-4 sm:px-6">

                    {{-- Sits at the top of the panel so a submit-time error is visible
                         without scrolling back up. --}}
                    <div id="promoError" role="alert"
                         class="hidden p-3 text-sm text-red-700 rounded-xl bg-red-50 ring-1 ring-red-200 dark:bg-red-900/20 dark:ring-red-800 dark:text-red-300"></div>

                    {{-- Shown only when an edit payload references treatments or packages
                         that aren't in the lists below (deleted, or on another branch).
                         Saving would drop those links silently. --}}
                    <div id="promoOrphanNotice"
                         class="hidden p-3 text-sm border rounded-xl border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-900/10 dark:text-amber-300"></div>

                    <div>
                        <label for="promoName" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Promo Name</label>
                        <input type="text" name="name" id="promoName" required placeholder="e.g. Summer Sale"
                               class="{{ $inputBase }}">
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label for="promoDiscountType" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Discount Type</label>
                            <select name="discount_type" id="promoDiscountType" required class="{{ $inputBase }}">
                                <option value="percent">% Off</option>
                                <option value="fixed">₱ Off</option>
                            </select>
                        </div>
                        <div>
                            <label for="promoDiscountValue" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Discount Value</label>
                            <input type="number" name="discount_value" id="promoDiscountValue" step="0.01" min="0" required placeholder="20"
                                   class="{{ $inputBase }}">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label for="promoStartDate" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Start Date</label>
                            <input type="date" name="start_date" id="promoStartDate" required class="{{ $inputBase }}">
                        </div>
                        <div>
                            <label for="promoEndDate" class="block text-sm font-medium text-gray-700 dark:text-gray-300">End Date</label>
                            <input type="date" name="end_date" id="promoEndDate" required class="{{ $inputBase }}">
                        </div>
                    </div>

                    <div id="promoActiveWrapper" class="hidden">
                        <label class="inline-flex items-center gap-2 min-h-[44px] cursor-pointer">
                            <input type="checkbox" name="is_active" id="promoIsActive" value="1" checked
                                   class="w-4 h-4 rounded accent-[#8B7355] focus:ring-[#8B7355]">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Promo is active</span>
                        </label>
                    </div>

                    <hr class="border-gray-200 dark:border-gray-700">

                    <div>
                        <p id="promoTreatmentsLabel" class="mb-2 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Apply to Treatments</p>
                        <div role="group" aria-labelledby="promoTreatmentsLabel"
                             class="grid grid-cols-1 gap-1.5 pr-1 overflow-y-auto overscroll-contain max-h-40">
                            @forelse($treatments as $t)
                                <label class="flex items-center justify-between gap-2 px-3 py-2 text-sm rounded-xl cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <span class="text-gray-700 truncate dark:text-gray-200">{{ $t->name }}</span>
                                    <span class="flex items-center gap-2 shrink-0">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">₱{{ number_format($t->price, 2) }}</span>
                                        <input type="checkbox" name="treatment_ids[]" value="{{ $t->id }}"
                                               class="promo-treatment-checkbox w-4 h-4 rounded accent-[#8B7355] focus:ring-[#8B7355]">
                                    </span>
                                </label>
                            @empty
                                <p class="text-xs italic text-gray-500 dark:text-gray-400">No treatments available.</p>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <p id="promoPackagesLabel" class="mb-2 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Apply to Packages</p>
                        <div role="group" aria-labelledby="promoPackagesLabel"
                             class="grid grid-cols-1 gap-1.5 pr-1 overflow-y-auto overscroll-contain max-h-40">
                            @forelse($packages as $p)
                                <label class="flex items-center justify-between gap-2 px-3 py-2 text-sm rounded-xl cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <span class="text-gray-700 truncate dark:text-gray-200">{{ $p->name }}</span>
                                    <span class="flex items-center gap-2 shrink-0">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">₱{{ number_format($p->price, 2) }}</span>
                                        <input type="checkbox" name="package_ids[]" value="{{ $p->id }}"
                                               class="promo-package-checkbox w-4 h-4 rounded accent-[#8B7355] focus:ring-[#8B7355]">
                                    </span>
                                </label>
                            @empty
                                <p class="text-xs italic text-gray-500 dark:text-gray-400">No packages available.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                        <button type="button" data-promo-close class="{{ $btn['secondary'] }}">Cancel</button>
                        <button type="submit" id="promoSubmitBtn" class="{{ $btn['primary'] }}">Save Promo</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endif


{{-- ═══════════════════════════════════════════════════
     DELETE MODAL
     ═══════════════════════════════════════════════════ --}}
@if($canDeletePromos)
<div id="promoDeleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="alertdialog" aria-modal="true" aria-labelledby="promoDeleteTitle" aria-describedby="promoDeleteDesc"
             class="w-full max-w-md p-6 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <h2 id="promoDeleteTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Delete Promo</h2>
            <p id="promoDeleteDesc" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                This permanently deletes <span id="promoDeleteName" class="font-medium text-gray-700 dark:text-gray-200"></span>
                and removes it from every treatment and package it was applied to. To stop it running
                without deleting the record, close this and untick “Promo is active” in Edit instead.
            </p>
            <div class="flex flex-col-reverse gap-2 mt-6 sm:flex-row sm:justify-end">
                <button type="button" data-promo-delete-close class="{{ $btn['secondary'] }}">Keep Promo</button>
                <form id="promoDeleteForm" method="POST" class="sm:w-auto">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full {{ $btn['remove'] }} sm:w-auto">Yes, Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif


<script>
// One IIFE, and no functions attached to window: every trigger in the markup is
// a data-* attribute handled by delegation, following booking.blade.php.
(function () {
    'use strict';

    const ROUTE_STORE  = @json(route('promos.store'));
    const ROUTE_UPDATE = @json($promoUpdateUrl);
    const ROUTE_DELETE = @json(route('promos.destroy', '__ID__'));

    function routeFor(template, id) { return template.replace('__ID__', encodeURIComponent(id)); }

    // ── Shared modal behaviour (ported from appointments.blade.php) ──────
    const MODAL_IDS = ['promoModal', 'promoDeleteModal'];
    const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    let lastFocused = null;

    function topmostOpenModal() {
        for (let i = MODAL_IDS.length - 1; i >= 0; i--) {
            const el = document.getElementById(MODAL_IDS[i]);
            if (el && !el.classList.contains('hidden')) return el;
        }
        return null;
    }

    function openModal(id, focusSelector) {
        const el = document.getElementById(id);
        if (!el) return;
        lastFocused = document.activeElement;
        el.classList.remove('hidden');
        const target = (focusSelector && el.querySelector(focusSelector)) || el.querySelector(FOCUSABLE);
        if (target) target.focus();
    }

    function closeModal(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('hidden');
        if (lastFocused && document.contains(lastFocused)) lastFocused.focus();
        lastFocused = null;
    }

    document.addEventListener('keydown', function (e) {
        const modal = topmostOpenModal();
        if (!modal) return;

        if (e.key === 'Escape') {
            e.preventDefault();
            closeModal(modal.id);
            return;
        }
        if (e.key !== 'Tab') return;

        const items = Array.from(modal.querySelectorAll(FOCUSABLE)).filter(el => el.offsetParent !== null);
        if (!items.length) return;
        const first = items[0];
        const last  = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault(); last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault(); first.focus();
        }
    });

    // Backdrop click closes the delete confirm only. The promo form holds eight
    // fields plus two checkbox lists; a stray tap would bin all of it.
    ['promoDeleteModal'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('click', e => { if (e.target === el) closeModal(id); });
    });

    // ── Promo form ───────────────────────────────────────────────────────
    const form = document.getElementById('promoForm');

    function setPromoError(message) {
        const el = document.getElementById('promoError');
        if (!el) return;
        if (!message) { el.classList.add('hidden'); el.textContent = ''; return; }
        el.textContent = message;
        el.classList.remove('hidden');
        el.scrollIntoView({ block: 'nearest' });
    }

    function checkAll(selector, ids) {
        const wanted = new Set((ids || []).map(String));
        let matched = 0;
        document.querySelectorAll(selector).forEach(cb => {
            if (wanted.has(String(cb.value))) { cb.checked = true; matched++; }
        });
        return wanted.size - matched;
    }

    function openPromoModal(promo) {
        if (!form) return;

        const title      = document.getElementById('promoModalTitle');
        const method     = document.getElementById('promoFormMethod');
        const activeWrap = document.getElementById('promoActiveWrapper');
        const orphan     = document.getElementById('promoOrphanNotice');

        form.reset();
        // reset() restores each checkbox to its markup default. The treatment and
        // package boxes have no `checked` attribute so they clear; promoIsActive
        // does, so it returns to ticked — which is what a new promo should be.
        document.querySelectorAll('.promo-treatment-checkbox, .promo-package-checkbox')
            .forEach(cb => { cb.checked = false; });

        setPromoError(null);
        orphan.classList.add('hidden');
        orphan.textContent = '';

        if (promo) {
            title.textContent = 'Edit Promo';
            form.action = routeFor(ROUTE_UPDATE, promo.id);
            method.value = 'PUT';

            document.getElementById('promoName').value          = promo.name ?? '';
            document.getElementById('promoDiscountType').value  = promo.discount_type ?? 'percent';
            document.getElementById('promoDiscountValue').value = promo.discount_value ?? '';
            document.getElementById('promoStartDate').value     = promo.start_date ?? '';
            document.getElementById('promoEndDate').value       = promo.end_date ?? '';
            document.getElementById('promoIsActive').checked    = !!promo.is_active;
            activeWrap.classList.remove('hidden');

            const missing = checkAll('.promo-treatment-checkbox', promo.treatment_ids)
                          + checkAll('.promo-package-checkbox',   promo.package_ids);

            if (missing > 0) {
                orphan.textContent = missing + ' linked item' + (missing === 1 ? ' is' : 's are')
                    + ' not in the lists below — it may have been deleted or belong to another branch.'
                    + ' Saving now will unlink it.';
                orphan.classList.remove('hidden');
            }
        } else {
            title.textContent = 'New Promo';
            form.action = ROUTE_STORE;
            method.value = 'POST';
            activeWrap.classList.add('hidden');
        }

        syncEndDateFloor();
        openModal('promoModal', '#promoName');
    }

    // The browser blocks an end date before the start date at the picker level;
    // the submit check below still runs for typed input and older browsers.
    const startInput = document.getElementById('promoStartDate');
    const endInput   = document.getElementById('promoEndDate');

    function syncEndDateFloor() {
        if (!startInput || !endInput) return;
        endInput.min = startInput.value || '';
    }
    if (startInput) startInput.addEventListener('change', syncEndDateFloor);

    if (form) {
        form.addEventListener('submit', function (e) {
            const treatments = document.querySelectorAll('.promo-treatment-checkbox:checked').length;
            const packages   = document.querySelectorAll('.promo-package-checkbox:checked').length;

            if (treatments === 0 && packages === 0) {
                e.preventDefault();
                setPromoError('Select at least one treatment or package.');
                return;
            }

            const start = startInput?.value;
            const end   = endInput?.value;
            if (start && end && end < start) {
                e.preventDefault();
                setPromoError('The end date can’t be earlier than the start date.');
                return;
            }

            setPromoError(null);
        });
    }

    // ── Delegated triggers ───────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        const openBtn = e.target.closest('[data-promo-open]');
        if (openBtn) { openPromoModal(null); return; }

        const editBtn = e.target.closest('[data-promo-edit]');
        if (editBtn) {
            let payload = null;
            try {
                payload = JSON.parse(editBtn.getAttribute('data-promo-edit'));
            } catch (err) {
                setPromoError('Could not load this promo. Refresh the page and try again.');
            }
            if (payload) openPromoModal(payload);
            return;
        }

        if (e.target.closest('[data-promo-close]')) { closeModal('promoModal'); return; }

        const delBtn = e.target.closest('[data-promo-delete]');
        if (delBtn) {
            const id   = delBtn.getAttribute('data-promo-delete');
            const name = delBtn.getAttribute('data-promo-name') || 'this promo';
            document.getElementById('promoDeleteForm').action = routeFor(ROUTE_DELETE, id);
            document.getElementById('promoDeleteName').textContent = '“' + name + '”';
            openModal('promoDeleteModal');
            return;
        }

        if (e.target.closest('[data-promo-delete-close]')) { closeModal('promoDeleteModal'); }
    });
})();
</script>

@endsection