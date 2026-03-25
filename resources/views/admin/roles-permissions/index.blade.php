@extends('layouts.app')

@section('content')
<div class="p-6 space-y-6">
    <x-page-header
        title="Default Roles & Permissions"
        subtitle="Manage the platform-wide role templates used by branches that still follow system defaults."
    />

    <div class="p-4 border rounded-2xl bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-700">
        <div class="flex items-start gap-3">
            <i class="mt-0.5 fa-solid fa-circle-info text-amber-600 dark:text-amber-400"></i>
            <div>
                <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">
                    Platform default templates
                </p>
                <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                    Changes made here update the universal business-role defaults. These are mainly used by branches that still rely on default role settings.
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white border shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                System Role Templates
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Choose a role below to update its default permissions.
            </p>
        </div>

        <div class="grid gap-4 p-6 md:grid-cols-2 xl:grid-cols-3">
            @foreach($roles as $role)
                <div class="border rounded-2xl p-5 bg-gray-50/70 dark:bg-gray-900/20 dark:border-gray-700">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="inline-flex items-center gap-2 text-gray-800 dark:text-gray-100">
                                <i class="{{ $role->ui_icon }}"></i>
                                <h3 class="text-base font-semibold">{{ $role->ui_title }}</h3>
                            </div>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                {{ $role->ui_description }}
                            </p>
                        </div>

                        <span class="px-2.5 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded-full dark:bg-blue-900/30 dark:text-blue-300">
                            Default
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mt-5">
                        <div class="p-3 bg-white border rounded-xl dark:bg-gray-800 dark:border-gray-700">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Users</p>
                            <p class="mt-1 text-lg font-semibold text-gray-800 dark:text-gray-100">
                                {{ $role->users_count }}
                            </p>
                        </div>

                        <div class="p-3 bg-white border rounded-xl dark:bg-gray-800 dark:border-gray-700">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Permissions</p>
                            <p class="mt-1 text-lg font-semibold text-gray-800 dark:text-gray-100">
                                {{ $role->default_permission_count }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mt-3">
                        <div class="p-3 bg-white border rounded-xl dark:bg-gray-800 dark:border-gray-700">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Branches on default</p>
                            <p class="mt-1 text-lg font-semibold text-gray-800 dark:text-gray-100">
                                {{ $role->default_branches_count }}
                            </p>
                        </div>

                        <div class="p-3 bg-white border rounded-xl dark:bg-gray-800 dark:border-gray-700">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Customized branches</p>
                            <p class="mt-1 text-lg font-semibold text-gray-800 dark:text-gray-100">
                                {{ $role->customized_branches_count }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mt-4 text-xs text-gray-500 dark:text-gray-400">
                        <span>{{ $totalBranches }} total branch{{ $totalBranches === 1 ? '' : 'es' }}</span>
                        <a href="{{ route('admin.roles-permissions.edit', $role) }}"
                           class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-lg bg-[#8B7355] hover:opacity-90">
                            <i class="fa-solid fa-pen-to-square"></i>
                            Edit
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="px-6 py-4 text-xs text-gray-500 border-t dark:border-gray-700 dark:text-gray-400">
            These are platform defaults for business roles only. Admin-only permissions are not editable here.
        </div>
    </div>
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