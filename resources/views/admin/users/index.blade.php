@extends('layouts.app')

@section('content')
<div class="p-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white">Users</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Manage user roles</p>
        </div>

        <form method="GET" class="flex gap-2">
            <input name="q" value="{{ $q }}"
                   class="w-64 px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white"
                   placeholder="Search name or email">
            <button class="px-4 py-2 text-sm font-medium text-white bg-[#8B7355] rounded-lg hover:opacity-90">
                Search
            </button>
        </form>
    </div>

    @if(session('success'))
        <div class="p-3 mb-4 text-sm text-green-800 bg-green-100 border border-green-200 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="overflow-hidden bg-white border rounded-lg dark:bg-gray-800 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/30">
                <tr class="text-left text-gray-600 dark:text-gray-300">
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Current Role</th>
                    <th class="px-4 py-3">Change Role</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    @php
                        $currentRole = $user->roles->first()?->name ?? 'none';
                    @endphp
                    <tr class="border-t dark:border-gray-700">
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs bg-gray-100 rounded dark:bg-gray-700 dark:text-gray-200">
                                {{ $currentRole }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('users.updateRole', $user) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PUT')

                                <select name="role"
                                        class="px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white">
                                    @foreach($roles as $role)
                                        <option value="{{ $role->name }}" @selected($role->name === $currentRole)>
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </select>

                                <button class="px-3 py-2 text-xs font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                                    Update
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                            No users found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
</div>
@endsection
