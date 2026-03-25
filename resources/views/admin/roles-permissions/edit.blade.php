@extends('layouts.app')

@section('content')
<div class="p-6">
    <x-page-header
        title="Edit Default Role — {{ ucfirst($role->name) }}"
        subtitle="These permissions become the default template for newly initialized spa branches."
    />

    <div class="p-4 mb-5 border rounded-xl bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-700">
        <div class="flex items-start gap-3">
            <i class="mt-0.5 fa-solid fa-circle-info text-amber-600 dark:text-amber-400"></i>
            <div>
                <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Default role template</p>
                <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                    Changes here affect newly initialized spa branches only. Existing branches keep their current role setup unless manually changed by the spa owner.
                </p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.roles-permissions.update', $role) }}"
          class="bg-white border rounded-xl dark:bg-gray-800 dark:border-gray-700">
        @csrf
        @method('PUT')

        <div class="p-5 space-y-4">
            @foreach($groups as $group => $perms)
                <details class="overflow-hidden border rounded-xl group dark:border-gray-700">
                    <summary class="flex items-center justify-between px-4 py-3 cursor-pointer bg-gray-50 dark:bg-gray-700/40">
                        <div>
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $group }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $perms->count() }} permissions</p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button"
                                    class="section-check-all px-2.5 py-1 text-xs font-medium text-[#8B7355] border border-[#8B7355]/30 rounded-lg hover:bg-[#8B7355]/5"
                                    data-group="{{ \Illuminate\Support\Str::slug($group) }}">
                                Select all
                            </button>
                            <button type="button"
                                    class="section-clear-all px-2.5 py-1 text-xs font-medium text-gray-600 border rounded-lg hover:bg-gray-50 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700"
                                    data-group="{{ \Illuminate\Support\Str::slug($group) }}">
                                Clear
                            </button>
                            <i class="fa-solid fa-chevron-down text-xs text-gray-400 transition group-open:rotate-180"></i>
                        </div>
                    </summary>

                    <div class="p-4 bg-white dark:bg-gray-800">
                        <div class="grid gap-3 md:grid-cols-2">
                            @foreach($perms as $perm)
                                <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/40">
                                    <input
                                        type="checkbox"
                                        name="permissions[]"
                                        value="{{ $perm->name }}"
                                        data-group="{{ \Illuminate\Support\Str::slug($group) }}"
                                        @checked($role->permissions->contains('name', $perm->name))
                                        class="mt-1 border-gray-300 rounded text-[#8B7355] focus:ring-[#8B7355]/30"
                                    >
                                    <div>
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ ucwords($perm->name) }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Grants this role access to {{ strtolower($perm->name) }}.
                                        </p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </details>
            @endforeach
        </div>

        <div class="flex items-center justify-between gap-3 px-5 py-4 border-t bg-gray-50 dark:bg-gray-700/40 dark:border-gray-700">
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Only business-role defaults are shown here.
            </p>

            <div class="flex items-center gap-2">
                <a href="{{ route('admin.roles-permissions.index') }}"
                   class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200">
                    Cancel
                </a>
                <button class="px-4 py-2 text-sm font-medium text-white bg-[#8B7355] rounded-lg hover:opacity-90">
                    Save Changes
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.section-check-all').forEach(button => {
        button.addEventListener('click', function () {
            const group = this.dataset.group;
            document.querySelectorAll(`input[type="checkbox"][data-group="${group}"]`)
                .forEach(cb => cb.checked = true);
        });
    });

    document.querySelectorAll('.section-clear-all').forEach(button => {
        button.addEventListener('click', function () {
            const group = this.dataset.group;
            document.querySelectorAll(`input[type="checkbox"][data-group="${group}"]`)
                .forEach(cb => cb.checked = false);
        });
    });
});
</script>
@endsection