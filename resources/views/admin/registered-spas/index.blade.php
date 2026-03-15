@extends('layouts.app')

@section('content')
<div class="p-6">
    <x-page-header
        title="Registered Spas"
        subtitle="Manage spa tiers and information"
    >
    </x-page-header>

    <!-- CARD -->
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
                        <th class="px-6 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($spas as $spa)
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
                            <td class="px-6 py-3 text-center">
                                <button
                                    onclick="openEditTierModal({{ $spa->id }}, '{{ $spa->name }}', '{{ $spa->business_tier }}')"
                                    class="px-3 py-1 text-sm text-white bg-yellow-500 rounded hover:bg-yellow-600">
                                    Edit
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                No spas found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- EDIT TIER MODAL -->
<div id="editTierModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50">
    <div class="w-full max-w-md p-6 mx-auto mt-24 bg-white rounded-lg dark:bg-gray-800">
        <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">
            Edit Business Tier
        </h2>

        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
            Update tier for <span id="modalSpaName" class="font-medium"></span>
        </p>

        <form id="editTierForm" method="POST">
            @csrf
            @method('PUT')

            <select name="business_tier"
                    id="modalTierSelect"
                    class="w-full px-3 py-2 mb-4 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white">
                <option value="basic">Basic</option>
                <option value="professional">Professional</option>
            </select>

            <div class="flex justify-end gap-2">
                <button type="button"
                        onclick="closeEditTierModal()"
                        class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded-lg dark:bg-gray-700 dark:text-gray-300">
                    Cancel
                </button>
                <button type="submit"
                        class="px-4 py-2 text-sm font-medium text-white bg-[#8B7355] border border-transparent rounded-md shadow-sm hover:bg-[#7A6348] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#8B7355]">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL SCRIPT -->
<script>
function openEditTierModal(spaId, spaName, currentTier) {
    document.getElementById('modalSpaName').textContent = spaName;
    document.getElementById('modalTierSelect').value = currentTier;
    document.getElementById('editTierForm').action = `/admin/registered-spas/${spaId}`;
    document.getElementById('editTierModal').classList.remove('hidden');
}

function closeEditTierModal() {
    document.getElementById('editTierModal').classList.add('hidden');
}
</script>
@endsection