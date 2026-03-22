@extends('layouts.app')

@section('content')
<div class="p-6">
    <x-page-header
        title="Edit Role — {{ ucfirst($role->name) }}"
        subtitle="Modify the permissions assigned to this staff role."
    />

    {{-- Branch Context Banner --}}
    <div class="flex items-center gap-3 px-4 py-3 mb-5 text-sm text-blue-700 border border-blue-200 rounded-lg bg-blue-50 dark:bg-blue-900/20 dark:border-blue-700 dark:text-blue-300">
        <i class="fa-solid fa-code-branch"></i>
        <span>Editing <strong>{{ $role->name }}</strong> permissions for branch: <strong>{{ $branch->name }}</strong> — {{ $branch->location }}</span>
        <span class="ml-auto text-xs text-blue-500 dark:text-blue-400">Switch branches from the top nav to edit another branch.</span>
    </div>

    <form method="POST" action="{{ route('owner.roles-permissions.update', $role) }}"
          class="bg-white border rounded-lg dark:bg-gray-800 dark:border-gray-700">
        @csrf
        @method('PUT')

        @php
            $actions = ['view', 'create', 'edit', 'delete', 'manage'];

            // Group all permissions by their leading action word
            $groupedByAction = collect($actions)->mapWithKeys(fn($a) => [$a => collect()]);
            $groupedByAction['other'] = collect();

            foreach ($permissions as $perm) {
                $name    = strtolower($perm->name);
                $matched = false;
                foreach ($actions as $a) {
                    if (str_starts_with($name, $a)) {
                        $groupedByAction[$a]->push($perm);
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) $groupedByAction['other']->push($perm);
            }

            $groupedByAction = $groupedByAction->filter(fn($c) => $c->count() > 0);
        @endphp

        <div class="p-5 space-y-4">
            @foreach($groupedByAction as $action => $perms)
                <details class="border rounded-lg group dark:border-gray-700">
                    <summary class="flex items-center justify-between px-4 py-3 cursor-pointer select-none bg-gray-50 dark:bg-gray-700/40">
                        <p class="text-xs font-semibold tracking-wider text-gray-600 uppercase dark:text-gray-200">
                            <i class="mr-2 fa-brands fa-creative-commons-by"></i>{{ ucfirst($action) }} Permissions
                        </p>
                        <svg class="w-4 h-4 text-gray-400 transition-transform duration-200 group-open:rotate-180"
                             viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                        </svg>
                    </summary>

                    <div class="p-4 bg-white details-anim dark:bg-gray-800">
                        <div class="grid gap-2 md:grid-cols-2">
                            @foreach($perms as $perm)
                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                    <input type="checkbox"
                                           name="permissions[]"
                                           value="{{ $perm->name }}"
                                           {{ in_array($perm->name, $effectivePermissions) ? 'checked' : '' }}
                                           class="border-gray-300 rounded dark:border-gray-600">
                                    <span>{{ $perm->name }}</span>
                                </label>
                            @endforeach
                        </div>
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
