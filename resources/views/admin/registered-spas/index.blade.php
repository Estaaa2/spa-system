@extends('layouts.app')

@section('title', 'Registered Spas')
@section('content')
@php
    // Button tokens, same shape as appointments.blade.php's $btn map.
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'edit'    => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        'neutral' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
        'approve' => $btnBase . ' bg-emerald-700 text-white hover:bg-emerald-800',
        'remove'  => $btnBase . ' bg-red-700 text-white hover:bg-red-800',
    ];

    $inputClass = 'block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 '
                . 'dark:placeholder-gray-400 dark:text-white';

    $tabBase     = 'flex items-center justify-center flex-1 gap-2 min-h-[44px] px-3 text-sm font-medium whitespace-nowrap transition rounded-xl';
    $tabActive   = $tabBase . ' text-white shadow-sm bg-gradient-to-r from-[#7A6348] to-[#6F5430]';
    $tabInactive = $tabBase . ' text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700';

    // Same verification tones as owner/spa-profile/edit.blade.php.
    $statusClasses = [
        'verified'   => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'pending'    => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'rejected'   => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        'unverified' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
    ];

    $tierClasses = [
        'professional' => 'bg-[#8B7355]/10 text-[#6F5430] dark:bg-[#C4A97D]/10 dark:text-[#C4A97D]',
        'basic'        => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    ];

    $thClass    = 'px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400';
    $badgeClass = 'px-2.5 py-1 text-xs font-medium rounded-full';
    $labelClass = 'block mb-2 text-sm font-medium text-gray-900 dark:text-white';

    $canEdit = auth()->user()->can('edit registered spas');
    $tabs    = ['' => 'All'] + array_combine($statuses, array_map('ucfirst', $statuses));
@endphp
<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">
    <x-page-header
        title="Registered Spas"
        subtitle="Review verification documents and manage spa tiers."
    />

    <nav aria-label="Verification status"
         class="flex gap-1 p-1.5 overflow-x-auto bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        @foreach ($tabs as $value => $label)
            @php $isCurrent = $value === (string) $status; @endphp
            <a href="{{ route('admin.registered-spas.index', array_filter(['status' => $value, 'q' => $q])) }}"
               @if ($isCurrent) aria-current="page" @endif
               class="{{ $isCurrent ? $tabActive : $tabInactive }}">
                {{ $label }}
                <span class="text-xs opacity-75">{{ $value === '' ? $counts->sum() : ($counts[$value] ?? 0) }}</span>
            </a>
        @endforeach
    </nav>

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-col gap-3 px-4 py-4 border-b border-gray-200 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ $tabs[(string) $status] }} Spas</h2>

            <form method="GET" class="flex gap-2">
                @if ($status)
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif
                <input type="search" name="q" value="{{ $q }}" aria-label="Search spa name or owner"
                       placeholder="Search spa name or owner" class="{{ $inputClass }} sm:w-64">
                <button type="submit" class="{{ $btn['primary'] }}">Search</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="{{ $thClass }}">Spa</th>
                        <th class="{{ $thClass }}">Owner</th>
                        <th class="{{ $thClass }}">Tier</th>
                        <th class="{{ $thClass }}">Verification</th>
                        <th class="{{ $thClass }}">Registered</th>
                        @if ($canEdit)
                            <th class="{{ $thClass }}">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    @forelse ($spas as $spa)
                        <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ $spa->name }}</td>
                            <td class="px-6 py-4">
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $spa->owner?->name ?: '—' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $spa->owner?->email }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="{{ $badgeClass }} {{ $tierClasses[$spa->business_tier] ?? $tierClasses['basic'] }}">
                                    {{ ucfirst($spa->business_tier) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="{{ $badgeClass }} {{ $statusClasses[$spa->verification_status] ?? $statusClasses['unverified'] }}">
                                    {{ ucfirst($spa->verification_status) }}
                                </span>
                                @if ($spa->verified_at)
                                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ $spa->verified_at->format('M d, Y') }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $spa->created_at->format('M d, Y') }}</td>
                            @if ($canEdit)
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" onclick="openSpaModal({{ $spa->id }})"
                                                class="{{ $spa->verification_status === 'pending' ? $btn['primary'] : $btn['edit'] }}">
                                            <i class="text-xs fa-solid fa-file-circle-check" aria-hidden="true"></i>
                                            <span>Review</span>
                                        </button>
                                        <button type="button" class="{{ $btn['remove'] }}"
                                                onclick='openDeleteModal({{ $spa->id }}, @json($spa->name))'>
                                            <i class="text-xs fa-solid fa-trash" aria-hidden="true"></i>
                                            <span>Remove</span>
                                        </button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <i class="mb-3 text-4xl text-gray-400 fa-solid fa-spa" aria-hidden="true"></i>
                                <p class="text-sm text-gray-500 dark:text-gray-400">No spas found.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($spas->hasPages())
            <div class="px-4 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
                {{ $spas->links() }}
            </div>
        @endif
    </div>
</div>

@if ($canEdit)
<div id="spaModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="spaModalTitle"
             class="w-full max-w-2xl bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div>
                    <h2 id="spaModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Review Spa</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Check the business details and documents, then approve or reject.</p>
                </div>
                <button type="button" onclick="closeModal('spaModal')" aria-label="Close dialog"
                        class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <p id="spaModalStatus" role="status" class="px-6 py-12 text-sm text-center text-gray-500 dark:text-gray-400"></p>

            <form id="spaReviewForm" method="POST" class="hidden">
                @csrf
                @method('PUT')

                <div class="px-4 py-6 space-y-6 sm:px-6">
                    <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                        @foreach (['SpaName' => 'Spa name', 'CurrentStatus' => 'Current status', 'OwnerName' => 'Owner', 'OwnerEmail' => 'Owner email', 'VerifiedBy' => 'Verified by', 'VerifiedAt' => 'Verified on'] as $field => $label)
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                <dd id="modal{{ $field }}" class="font-medium text-gray-900 break-words dark:text-white"></dd>
                            </div>
                        @endforeach
                    </dl>

                    <div>
                        <h3 class="{{ $labelClass }}">Verification documents</h3>
                        <div id="documentsContainer" class="space-y-2"></div>
                    </div>

                    <div>
                        <label for="modalTier" class="{{ $labelClass }}">Business tier</label>
                        <select name="business_tier" id="modalTier" class="{{ $inputClass }}">
                            <option value="basic">Basic</option>
                            <option value="professional">Professional</option>
                        </select>
                        <p class="mt-2 text-xs text-amber-700 dark:text-amber-400">
                            Tiers normally follow the spa's subscription. Change this only to correct a mistake.
                        </p>
                    </div>

                    <div id="rejectionReasonWrapper" class="hidden">
                        <label for="modalVerificationRemarks" class="{{ $labelClass }}">
                            Reason for rejection <span class="text-red-500">*</span>
                        </label>
                        <textarea name="verification_remarks" id="modalVerificationRemarks" rows="4"
                                  class="{{ $inputClass }}" placeholder="Explain what the owner needs to fix."></textarea>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">The owner sees this on their Spa Profile page.</p>
                    </div>
                </div>

                <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                    <div id="reviewActions" class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeModal('spaModal')" class="w-full {{ $btn['neutral'] }} sm:w-auto">Cancel</button>
                        <button type="button" onclick="setRejectMode(true)" class="w-full {{ $btn['remove'] }} sm:w-auto">Reject</button>
                        <button type="submit" name="verification_status" value="verified" class="w-full {{ $btn['approve'] }} sm:w-auto">Approve</button>
                    </div>
                    <div id="rejectActions" class="flex flex-col-reverse hidden gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onclick="setRejectMode(false)" class="w-full {{ $btn['neutral'] }} sm:w-auto">Back</button>
                        <button type="submit" name="verification_status" value="rejected" class="w-full {{ $btn['remove'] }} sm:w-auto">Confirm Rejection</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Cloned per document and filled with textContent, so file names are never parsed as HTML. --}}
<template id="documentRowTemplate">
    <div class="flex flex-col gap-2 p-4 border border-gray-200 rounded-xl sm:flex-row sm:items-center sm:justify-between dark:border-gray-700">
        <div class="min-w-0">
            <p data-doc="label" class="text-sm font-medium text-gray-900 dark:text-white"></p>
            <p data-doc="file" class="text-sm text-gray-500 truncate dark:text-gray-400"></p>
            <p data-doc="date" class="text-xs text-gray-500 dark:text-gray-400"></p>
        </div>
        <a data-doc="link" target="_blank" rel="noopener" class="shrink-0 {{ $btn['edit'] }}">
            <i class="text-xs fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
            <span>View</span>
        </a>
    </div>
</template>

<div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="alertdialog" aria-modal="true" aria-labelledby="deleteModalTitle" aria-describedby="deleteModalDesc"
             class="w-full max-w-md p-6 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <h2 id="deleteModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Remove Spa</h2>
            <p id="deleteModalDesc" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                This removes <span id="deleteSpaName" class="font-medium text-gray-900 dark:text-white"></span>
                from the platform. The owner's user account is not deleted.
            </p>
            <div class="flex flex-col-reverse gap-2 mt-6 sm:flex-row sm:justify-end">
                <button type="button" onclick="closeModal('deleteModal')" class="w-full {{ $btn['neutral'] }} sm:w-auto">Keep Spa</button>
                <form id="deleteForm" method="POST" class="sm:w-auto">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full {{ $btn['remove'] }} sm:w-auto">Yes, Remove</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const ROUTE_EDIT = @json(route('admin.registered-spas.edit', '__ID__'));
    const ROUTE_SPA  = @json(route('admin.registered-spas.update', '__ID__'));   // PUT updates, DELETE removes
    const DOC_LABELS = {
        government_id:   'Government ID',
        dti_sec:         'DTI / SEC Certificate',
        bir_certificate: 'BIR Certificate of Registration',
    };
    const MODAL_IDS = ['spaModal', 'deleteModal'];
    const FOCUSABLE = 'a[href], button:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    const el = id => document.getElementById(id);
    let lastFocused = null;   // element to restore focus to on close

    function openModal(id, focusSelector) {
        lastFocused = document.activeElement;
        el(id).classList.remove('hidden');
        el(id).querySelector(focusSelector).focus();
    }

    function closeModal(id) {
        el(id).classList.add('hidden');
        if (lastFocused && document.contains(lastFocused)) lastFocused.focus();
        lastFocused = null;
    }

    // Escape closes the open modal; Tab is kept inside it.
    document.addEventListener('keydown', function (e) {
        const modal = MODAL_IDS.map(el).find(m => !m.classList.contains('hidden'));
        if (!modal) return;

        if (e.key === 'Escape') {
            e.preventDefault();
            closeModal(modal.id);
            return;
        }
        if (e.key !== 'Tab') return;

        // offsetParent is null for controls inside a hidden block (the unused action row).
        const items = Array.from(modal.querySelectorAll(FOCUSABLE)).filter(i => i.offsetParent !== null);
        const first = items[0];
        const last  = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault(); last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault(); first.focus();
        }
    });

    // Backdrop click closes the delete confirmation only, so a stray tap
    // cannot discard a rejection reason the admin is still writing.
    el('deleteModal').addEventListener('click', e => { if (e.target === el('deleteModal')) closeModal('deleteModal'); });

    // Swaps Approve/Reject for Back/Confirm and makes the reason mandatory.
    function setRejectMode(on) {
        const remarks = el('modalVerificationRemarks');
        el('rejectionReasonWrapper').classList.toggle('hidden', !on);
        el('reviewActions').classList.toggle('hidden', on);
        el('rejectActions').classList.toggle('hidden', !on);
        remarks.required = on;
        if (on) remarks.focus();
    }

    function renderDocuments(documents) {
        const container = el('documentsContainer');
        container.replaceChildren();

        if (!documents.length) {
            const empty = document.createElement('p');
            empty.className = 'text-sm text-gray-500 dark:text-gray-400';
            empty.textContent = 'No verification documents uploaded yet.';
            container.append(empty);
            return;
        }

        documents.forEach(doc => {
            const row = el('documentRowTemplate').content.cloneNode(true);
            row.querySelector('[data-doc="label"]').textContent = DOC_LABELS[doc.document_type] ?? doc.document_type;
            row.querySelector('[data-doc="file"]').textContent  = doc.file_name ?? 'Unnamed file';
            row.querySelector('[data-doc="date"]').textContent  = 'Uploaded ' + doc.uploaded_at;
            row.querySelector('[data-doc="link"]').href         = doc.file_url;
            container.append(row);
        });
    }

    window.closeModal    = closeModal;
    window.setRejectMode = setRejectMode;

    window.openSpaModal = function (id) {
        const status = el('spaModalStatus');
        const form   = el('spaReviewForm');

        status.textContent = 'Loading spa details…';
        status.classList.remove('hidden');
        form.classList.add('hidden');
        setRejectMode(false);
        openModal('spaModal', 'button');

        fetch(ROUTE_EDIT.replace('__ID__', id), { headers: { 'Accept': 'application/json' } })
            .then(response => {
                if (!response.ok) throw new Error('Request failed');
                return response.json();
            })
            .then(({ spa }) => {
                const fields = {
                    modalSpaName:       spa.name,
                    modalCurrentStatus: spa.verification_status.charAt(0).toUpperCase() + spa.verification_status.slice(1),
                    modalOwnerName:     spa.owner_name,
                    modalOwnerEmail:    spa.owner_email,
                    modalVerifiedBy:    spa.verified_by ?? '—',
                    modalVerifiedAt:    spa.verified_at ?? '—',
                };
                Object.entries(fields).forEach(([fieldId, value]) => { el(fieldId).textContent = value; });

                el('modalTier').value = spa.business_tier;
                el('modalVerificationRemarks').value = spa.verification_remarks ?? '';
                form.action = ROUTE_SPA.replace('__ID__', spa.id);
                renderDocuments(spa.documents);

                status.classList.add('hidden');
                form.classList.remove('hidden');
            })
            .catch(() => {
                status.textContent = 'Could not load this spa. Close the dialog and try again.';
            });
    };

    window.openDeleteModal = function (id, name) {
        el('deleteSpaName').textContent = name;
        el('deleteForm').action = ROUTE_SPA.replace('__ID__', id);
        openModal('deleteModal', 'button[type="button"]');
    };
}());
</script>
@endif
@endsection