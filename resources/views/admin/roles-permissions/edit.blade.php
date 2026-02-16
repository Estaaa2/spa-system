@extends('layouts.app')

@section('content')
<div class="p-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">
                Edit Permissions — {{ $role->name }}
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Select permissions for this role, then save.
            </p>
        </div>

        <a href="{{ route('roles-permissions.index') }}"
           class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200">
            Back
        </a>
    </div>

    <form method="POST" action="{{ route('roles-permissions.update', $role) }}"
          class="bg-white border rounded-lg dark:bg-gray-800 dark:border-gray-700">
        @csrf
        @method('PUT')

        <div class="p-5 space-y-6">
            @foreach($groups as $group => $perms)
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold tracking-wider text-gray-400 uppercase">
                            {{ $group }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $perms->count() }} permissions
                        </p>
                    </div>

                    <div class="grid gap-2 md:grid-cols-2">
                        @foreach($perms as $perm)
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

        <div class="flex items-center justify-end gap-2 px-5 py-4 border-t bg-gray-50 dark:bg-gray-700/50 dark:border-gray-700">
            <a href="{{ route('roles-permissions.index') }}"
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
                escapeMarkup: false, // ✅ REQUIRED
                backgroundColor: "#ffffff",
                style: {
                    border: "1px solid #16a34a",
                    borderRadius: "10px",
                    minWidth: "300px",
                    display: "flex",
                    alignItems: "center",
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
                        <span class="text-red-600">
                            {{ $errors->first() }}
                        </span>
                    </div>
                `,
                duration: 4000,
                gravity: "top",
                position: "right",
                close: true,
                escapeMarkup: false, // ✅ allow icon HTML
                backgroundColor: "#ffffff",
                style: {
                    border: "1px solid #dc2626",   // red-600
                    borderRadius: "10px",
                    minWidth: "300px",
                    display: "flex",
                    alignItems: "center",
                    boxShadow: "0 8px 20px rgba(0,0,0,0.08)"
                }
            }).showToast();
        });
    }
</script>
@endif

@endsection
