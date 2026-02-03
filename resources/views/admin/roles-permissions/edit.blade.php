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

    @if($errors->any())
        <div class="p-3 mb-4 text-sm text-red-800 bg-red-100 border border-red-200 rounded-lg">
            Please fix the errors and try again.
        </div>
    @endif

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
@endsection
