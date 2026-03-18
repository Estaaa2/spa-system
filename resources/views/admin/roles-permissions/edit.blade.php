@extends('layouts.app')

@section('content')
<div class="p-6">
    <x-page-header
        title="Edit Role — {{ ucfirst($role->name) }}"
        subtitle="Modify the permissions assigned to this role."
    />

    @php
        $isAdminRole = strtolower($role->name) === 'admin';
        $actions = ['view', 'create', 'edit', 'delete', 'manage'];
    @endphp

    @if($isAdminRole)
        <div class="p-5 bg-white border rounded-lg dark:bg-gray-800 dark:border-gray-700">
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Admin role is protected</p>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                This role can't be edited from the UI to avoid locking yourself out.
            </p>
            <div class="mt-4">
                <a href="{{ route('admin.roles-permissions.index') }}"
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200">
                    Back to Roles
                </a>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('admin.roles-permissions.update', $role) }}"
              class="bg-white border rounded-lg dark:bg-gray-800 dark:border-gray-700">
            @csrf
            @method('PUT')

            <div class="p-5 space-y-4">
                @foreach($groups as $group => $perms)
                    @php
                        // ✅ FIX: Group by FIRST WORD only — not str_contains
                        $sections = collect($actions)->mapWithKeys(fn($a) => [$a => collect()]);
                        $sections['other'] = collect();

                        foreach ($perms as $perm) {
                            $name      = strtolower($perm->name);
                            $firstWord = explode(' ', $name)[0]; // ✅ use first word only

                            if (in_array($firstWord, $actions)) {
                                $sections[$firstWord]->push($perm);
                            } else {
                                $sections['other']->push($perm);
                            }
                        }

                        $sections = $sections->filter(fn($c) => $c->count() > 0);
                    @endphp

                    {{-- One dropdown per group --}}
                    <details class="border rounded-lg group dark:border-gray-700">
                        <summary class="flex items-center justify-between px-4 py-3 cursor-pointer select-none bg-gray-50 dark:bg-gray-700/40">
                            <p class="text-xs font-semibold tracking-wider text-gray-600 uppercase dark:text-gray-200">
                                <i class="mr-2 fa-brands fa-creative-commons-by"></i>
                                {{ $group }} Permissions
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
                                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer dark:text-gray-200">
                                                <input type="checkbox"
                                                       name="permissions[]"
                                                       value="{{ $perm->name }}"
                                                       @checked($role->permissions->contains('name', $perm->name))
                                                       class="border-gray-300 rounded dark:border-gray-600 text-[#8B7355] focus:ring-[#8B7355]/30">
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
                <a href="{{ route('admin.roles-permissions.index') }}"
                   class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200">
                    Cancel
                </a>
                <button class="px-4 py-2 text-sm font-medium text-white bg-[#8B7355] rounded-lg hover:opacity-90">
                    Save Changes
                </button>
            </div>
        </form>
    @endif
</div>

<style>
details > .details-anim {
    overflow: hidden;
}
details[open] > .details-anim {
    animation: details-slide-down 300ms ease-out;
    transform-origin: top;
}
details:not([open]) > .details-anim {
    animation: details-slide-up 180ms ease-in;
    transform-origin: top;
}
@keyframes details-slide-down {
    from { opacity: 0; transform: translateY(-6px) scaleY(0.98); }
    to   { opacity: 1; transform: translateY(0) scaleY(1); }
}
@keyframes details-slide-up {
    from { opacity: 1; transform: translateY(0) scaleY(1); }
    to   { opacity: 0; transform: translateY(-6px) scaleY(0.98); }
}
</style>
@endsection
