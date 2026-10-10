@extends('layouts.app')

@section('title', 'Registered Spas')

@section('content')

@php
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'edit' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        'neutral' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
        'approve' => $btnBase . ' bg-emerald-700 text-white hover:bg-emerald-800',
        'remove' => $btnBase . ' bg-red-700 text-white hover:bg-red-800',
    ];

    $inputClass = 'block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 '
                . 'dark:placeholder-gray-400 dark:text-white';

    $tabBase = 'flex items-center justify-center flex-1 gap-2 min-h-[44px] px-3 text-sm font-medium whitespace-nowrap transition rounded-xl';
    $tabActive = $tabBase . ' text-white shadow-sm bg-gradient-to-r from-[#7A6348] to-[#6F5430]';
    $tabInactive = $tabBase . ' text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700';

    $statusClasses = [
        'verified' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        'unverified' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
    ];

    $tierClasses = [
        'basic' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        'premium' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
        'business' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',

        // Legacy database value. It is displayed as Premium below.
        'professional' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    ];

    $thClass = 'px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400';
    $badgeClass = 'px-2.5 py-1 text-xs font-medium rounded-full';
    $labelClass = 'block mb-2 text-sm font-medium text-gray-900 dark:text-white';

    $canEdit = auth()->user()->can('edit registered spas');

    $tabs = ['' => 'All']
        + array_combine(
            $statuses,
            array_map(
                'ucfirst',
                $statuses
            )
        );
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">
    <x-page-header
        title="Registered Spas"
        subtitle="Review verification documents and manage verification status."
    />

    <nav aria-label="Verification status"
        class="flex gap-1 p-1.5 overflow-x-auto bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">

        @foreach ($tabs as $value => $label)
            @php
                $isCurrent =
                    $value ===
                    (string) $status;
            @endphp

            <a href="{{ route('admin.registered-spas.index', array_filter([
                    'status' => $value,
                    'q' => $q,
                ])) }}"
                @if ($isCurrent)
                    aria-current="page"
                @endif
                class="{{ $isCurrent ? $tabActive : $tabInactive }}">

                {{ $label }}

                <span class="text-xs opacity-75">
                    {{ $value === ''
                        ? $counts->sum()
                        : ($counts[$value] ?? 0)
                    }}
                </span>
            </a>
        @endforeach
    </nav>

    @if ($branchApplications->isNotEmpty())
        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    Branch Applications
                </h2>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Additional branches waiting for document review.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="{{ $thClass }}">Branch</th>
                            <th class="{{ $thClass }}">Spa</th>
                            <th class="{{ $thClass }}">Owner</th>
                            <th class="{{ $thClass }}">Submitted</th>

                            @if ($canEdit)
                                <th class="{{ $thClass }}">Actions</th>
                            @endif
                        </tr>
                    </thead>

                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        @foreach ($branchApplications as $application)
                            <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td class="px-6 py-4">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $application->name }}
                                    </p>

                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $application->location }}
                                    </p>
                                </td>

                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                    {{ $application->spa?->name ?: '—' }}
                                </td>

                                <td class="px-6 py-4">
                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                        {{ $application->spa?->owner?->name ?: '—' }}
                                    </p>

                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $application->spa?->owner?->email }}
                                    </p>
                                </td>

                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $application->updated_at?->format('M d, Y') }}
                                </td>

                                @if ($canEdit)
                                    <td class="px-6 py-4">
                                        <button type="button"
                                            onclick="openSpaModal({{ $application->id }}, 'branch')"
                                            class="{{ $btn['primary'] }}">

                                            <i class="text-xs fa-solid fa-file-circle-check"
                                                aria-hidden="true"></i>

                                            Review
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($branchApplications->hasPages())
                <div class="px-4 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
                    {{ $branchApplications->links() }}
                </div>
            @endif
        </div>
    @endif

    @if ($documentReviews->isNotEmpty())
        <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    Document Reviews
                </h2>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Renewed or newly uploaded documents of spas and branches that are already verified.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="{{ $thClass }}">Document</th>
                            <th class="{{ $thClass }}">Spa</th>
                            <th class="{{ $thClass }}">Owner</th>
                            <th class="{{ $thClass }}">Date in effect</th>
                            <th class="{{ $thClass }}">Submitted</th>

                            @if ($canEdit)
                                <th class="{{ $thClass }}">Actions</th>
                            @endif
                        </tr>
                    </thead>

                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        @foreach ($documentReviews as $review)
                            @php
                                $reviewSpa = $documentReviewSpas->get($review->spa_id);
                            @endphp

                            <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td class="px-6 py-4">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $documentLabels[$review->document_type] ?? $review->document_type }}
                                    </p>

                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $review->branch?->name ?: 'All branches' }}
                                    </p>
                                </td>

                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                    {{ $reviewSpa?->name ?: '—' }}
                                </td>

                                <td class="px-6 py-4">
                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                        {{ $reviewSpa?->owner?->name ?: '—' }}
                                    </p>

                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $reviewSpa?->owner?->email }}
                                    </p>
                                </td>

                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $review->expiry_date?->format('M d, Y') ?: 'None yet' }}
                                </td>

                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $review->updated_at?->format('M d, Y') }}
                                </td>

                                @if ($canEdit)
                                    <td class="px-6 py-4">
                                        <button type="button"
                                            onclick="openSpaModal({{ $review->id }}, 'document')"
                                            class="{{ $btn['primary'] }}">

                                            <i class="text-xs fa-solid fa-file-circle-check"
                                                aria-hidden="true"></i>

                                            Review
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($documentReviews->hasPages())
                <div class="px-4 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
                    {{ $documentReviews->links() }}
                </div>
            @endif
        </div>
    @endif

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-col gap-3 px-4 py-4 border-b border-gray-200 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                {{ $tabs[(string) $status] }} Spas
            </h2>

            <form method="GET"
                class="flex gap-2">

                @if ($status)
                    <input type="hidden"
                        name="status"
                        value="{{ $status }}">
                @endif

                <input type="search"
                    name="q"
                    value="{{ $q }}"
                    aria-label="Search spa name or owner"
                    placeholder="Search spa or owner"
                    class="{{ $inputClass }} sm:w-64">

                <button type="submit"
                    class="{{ $btn['primary'] }}">
                    Search
                </button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="{{ $thClass }}">Spa</th>
                        <th class="{{ $thClass }}">Owner</th>
                        <th class="{{ $thClass }}">Plan</th>
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
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                {{ $spa->name }}
                            </td>

                            <td class="px-6 py-4">
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ $spa->owner?->name ?: '—' }}
                                </p>

                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $spa->owner?->email }}
                                </p>
                            </td>

                            @php
                                $plan = strtolower((string) ($spa->business_tier ?: 'basic'));

                                if ($plan === 'professional') {
                                    $plan = 'premium';
                                }

                                if (! in_array($plan, ['basic', 'premium', 'business'], true)) {
                                    $plan = 'basic';
                                }
                            @endphp

                            <td class="px-6 py-4">
                                <span class="{{ $badgeClass }} {{ $tierClasses[$plan] }}">
                                    {{ ucfirst($plan) }}
                                </span>
                            </td>

                            <td class="px-6 py-4">
                                <span class="{{ $badgeClass }} {{ $statusClasses[$spa->verification_status] ?? $statusClasses['unverified'] }}">
                                    {{ ucfirst($spa->verification_status) }}
                                </span>
                            </td>

                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $spa->created_at->format('M d, Y') }}
                            </td>

                            @if ($canEdit)
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button"
                                            onclick="openSpaModal({{ $spa->id }})"
                                            class="{{ $spa->verification_status === 'pending'
                                                ? $btn['primary']
                                                : $btn['edit']
                                            }}">

                                            <i class="text-xs fa-solid fa-file-circle-check"
                                                aria-hidden="true"></i>

                                            Review
                                        </button>

                                        <button type="button"
                                            onclick='openDeleteModal({{ $spa->id }}, @json($spa->name))'
                                            class="{{ $btn['remove'] }}">

                                            <i class="text-xs fa-solid fa-trash"
                                                aria-hidden="true"></i>

                                            Remove
                                        </button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canEdit ? 6 : 5 }}"
                            class="px-6 py-12 text-sm text-center text-gray-500 dark:text-gray-400">
                                No spas found.
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

<div id="spaModal"
    class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">

    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog"
            aria-modal="true"
            aria-labelledby="spaModalTitle"
            class="w-full max-w-3xl bg-white shadow-xl rounded-2xl dark:bg-gray-800">

            <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                <div>
                    <h2 id="spaModalTitle"
                        class="text-lg font-semibold text-gray-900 dark:text-white">
                        Review Spa
                    </h2>

                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Review the uploaded document and confirm its expiry information before approval.
                    </p>
                </div>

                <button type="button"
                    onclick="closeModal('spaModal')"
                    aria-label="Close"
                    class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">

                    <i class="fa-solid fa-xmark"
                        aria-hidden="true"></i>
                </button>
            </div>

            <p id="spaModalStatus"
                role="status"
                class="px-6 py-12 text-sm text-center text-gray-500 dark:text-gray-400">
            </p>

            <form id="spaReviewForm"
                method="POST"
                class="hidden">

                @csrf
                @method('PUT')

                <div class="px-4 py-6 space-y-6 sm:px-6">
                    <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                        @foreach ([
                            'SpaName' => 'Spa name',
                            'CurrentTier' => 'Business tier',
                            'CurrentStatus' => 'Current status',
                            'OwnerName' => 'Owner',
                            'OwnerEmail' => 'Owner email',
                            'VerifiedBy' => 'Verified by',
                            'VerifiedAt' => 'Verified on',
                        ] as $field => $label)

                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">
                                    {{ $label }}
                                </dt>

                                <dd id="modal{{ $field }}"
                                    class="font-medium text-gray-900 break-words dark:text-white">
                                </dd>
                            </div>
                        @endforeach
                    </dl>

                    <div>
                        <h3 class="{{ $labelClass }}">
                            Verification Documents
                        </h3>

                        <div id="documentsContainer"
                            class="space-y-3">
                        </div>
                    </div>

                    <div id="rejectionReasonWrapper"
                        class="hidden">

                        <label for="modalVerificationRemarks"
                            class="{{ $labelClass }}">

                            Reason for rejection
                            <span class="text-red-500">*</span>
                        </label>

                        <textarea name="verification_remarks"
                            id="modalVerificationRemarks"
                            rows="4"
                            class="{{ $inputClass }}"
                            placeholder="Explain what needs to be corrected."></textarea>
                    </div>
                </div>

                <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                    <div id="reviewActions"
                        class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">

                        <button type="button"
                            onclick="closeModal('spaModal')"
                            class="w-full {{ $btn['neutral'] }} sm:w-auto">
                            Cancel
                        </button>

                        <button type="button"
                            onclick="setRejectMode(true)"
                            class="w-full {{ $btn['remove'] }} sm:w-auto">
                            Reject
                        </button>

                        <button type="submit"
                            name="verification_status"
                            value="verified"
                            class="w-full {{ $btn['approve'] }} sm:w-auto">
                            Approve
                        </button>
                    </div>

                    <div id="rejectActions"
                        class="flex flex-col-reverse hidden gap-2 sm:flex-row sm:justify-end">

                        <button type="button"
                            onclick="setRejectMode(false)"
                            class="w-full {{ $btn['neutral'] }} sm:w-auto">
                            Back
                        </button>

                        <button type="submit"
                            name="verification_status"
                            value="rejected"
                            class="w-full {{ $btn['remove'] }} sm:w-auto">
                            Confirm Rejection
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<template id="documentRowTemplate">
    <div class="p-4 space-y-4 border border-gray-200 rounded-2xl dark:border-gray-700">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <p data-doc="label"
                        class="text-sm font-semibold text-gray-900 dark:text-white">
                    </p>

                    <span data-doc="status"
                        class="inline-flex px-2.5 py-1 text-xs font-medium rounded-full">
                    </span>
                </div>

                <p data-doc="file"
                    class="mt-1 text-sm text-gray-500 break-words dark:text-gray-400">
                </p>

                <p data-doc="date"
                    class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                </p>
            </div>

            <a data-doc="link"
                target="_blank"
                rel="noopener"
                class="{{ $btn['edit'] }} shrink-0">

                <i class="text-xs fa-solid fa-arrow-up-right-from-square"
                    aria-hidden="true"></i>

                View Document
            </a>
        </div>

        <div data-doc="expiry-section"
            class="grid grid-cols-1 gap-4 pt-4 border-t border-gray-200 sm:grid-cols-2 dark:border-gray-700">

            <div>
                <p class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">
                    Expiration Check
                </p>

                <div class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-gray-500 dark:text-gray-400">
                            Owner declared
                        </span>

                        <span data-doc="owner-expiry"
                            class="font-medium text-right text-gray-900 dark:text-white">
                        </span>
                    </div>

                    <div class="flex justify-between gap-4">
                        <span class="text-gray-500 dark:text-gray-400">
                            OCR result
                        </span>

                        <span data-doc="ocr-expiry"
                            class="font-medium text-right text-gray-900 dark:text-white">
                        </span>
                    </div>
                </div>

                <div data-doc="mismatch"
                    class="hidden p-2.5 mt-3 text-xs text-amber-800 border border-amber-200 bg-amber-50 rounded-xl dark:border-amber-800 dark:bg-amber-900/10 dark:text-amber-300">

                    Dates do not match. Check the document.
                </div>

                <div data-doc="unreadable"
                    class="hidden p-2.5 mt-3 text-xs text-red-700 border border-red-200 bg-red-50 rounded-xl dark:border-red-800 dark:bg-red-900/10 dark:text-red-300">

                    OCR could not read this document clearly. Review the uploaded file.
                </div>

                <p data-doc="source"
                    class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                </p>
            </div>

            <div data-doc="expiry-input-wrapper">
                <label data-doc="expiry-label"
                    class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                </label>

                <input data-doc="expiry-input"
                    type="date"
                    min="{{ now()->format('Y-m-d') }}"
                    class="{{ $inputClass }}">

                <p data-doc="expiry-help"
                    class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                </p>
            </div>
        </div>

        <div data-doc="no-expiry-section"
            class="hidden pt-4 text-sm text-gray-600 border-t border-gray-200 dark:border-gray-700 dark:text-gray-300">

            No expiration date required.
        </div>

        <div data-doc="reviewed-wrapper"
            class="hidden pt-3 border-t border-gray-200 dark:border-gray-700">

            <p data-doc="reviewed"
                class="text-xs text-emerald-700 dark:text-emerald-400">
            </p>
        </div>
    </div>
</template>

<div id="deleteModal"
    class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">

    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="alertdialog"
            aria-modal="true"
            aria-labelledby="deleteModalTitle"
            class="w-full max-w-md p-6 bg-white shadow-xl rounded-2xl dark:bg-gray-800">

            <h2 id="deleteModalTitle"
                class="text-lg font-semibold text-gray-900 dark:text-white">
                Remove Spa
            </h2>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Remove
                <span id="deleteSpaName"
                    class="font-medium text-gray-900 dark:text-white">
                </span>
                from the platform?
            </p>

            <div class="flex flex-col-reverse gap-2 mt-6 sm:flex-row sm:justify-end">
                <button type="button"
                    onclick="closeModal('deleteModal')"
                    class="w-full {{ $btn['neutral'] }} sm:w-auto">
                    Cancel
                </button>

                <form id="deleteForm"
                    method="POST">

                    @csrf
                    @method('DELETE')

                    <button type="submit"
                        class="w-full {{ $btn['remove'] }} sm:w-auto">
                        Remove
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const ROUTE_EDIT =
        @json(route(
            'admin.registered-spas.edit',
            '__ID__'
        ));

    const ROUTE_SPA =
        @json(route(
            'admin.registered-spas.update',
            '__ID__'
        ));

    const ROUTE_BRANCH_EDIT =
        @json(route(
            'admin.branch-verifications.edit',
            '__ID__'
        ));

    const ROUTE_BRANCH =
        @json(route(
            'admin.branch-verifications.update',
            '__ID__'
        ));

    const ROUTE_DOCUMENT_EDIT =
        @json(route(
            'admin.document-reviews.edit',
            '__ID__'
        ));

    const ROUTE_DOCUMENT =
        @json(route(
            'admin.document-reviews.update',
            '__ID__'
        ));

    // Which routes and title the review dialog uses for each kind.
    const REVIEW_KINDS = {
        spa: {
            edit: ROUTE_EDIT,
            update: ROUTE_SPA,
            title: 'Review Spa',
        },
        branch: {
            edit: ROUTE_BRANCH_EDIT,
            update: ROUTE_BRANCH,
            title: 'Review Branch',
        },
        document: {
            edit: ROUTE_DOCUMENT_EDIT,
            update: ROUTE_DOCUMENT,
            title: 'Review Document',
        },
    };

    const DOC_LABELS = {
        government_id:
            'Government ID',

        dti_sec:
            'DTI / SEC Certificate',

        bir_certificate:
            'BIR Certificate of Registration',

        business_permit:
            'Business Permit',
    };

    const DOC_RULES = {
        government_id: {
            expiry: 'required',
            label:
                'Confirm Expiration Date',
            help:
                'Confirm the expiration date shown on the Government ID.',
        },

        dti_sec: {
            expiry: 'optional',
            label:
                'Confirm Expiration Date',
            help:
                'Leave blank if no expiration date applies.',
        },

        bir_certificate: {
            expiry: 'none',
            label: '',
            help: '',
        },

        business_permit: {
            expiry: 'required',
            label:
                'Confirm Expiration Date',
            help:
                'Required before approval.',
        },
    };

    const STATUS_MAP = {
        verified: {
            label: 'Reviewed',
            classes:
                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        },

        verified_no_expiry: {
            label: 'Reviewed',
            classes:
                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        },

        detected: {
            label: 'Detected',
            classes:
                'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        },

        needs_review: {
            label: 'Needs Review',
            classes:
                'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        },

        not_found: {
            label: 'No Expiry Found',
            classes:
                'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
        },

        not_required: {
            label: 'No Expiry Required',
            classes:
                'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
        },

        failed: {
            label: 'Scan Failed',
            classes:
                'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        },

        unreadable: {
            label: 'Unreadable',
            classes:
                'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        },

        not_scanned: {
            label: 'Not Scanned',
            classes:
                'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
        },
    };

    const SOURCE_LABELS = {
        native_pdf:
            'PDF text',

        ocr_pdf:
            'OCR — PDF',

        ocr_image:
            'OCR — image',
    };

    const MODAL_IDS = [
        'spaModal',
        'deleteModal',
    ];

    const FOCUSABLE =
        'a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    const el = id =>
        document.getElementById(id);

    let lastFocused = null;

    function openModal(
        id,
        focusSelector
    ) {
        lastFocused =
            document.activeElement;

        const modal =
            el(id);

        modal.classList.remove(
            'hidden'
        );

        const target =
            modal.querySelector(
                focusSelector
            );

        if (target) {
            target.focus();
        }
    }

    function closeModal(id) {
        el(id).classList.add(
            'hidden'
        );

        if (
            lastFocused &&
            document.contains(
                lastFocused
            )
        ) {
            lastFocused.focus();
        }

        lastFocused = null;
    }

    function setRejectMode(on) {
        const remarks =
            el(
                'modalVerificationRemarks'
            );

        el(
            'rejectionReasonWrapper'
        ).classList.toggle(
            'hidden',
            !on
        );

        el(
            'reviewActions'
        ).classList.toggle(
            'hidden',
            on
        );

        el(
            'rejectActions'
        ).classList.toggle(
            'hidden',
            !on
        );

        remarks.required =
            on;

        document
            .querySelectorAll(
                '[data-doc="expiry-input"]'
            )
            .forEach(
                input => {
                    input.required =
                        !on &&
                        input.dataset
                            .requiredForApproval ===
                            'true';
                }
            );

        if (on) {
            remarks.focus();
        }
    }

    function renderDocuments(
        documents
    ) {
        const container =
            el(
                'documentsContainer'
            );

        container.replaceChildren();

        if (!documents.length) {
            const empty =
                document.createElement(
                    'p'
                );

            empty.className =
                'text-sm text-gray-500 dark:text-gray-400';

            empty.textContent =
                'No verification documents uploaded.';

            container.append(
                empty
            );

            return;
        }

        documents.forEach(
            doc => {
                const row =
                    el(
                        'documentRowTemplate'
                    )
                        .content
                        .cloneNode(
                            true
                        );

                const rule =
                    DOC_RULES[
                        doc.document_type
                    ] ?? {
                        expiry:
                            'optional',

                        label:
                            'Confirm Expiration Date',

                        help:
                            '',
                    };

                const label =
                    row.querySelector(
                        '[data-doc="label"]'
                    );

                const file =
                    row.querySelector(
                        '[data-doc="file"]'
                    );

                const date =
                    row.querySelector(
                        '[data-doc="date"]'
                    );

                const link =
                    row.querySelector(
                        '[data-doc="link"]'
                    );

                const status =
                    row.querySelector(
                        '[data-doc="status"]'
                    );

                const ownerExpiry =
                    row.querySelector(
                        '[data-doc="owner-expiry"]'
                    );

                const ocrExpiry =
                    row.querySelector(
                        '[data-doc="ocr-expiry"]'
                    );

                const mismatch =
                    row.querySelector(
                        '[data-doc="mismatch"]'
                    );

                const unreadable =
                    row.querySelector(
                        '[data-doc="unreadable"]'
                    );

                const source =
                    row.querySelector(
                        '[data-doc="source"]'
                    );

                const expirySection =
                    row.querySelector(
                        '[data-doc="expiry-section"]'
                    );

                const noExpirySection =
                    row.querySelector(
                        '[data-doc="no-expiry-section"]'
                    );

                const expiryLabel =
                    row.querySelector(
                        '[data-doc="expiry-label"]'
                    );

                const expiryInput =
                    row.querySelector(
                        '[data-doc="expiry-input"]'
                    );

                const expiryHelp =
                    row.querySelector(
                        '[data-doc="expiry-help"]'
                    );

                const reviewedWrapper =
                    row.querySelector(
                        '[data-doc="reviewed-wrapper"]'
                    );

                const reviewed =
                    row.querySelector(
                        '[data-doc="reviewed"]'
                    );

                label.textContent =
                    DOC_LABELS[
                        doc.document_type
                    ] ??
                    doc.document_type;

                file.textContent =
                    doc.file_name ??
                    'Unnamed file';

                date.textContent =
                    doc.uploaded_at
                        ? 'Uploaded ' +
                          doc.uploaded_at
                        : '';

                link.href =
                    doc.file_url;

                let statusKey =
                    doc.expiry_detection_status ??
                    'not_scanned';

                if (
                    doc.document_type ===
                    'bir_certificate'
                ) {
                    statusKey =
                        'not_required';
                }

                const statusConfig =
                    STATUS_MAP[
                        statusKey
                    ] ??
                    STATUS_MAP
                        .not_scanned;

                status.textContent =
                    statusConfig.label;

                status.className =
                    'inline-flex px-2.5 py-1 text-xs font-medium rounded-full ' +
                    statusConfig.classes;

                if (
                    doc.document_type ===
                    'bir_certificate'
                ) {
                    expirySection
                        .classList
                        .add(
                            'hidden'
                        );

                    noExpirySection
                        .classList
                        .remove(
                            'hidden'
                        );
                } else {
                    ownerExpiry.textContent =
                        doc.owner_expiry_date_display ??
                        'Not provided';

                    if (
                        doc.expiry_detection_status ===
                        'unreadable'
                    ) {
                        ocrExpiry.textContent =
                            'Unreadable';

                        ocrExpiry.classList.remove(
                            'text-gray-900',
                            'dark:text-white'
                        );

                        ocrExpiry.classList.add(
                            'text-red-700',
                            'dark:text-red-300'
                        );

                        unreadable
                            .classList
                            .remove(
                                'hidden'
                            );
                    } else if (
                        doc.ocr_expiry_date_display
                    ) {
                        ocrExpiry.textContent =
                            doc.ocr_expiry_date_display;
                    } else if (
                        doc.expiry_detection_status ===
                        'not_scanned'
                    ) {
                        ocrExpiry.textContent =
                            'Not scanned';
                    } else if (
                        doc.expiry_detection_status ===
                        'failed'
                    ) {
                        ocrExpiry.textContent =
                            'Scan failed';

                        ocrExpiry.classList.remove(
                            'text-gray-900',
                            'dark:text-white'
                        );

                        ocrExpiry.classList.add(
                            'text-red-700',
                            'dark:text-red-300'
                        );
                    } else if (
                        doc.expiry_detection_status ===
                        'needs_review'
                    ) {
                        ocrExpiry.textContent =
                            'Needs review';
                    } else {
                        ocrExpiry.textContent =
                            'Not detected';
                    }

                    const hasMismatch =
                        doc.owner_expiry_date &&
                        doc.ocr_expiry_date &&
                        doc.owner_expiry_date !==
                            doc.ocr_expiry_date;

                    if (hasMismatch) {
                        mismatch
                            .classList
                            .remove(
                                'hidden'
                            );
                    }

                    source.textContent =
                        doc.expiry_detection_source
                            ? 'Source: ' +
                              (
                                  SOURCE_LABELS[
                                      doc.expiry_detection_source
                                  ] ??
                                  doc.expiry_detection_source
                              )
                            : 'Source: Not available';

                    if (
                        doc.expiry_scanned_at
                    ) {
                        source.textContent +=
                            ' • Scanned ' +
                            doc.expiry_scanned_at;
                    }

                    expiryInput.name =
                        'document_expiry[' +
                        doc.id +
                        ']';

                    if (doc.expiry_date) {
                        expiryInput.value =
                            doc.expiry_date;
                    } else if (doc.owner_expiry_date) {
                        expiryInput.value =
                            doc.owner_expiry_date;
                    } else if (doc.ocr_expiry_date) {
                        expiryInput.value =
                            doc.ocr_expiry_date;
                    } else {
                        expiryInput.value =
                            '';
                    }

                    expiryLabel.textContent =
                        rule.label;

                    expiryHelp.textContent =
                        rule.help;

                    const required =
                        rule.expiry ===
                        'required';

                    expiryInput.dataset
                        .requiredForApproval =
                        required
                            ? 'true'
                            : 'false';

                    expiryInput.required =
                        required;
                }

                // Shared documents (Government ID, DTI/SEC) are shown for
                // reference in a branch review. Their dates are not edited.
                if (doc.shared) {
                    label.textContent +=
                        ' (shared from main branch)';

                    expiryInput.disabled = true;
                    expiryInput.required = false;

                    expiryInput.dataset
                        .requiredForApproval = 'false';
                }

                if (
                    doc.expiry_verified_at
                ) {
                    reviewedWrapper
                        .classList
                        .remove(
                            'hidden'
                        );

                    reviewed.textContent =
                        'Reviewed ' +
                        doc.expiry_verified_at;
                }

                container.append(
                    row
                );
            }
        );
    }

    document.addEventListener(
        'keydown',
        function (event) {
            const modal =
                MODAL_IDS
                    .map(el)
                    .find(
                        item =>
                            item &&
                            !item
                                .classList
                                .contains(
                                    'hidden'
                                )
                    );

            if (!modal) {
                return;
            }

            if (
                event.key ===
                'Escape'
            ) {
                event.preventDefault();

                closeModal(
                    modal.id
                );

                return;
            }

            if (
                event.key !==
                'Tab'
            ) {
                return;
            }

            const items =
                Array.from(
                    modal.querySelectorAll(
                        FOCUSABLE
                    )
                ).filter(
                    item =>
                        item.offsetParent !==
                        null
                );

            if (!items.length) {
                return;
            }

            const first =
                items[0];

            const last =
                items[
                    items.length -
                    1
                ];

            if (
                event.shiftKey &&
                document.activeElement ===
                    first
            ) {
                event.preventDefault();
                last.focus();
            } else if (
                !event.shiftKey &&
                document.activeElement ===
                    last
            ) {
                event.preventDefault();
                first.focus();
            }
        }
    );

    el('deleteModal').addEventListener(
        'click',
        function (event) {
            if (
                event.target ===
                el('deleteModal')
            ) {
                closeModal(
                    'deleteModal'
                );
            }
        }
    );

    window.closeModal =
        closeModal;

    window.setRejectMode =
        setRejectMode;

    window.openSpaModal =
        function (id, kind) {
            const review =
                REVIEW_KINDS[kind] ||
                REVIEW_KINDS.spa;

            const status =
                el(
                    'spaModalStatus'
                );

            const form =
                el(
                    'spaReviewForm'
                );

            status.textContent =
                'Loading spa details…';

            status.classList.remove(
                'hidden'
            );

            form.classList.add(
                'hidden'
            );

            setRejectMode(
                false
            );

            openModal(
                'spaModal',
                'button'
            );

            fetch(
                review.edit.replace(
                    '__ID__',
                    id
                ),
                {
                    headers: {
                        'Accept':
                            'application/json',

                        'X-Requested-With':
                            'XMLHttpRequest',
                    },
                }
            )
                .then(
                    response => {
                        if (
                            !response.ok
                        ) {
                            throw new Error(
                                'Request failed with status ' +
                                response.status
                            );
                        }

                        return response.json();
                    }
                )
                .then(
                    ({ spa }) => {
                        const verificationStatus =
                            spa.verification_status ??
                            'unverified';

                        const fields = {
                            modalSpaName:
                                spa.name ??
                                '—',

                            modalCurrentTier: (() => {
                                const plan = String(spa.business_tier || 'basic').toLowerCase();

                                if (plan === 'professional') {
                                    return 'Premium';
                                }

                                return ['basic', 'premium', 'business'].includes(plan)
                                    ? plan.charAt(0).toUpperCase() + plan.slice(1)
                                    : 'Basic';
                            })(),

                            modalCurrentStatus:
                                verificationStatus
                                    .charAt(0)
                                    .toUpperCase() +
                                verificationStatus
                                    .slice(1),

                            modalOwnerName:
                                spa.owner_name ??
                                '—',

                            modalOwnerEmail:
                                spa.owner_email ??
                                '—',

                            modalVerifiedBy:
                                spa.verified_by ??
                                '—',

                            modalVerifiedAt:
                                spa.verified_at ??
                                '—',
                        };

                        Object.entries(
                            fields
                        ).forEach(
                            ([
                                fieldId,
                                value,
                            ]) => {
                                const field =
                                    el(
                                        fieldId
                                    );

                                if (field) {
                                    field.textContent =
                                        value;
                                }
                            }
                        );

                        el(
                            'modalVerificationRemarks'
                        ).value =
                            spa.verification_remarks ??
                            '';

                        form.action =
                            review.update.replace(
                                '__ID__',
                                spa.id
                            );

                        el('spaModalTitle').textContent =
                            review.title;

                        renderDocuments(
                            spa.documents ??
                            []
                        );

                        status
                            .classList
                            .add(
                                'hidden'
                            );

                        form
                            .classList
                            .remove(
                                'hidden'
                            );
                    }
                )
                .catch(
                    error => {
                        console.error(
                            'Registered Spa review error:',
                            error
                        );

                        status.textContent =
                            'Could not load this spa. Close the dialog and try again.';
                    }
                );
        };

    window.openDeleteModal =
        function (
            id,
            name
        ) {
            el(
                'deleteSpaName'
            ).textContent =
                name;

            el(
                'deleteForm'
            ).action =
                ROUTE_SPA.replace(
                    '__ID__',
                    id
                );

            openModal(
                'deleteModal',
                'button'
            );
        };
}());

</script>

@endif

@endsection