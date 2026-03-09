@extends('layouts.app')

@section('content')
<div class="p-6">
    <x-page-header
        title="Edit Role — {{ ucfirst($role->name) }}"
        subtitle="Modify the permissions assigned to this staff role."
    />

    @php
        $actions = ['view', 'create', 'edit', 'delete', 'manage'];
    @endphp

    <form method="POST" action="{{ route('owner.roles-permissions.update', $role) }}"
          class="bg-white border rounded-lg dark:bg-gray-800 dark:border-gray-700">
        @csrf
        @method('PUT')

        <div class="p-5 space-y-4">
            @foreach($groups as $group => $perms)
                @php
                    $sections = collect($actions)->mapWithKeys(fn($a) => [$a => collect()]);
                    $sections['other'] = collect();

                    foreach ($perms as $perm) {
                        $name = strtolower($perm->name);
                        $matched = false;
                        foreach ($actions as $a) {
                            if (str_contains($name, $a)) {
                                $sections[$a]->push($perm);
                                $matched = true;
                                break;
                            }
                        }
                        if (! $matched) $sections['other']->push($perm);
                    }

                    $sections = $sections->filter(fn($c) => $c->count() > 0);
                @endphp

                <details class="border rounded-lg group dark:border-gray-700">
                    <summary class="flex items-center justify-between px-4 py-3 cursor-pointer select-none bg-gray-50 dark:bg-gray-700/40">
                        <p class="text-xs font-semibold tracking-wider text-gray-600 uppercase dark:text-gray-200">
                            <i class="mr-2 fa-brands fa-creative-commons-by"></i>{{ $group }} Permissions
                        </p>
                        <svg class="w-4 h-4 text-gray-400 transition-transform duration-200 group-open:rotate-180"
                             viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                        </svg>
                    </summary>

                    <div class="p-4 space-y-5 bg-white details-anim dark:bg-gray-800">
                        @foreach($sections as $action => $items)
                            <div>
                                <p class="mb-2 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-300">
                                    {{ $action }}
                                </p>
                                <div class="grid gap-2 md:grid-cols-2">
                                    @foreach($items as $perm)
                                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                            <input type="checkbox"
                                                   name="permissions[]"
                                                   value="{{ $perm->name }}"
                                                   @checked($role->permissions->contains('name', $perm->name))
                                                   class="border-gray-300 rounded dark:border-gray-600">
                                            <span>{{ $perm->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>

        <div class="flex items-center justify-end gap-2 px-5 py-4 border-t bg-gray-50 dark:bg-gray-700/50 dark:border-gray-700">
            <a href="{{ route('owner.roles-permissions.index') }}"
               class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200">
                Cancel
            </a>
            <button class="px-4 py-2 text-sm font-medium text-white bg-[#8B7355] rounded-lg hover:opacity-90">
                Save Changes
            </button>
        </div>
    </form>
</div>

@if (session('success'))
<script>
    if (!window.successToastShown) {
        window.successToastShown = true;
        document.addEventListener('DOMContentLoaded', function () {
            Toastify({
                text: `
                    <div class="flex items-center gap-3">
                        <i class="text-green-600 fa-solid fa-check-circle"></i>
                        <span class="text-green-600">{{ session('success') }}</span>
                    </div>
                `,
                duration: 3000,
                gravity: "top",
                position: "right",
                close: true,
                escapeMarkup: false,
                backgroundColor: "#ffffff",
                style: {
                    border: "1px solid #16a34a",
                    borderRadius: "10px",
                    minWidth: "300px",
                    boxShadow: "0 8px 20px rgba(0,0,0,0.08)"
                }
            }).showToast();
        });
    }
</script>
@endif

@if ($errors->any())
<script>
    if (!window.errorToastShown) {
        window.errorToastShown = true;
        document.addEventListener('DOMContentLoaded', function () {
            Toastify({
                text: `
                    <div class="flex items-center gap-3">
                        <i class="text-red-600 fa-solid fa-circle-xmark"></i>
                        <span class="text-red-600">{{ $errors->first() }}</span>
                    </div>
                `,
                duration: 4000,
                gravity: "top",
                position: "right",
                close: true,
                escapeMarkup: false,
                backgroundColor: "#ffffff",
                style: {
                    border: "1px solid #dc2626",
                    borderRadius: "10px",
                    minWidth: "300px",
                    boxShadow: "0 8px 20px rgba(0,0,0,0.08)"
                }
            }).showToast();
        });
    }
</script>
@endif

<style>
details > .details-anim { overflow: hidden; }
details[open] > .details-anim {
    animation: details-slide-down 300ms ease-out;
    transform-origin: top;
}
@keyframes details-slide-down {
    from { opacity: 0; transform: translateY(-6px) scaleY(0.98); }
    to   { opacity: 1; transform: translateY(0) scaleY(1); }
}
</style>
@endsection
