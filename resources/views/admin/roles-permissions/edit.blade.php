@extends('layouts.app')

@section('content')
<div class="p-6 space-y-6">
    <x-page-header
        title="Edit Default Role — {{ $roleMeta['title'] }}"
        subtitle="Update the universal permission template for this business role."
    />

    <div class="p-4 border rounded-2xl bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-700">
        <div class="flex items-start gap-3">
            <i class="mt-0.5 fa-solid fa-circle-info text-amber-600 dark:text-amber-400"></i>
            <div>
                <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">
                    Default role template
                </p>
                <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                    Saving here updates the platform default for this role. Branches that still rely on defaults will follow these permissions.
                </p>
            </div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-4">
        <div class="p-4 bg-white border rounded-2xl dark:bg-gray-800 dark:border-gray-700 md:col-span-2">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-[#8B7355]/10 text-[#8B7355]">
                    <i class="{{ $roleMeta['icon'] }}"></i>
                </div>
                <div>
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Role Template</p>
                    <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">{{ $roleMeta['title'] }}</h2>
                </div>
            </div>

            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                {{ $roleMeta['description'] }}
            </p>
        </div>

        <div class="p-4 bg-white border rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Selected</p>
            <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-gray-100" id="overall-selected">
                {{ $summary['selected'] }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Default permissions</p>
        </div>

        <div class="p-4 bg-white border rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Branches on default</p>
            <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-gray-100">
                {{ $defaultBranchesCount }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ $customizedBranchesCount }} customized
            </p>
        </div>
    </div>

    <form method="POST"
          action="{{ route('admin.roles-permissions.update', $role) }}"
          id="permission-form"
          class="bg-white border rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        @csrf
        @method('PUT')

        <div class="flex flex-col gap-3 px-6 py-4 border-b bg-gray-50/70 rounded-t-2xl dark:bg-gray-900/20 dark:border-gray-700 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                    Permission Sections
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Business permissions are grouped by feature for easier default-role setup.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('admin.roles-permissions.index') }}"
                   class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200">
                    Cancel
                </a>
                <button type="submit"
                        class="px-4 py-2 text-sm font-medium text-white rounded-lg bg-[#8B7355] hover:opacity-90">
                    Save Changes
                </button>
            </div>
        </div>

        <div class="p-6 space-y-4">
            @foreach($sections as $section)
                <details class="overflow-hidden border rounded-2xl group dark:border-gray-700" closed>
                    <summary class="flex items-center justify-between gap-4 px-5 py-4 cursor-pointer select-none bg-gray-50 dark:bg-gray-900/20">
                        <div class="flex items-start gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-[#8B7355]/10 text-[#8B7355]">
                                <i class="{{ $section['icon'] }}"></i>
                            </div>

                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                        {{ $section['title'] }}
                                    </h3>
                                    <span class="px-2 py-1 text-xs font-medium text-gray-600 bg-white border rounded-full dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                                        <span data-section-selected="{{ $section['key'] }}">{{ $section['selected_count'] }}</span>/{{ $section['total_count'] }} selected
                                    </span>
                                </div>

                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $section['description'] }}
                                </p>
                            </div>
                        </div>

                        <svg class="w-4 h-4 text-gray-400 transition-transform duration-200 group-open:rotate-180"
                             viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                        </svg>
                    </summary>

                    <div class="p-5 bg-white dark:bg-gray-800">
                        <div class="flex flex-wrap items-center gap-2 mb-4">
                            <button type="button"
                                    data-select-section="{{ $section['key'] }}"
                                    class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200">
                                Select all
                            </button>

                            <button type="button"
                                    data-clear-section="{{ $section['key'] }}"
                                    class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200">
                                Clear all
                            </button>
                        </div>

                        <div class="grid gap-3 lg:grid-cols-2">
                            @foreach($section['permissions'] as $permission)
                                <label class="flex items-start gap-3 p-4 transition border rounded-xl cursor-pointer hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-900/20">
                                    <input type="checkbox"
                                           name="permissions[]"
                                           value="{{ $permission['name'] }}"
                                           data-permission-checkbox
                                           data-section="{{ $section['key'] }}"
                                           {{ $permission['checked'] ? 'checked' : '' }}
                                           class="mt-1 border-gray-300 rounded text-[#8B7355] focus:ring-[#8B7355]">

                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-100">
                                            {{ $permission['label'] }}
                                        </p>
                                        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">
                                            {{ $permission['description'] }}
                                        </p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </details>
            @endforeach
        </div>

        <div class="flex items-center justify-between px-6 py-4 border-t bg-gray-50 rounded-b-2xl dark:bg-gray-900/20 dark:border-gray-700">
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Admin-only permissions are intentionally hidden from this editor.
            </p>

            <button type="submit"
                    class="px-4 py-2 text-sm font-medium text-white rounded-lg bg-[#8B7355] hover:opacity-90">
                Save Changes
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('permission-form');
    const checkboxes = Array.from(form.querySelectorAll('[data-permission-checkbox]'));

    function updateCounts() {
        let totalChecked = 0;

        checkboxes.forEach((checkbox) => {
            if (checkbox.checked) totalChecked++;
        });

        const overall = document.getElementById('overall-selected');
        if (overall) {
            overall.textContent = totalChecked;
        }

        const sectionNames = [...new Set(checkboxes.map(cb => cb.dataset.section))];

        sectionNames.forEach((section) => {
            const sectionBoxes = checkboxes.filter(cb => cb.dataset.section === section);
            const sectionChecked = sectionBoxes.filter(cb => cb.checked).length;
            const counter = document.querySelector(`[data-section-selected="${section}"]`);

            if (counter) {
                counter.textContent = sectionChecked;
            }
        });
    }

    document.querySelectorAll('[data-select-section]').forEach((button) => {
        button.addEventListener('click', function () {
            const section = this.dataset.selectSection;
            checkboxes
                .filter(cb => cb.dataset.section === section)
                .forEach(cb => cb.checked = true);

            updateCounts();
        });
    });

    document.querySelectorAll('[data-clear-section]').forEach((button) => {
        button.addEventListener('click', function () {
            const section = this.dataset.clearSection;
            checkboxes
                .filter(cb => cb.dataset.section === section)
                .forEach(cb => cb.checked = false);

            updateCounts();
        });
    });

    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateCounts);
    });

    updateCounts();
});
</script>
@endsection