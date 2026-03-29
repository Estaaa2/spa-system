@extends('layouts.app')

@section('content')
<div class="p-6">
    <x-page-header
        title="Registered Spas"
        subtitle="Manage spa tiers and information"
    >
    </x-page-header>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 gap-4 mb-6 lg:grid-cols-3">
        <div class="p-6 bg-white border shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-sm text-gray-500 dark:text-gray-400">Total Registered Spas</p>
            <h2 class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                {{ $spas->total() }}
            </h2>
        </div>

        <div class="p-6 bg-white border shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-sm text-gray-500 dark:text-gray-400">Pending Verification</p>
            <h2 class="mt-2 text-3xl font-semibold text-yellow-600 dark:text-yellow-400">
                {{ $pendingSpas->count() }}
            </h2>
        </div>

        <div class="p-6 bg-white border shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-sm text-gray-500 dark:text-gray-400">Verified Businesses</p>
            <h2 class="mt-2 text-3xl font-semibold text-green-600 dark:text-green-400">
                {{ $verifiedCount }}
            </h2>
        </div>
    </div>

    @if ($pendingSpas->isNotEmpty())
        <div class="mb-6 bg-white border shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <div class="px-6 py-4 border-b dark:border-gray-700">
                <h2 class="text-sm font-semibold tracking-wide text-yellow-700 uppercase dark:text-yellow-400">
                    Pending Verification Review
                </h2>
            </div>

            <div class="divide-y dark:divide-gray-700">
                @foreach ($pendingSpas as $spa)
                    <div class="flex flex-col gap-3 px-6 py-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h3 class="font-medium text-gray-900 dark:text-white">{{ $spa->name }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Owner: {{ $spa->owner->name ?? 'N/A' }}
                            </p>
                        </div>

                        <button
                            type="button"
                            onclick="openSpaModal({{ $spa->id }})"
                            class="px-4 py-2 text-sm font-medium text-white bg-[#8B7355] rounded-lg hover:bg-[#7A6348]">
                            Review Now
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- MAIN CARD -->
    <div class="bg-white border shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
        <!-- Card Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                Spas
            </h2>

            <form method="GET" class="flex gap-2">
                <input
                    name="q"
                    value="{{ request('q') }}"
                    class="w-64 px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white"
                    placeholder="Search spa name or owner"
                >
                <button
                    class="px-4 py-2 text-sm font-medium text-white bg-[#8B7355] rounded-lg hover:opacity-90">
                    Search
                </button>
            </form>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/30">
                    <tr class="text-left text-gray-600 dark:text-gray-300">
                        <th class="px-6 py-3">Spa Name</th>
                        <th class="px-6 py-3">Owner</th>
                        <th class="px-6 py-3">Business Tier</th>
                        <th class="px-6 py-3">Verification Status</th>
                        <th class="px-6 py-3">Verified At</th>
                        <th class="px-6 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($spas as $spa)
                        @php
                            $statusClasses = match($spa->verification_status) {
                                'verified' => 'bg-green-100 text-green-800 border border-green-200',
                                'pending' => 'bg-yellow-100 text-yellow-800 border border-yellow-200',
                                'rejected' => 'bg-red-100 text-red-800 border border-red-200',
                                default => 'bg-gray-100 text-gray-800 border border-gray-200',
                            };
                        @endphp

                        <tr class="border-t dark:border-gray-700">
                            <td class="px-6 py-3 text-gray-800 dark:text-gray-100">
                                {{ $spa->name }}
                            </td>
                            <td class="px-6 py-3 text-gray-600 dark:text-gray-300">
                                {{ $spa->owner->name ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-3">
                                <span class="px-2 py-1 text-xs rounded text-white
                                    {{ $spa->business_tier == 'basic' ? 'bg-gray-700' : ($spa->business_tier == 'professional' ? 'bg-blue-500' : 'bg-yellow-500') }} rounded dark:text-gray-200">
                                    {{ ucfirst($spa->business_tier) }}
                                </span>
                            </td>
                            <td class="px-6 py-3">
                                <span class="inline-flex px-2.5 py-1 text-xs font-medium rounded-full {{ $statusClasses }}">
                                    {{ ucfirst($spa->verification_status) }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-gray-600 dark:text-gray-300">
                                {{ $spa->verified_at ? $spa->verified_at->format('M d, Y') : '—' }}
                            </td>
                            <td class="px-6 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <button
                                        type="button"
                                        onclick="openSpaModal({{ $spa->id }})"
                                        class="px-3 py-1 text-sm text-white bg-yellow-500 rounded hover:bg-yellow-600">
                                        Review
                                    </button>

                                    <button
                                        type="button"
                                        onclick="openDeleteModal({{ $spa->id }}, '{{ addslashes($spa->name) }}')"
                                        class="px-3 py-1 text-sm text-white bg-red-600 rounded hover:bg-red-700">
                                        Archive
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                No spas found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t dark:border-gray-700">
            {{ $spas->withQueryString()->links() }}
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div id="deleteModal" class="fixed inset-0 z-50 flex items-center justify-center hidden p-4 bg-black bg-opacity-50">
    <div class="w-full max-w-md bg-white shadow-xl rounded-xl dark:bg-gray-800">
        <div class="flex items-center justify-between px-6 py-4 border-b dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Archive Spa</h3>
            <button type="button" onclick="closeDeleteModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                <i class="text-xl fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="p-6">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Are you sure you want to archive
                <span id="deleteSpaName" class="font-semibold text-red-600"></span>?
                This action cannot be undone.
            </p>

            <div class="flex justify-end gap-2 mt-6">
                <button
                    type="button"
                    onclick="closeDeleteModal()"
                    class="px-4 py-2 text-sm font-medium bg-white border rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200">
                    Cancel
                </button>

                <form id="deleteForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">
                        Yes, Archive
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- REVIEW MODAL -->
<div id="spaModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50">
    <div class="w-full max-w-4xl p-6 mx-auto mt-10 bg-white rounded-xl shadow-xl dark:bg-gray-800 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b dark:border-gray-700">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Spa Details</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Review business information, documents, and verification status.
                </p>
            </div>

            <button type="button" onclick="closeSpaModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                <i class="text-xl fa-solid fa-xmark"></i>
            </button>
        </div>

        <div id="spaModalLoading" class="py-10 text-center text-gray-500 dark:text-gray-400">
            Loading spa details...
        </div>

        <div id="spaModalContent" class="hidden">
            <form id="spaReviewForm" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                <input type="hidden" name="verification_status" id="modalVerificationStatus" value="pending">

                <div class="grid grid-cols-1 gap-6 mt-6 lg:grid-cols-2">
                    <div class="p-5 border rounded-xl dark:border-gray-700">
                        <h3 class="mb-4 text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                            Business Information
                        </h3>

                        <dl class="space-y-3 text-sm">
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Spa Name</dt>
                                <dd id="modalSpaName" class="font-medium text-gray-900 dark:text-white"></dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Owner</dt>
                                <dd id="modalOwnerName" class="font-medium text-gray-900 dark:text-white"></dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Owner Email</dt>
                                <dd id="modalOwnerEmail" class="font-medium text-gray-900 dark:text-white"></dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Verified By</dt>
                                <dd id="modalVerifiedBy" class="font-medium text-gray-900 dark:text-white">—</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Verified At</dt>
                                <dd id="modalVerifiedAt" class="font-medium text-gray-900 dark:text-white">—</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Current Status</dt>
                                <dd id="modalCurrentStatus" class="font-medium text-gray-900 dark:text-white">—</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="p-5 border rounded-xl dark:border-gray-700">
                        <h3 class="mb-4 text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                            Review Settings
                        </h3>

                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Business Tier
                            </label>
                            <p class="mt-2 mb-2 text-xs text-red-600 dark:text-red-400">
                                Warning: Changing tiers manually is not recommended.
                            </p>
                            <select name="business_tier"
                                    id="modalTier"
                                    class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white">
                                <option value="basic">Basic</option>
                                <option value="professional">Professional</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="p-5 border rounded-xl dark:border-gray-700">
                    <h3 class="mb-4 text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                        Uploaded Verification Documents
                    </h3>

                    <div id="documentsContainer" class="space-y-3">
                        {{-- JS-rendered --}}
                    </div>

                    <div id="rejectionReasonWrapper" class="hidden mt-4">
                        <label for="modalVerificationRemarks" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Reason for Rejection <span class="text-red-600">*</span>
                        </label>
                        <textarea
                            name="verification_remarks"
                            id="modalVerificationRemarks"
                            rows="4"
                            class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white"
                            placeholder="Explain why the spa was rejected..."></textarea>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            This is required when rejecting a spa verification.
                        </p>
                    </div>

                    <div id="reviewActions" class="flex flex-col-reverse gap-2 mt-4 sm:flex-row sm:justify-end">

                        <button type="button"
                                onclick="prepareReject()"
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">
                            Reject
                        </button>

                        <button type="button"
                                onclick="submitSpaReview('verified')"
                                class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700">
                            Accept
                        </button>
                    </div>
                    <button type="button"
                            id="confirmRejectBtn"
                            onclick="submitSpaReview('rejected')"
                            class="hidden px-4 py-2 text-sm font-medium text-white bg-red-700 rounded-lg hover:bg-red-800 mt-2">
                        Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL SCRIPT -->
<script>

let rejectPending = false;

function prepareReject() {
    rejectPending = true;

    showRejectionField();

    document.getElementById('reviewActions').classList.add('hidden');

    document.getElementById('confirmRejectBtn').classList.remove('hidden');
}

function getDocumentLabel(type) {
    switch (type) {
        case 'government_id':
            return 'Government ID';
        case 'dti_sec':
            return 'DTI / SEC Certificate';
        case 'bir_certificate':
            return 'BIR Certificate of Registration';
        default:
            return type;
    }
}

function formatStatus(status) {
    if (!status) return '—';

    return status
        .replaceAll('_', ' ')
        .replace(/\b\w/g, char => char.toUpperCase());
}

function resetReviewDecision() {
    rejectPending = false;

    const statusInput = document.getElementById('modalVerificationStatus');
    const remarks = document.getElementById('modalVerificationRemarks');
    const rejectionWrapper = document.getElementById('rejectionReasonWrapper');

    statusInput.value = 'pending';
    remarks.value = '';
    remarks.removeAttribute('required');
    rejectionWrapper.classList.add('hidden');

    document.getElementById('reviewActions').classList.remove('hidden');
    document.getElementById('confirmRejectBtn').classList.add('hidden');
}

function showRejectionField() {
    const remarks = document.getElementById('modalVerificationRemarks');
    const rejectionWrapper = document.getElementById('rejectionReasonWrapper');

    rejectionWrapper.classList.remove('hidden');
    remarks.setAttribute('required', 'required');
    remarks.focus();
}

function hideRejectionField() {
    const remarks = document.getElementById('modalVerificationRemarks');
    const rejectionWrapper = document.getElementById('rejectionReasonWrapper');

    rejectionWrapper.classList.add('hidden');
    remarks.removeAttribute('required');
}

function submitSpaReview(status) {
    const form = document.getElementById('spaReviewForm');
    const statusInput = document.getElementById('modalVerificationStatus');
    const remarks = document.getElementById('modalVerificationRemarks');

    statusInput.value = status;

    if (status === 'rejected') {
        if (!remarks.value.trim()) {
            remarks.reportValidity();
            return;
        }
    } else {
        remarks.value = '';
    }

    form.submit();
}

function openDeleteModal(spaId, spaName) {
    document.getElementById('deleteSpaName').textContent = spaName;
    document.getElementById('deleteForm').action = `/admin/registered-spas/${spaId}`;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

function openSpaModal(spaId) {
    const modal = document.getElementById('spaModal');
    const loading = document.getElementById('spaModalLoading');
    const content = document.getElementById('spaModalContent');

    modal.classList.remove('hidden');
    loading.classList.remove('hidden');
    content.classList.add('hidden');

    resetReviewDecision();

    fetch(`/admin/registered-spas/${spaId}/edit`)
        .then(response => response.json())
        .then(data => {
            const spa = data.spa;

            document.getElementById('modalSpaName').textContent = spa.name;
            document.getElementById('modalOwnerName').textContent = spa.owner_name;
            document.getElementById('modalOwnerEmail').textContent = spa.owner_email;
            document.getElementById('modalVerifiedBy').textContent = spa.verified_by ?? '—';
            document.getElementById('modalVerifiedAt').textContent = spa.verified_at ?? '—';
            document.getElementById('modalCurrentStatus').textContent = formatStatus(spa.verification_status);

            document.getElementById('modalTier').value = spa.business_tier;
            document.getElementById('modalVerificationStatus').value = spa.verification_status ?? 'pending';
            document.getElementById('modalVerificationRemarks').value = spa.verification_remarks ?? '';
            document.getElementById('spaReviewForm').action = `/admin/registered-spas/${spa.id}`;

            const docsContainer = document.getElementById('documentsContainer');
            docsContainer.innerHTML = '';

            if (!spa.documents.length) {
                docsContainer.innerHTML = `
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        No verification documents uploaded yet.
                    </p>
                `;
            } else {
                spa.documents.forEach(doc => {
                    docsContainer.innerHTML += `
                        <div class="flex flex-col gap-3 p-4 border rounded-lg md:flex-row md:items-center md:justify-between dark:border-gray-700">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">${getDocumentLabel(doc.document_type)}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">${doc.file_name ?? 'Unnamed file'}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Uploaded on ${doc.uploaded_at}</p>
                            </div>
                            <a href="${doc.file_url}" target="_blank"
                               class="inline-flex px-3 py-2 text-sm font-medium text-blue-600 underline dark:text-blue-400">
                                View Document
                            </a>
                        </div>
                    `;
                });
            }

            loading.classList.add('hidden');
            content.classList.remove('hidden');
        })
        .catch(() => {
            loading.innerHTML = `
                <p class="text-sm text-red-600 dark:text-red-400">
                    Failed to load spa details.
                </p>
            `;
        });
}

function closeSpaModal() {
    document.getElementById('spaModal').classList.add('hidden');
    resetReviewDecision();
}
</script>
@endsection
