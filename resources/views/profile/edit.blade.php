@extends('layouts.app')

@section('title', 'Profile')
@section('content')
<div class="p-6 mx-auto space-y-6 max-w-7xl">
    <x-page-header
        title="Profile"
        subtitle="Manage your account information and security settings."
    />

    @php $mustChangePassword = $user->password_reset_required; @endphp

    {{-- ================= FIRST-LOGIN / TEMP PASSWORD NOTICE ================= --}}
    @if ($mustChangePassword)
        <div class="flex items-start gap-3 p-4 border border-amber-200 bg-amber-50 rounded-xl dark:bg-amber-900/10 dark:border-amber-800">
            <i class="fa-solid fa-triangle-exclamation text-amber-600 flex-shrink-0 mt-0.5"></i>
            <div>
                <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                    You're signing in with a temporary password
                </p>
                <p class="mt-0.5 text-sm text-amber-700 dark:text-amber-300">
                    For security, set your own password in the <span class="font-semibold">Change Password</span> section below.
                    The rest of the system unlocks automatically once you save it.
                </p>
            </div>
        </div>
    @endif

    @if (session('success'))
        <div class="flex items-center gap-3 p-4 border border-green-200 bg-green-50 rounded-xl dark:bg-green-900/10 dark:border-green-800">
            <i class="flex-shrink-0 text-green-600 fa-solid fa-circle-check"></i>
            <p class="text-sm text-green-800 dark:text-green-300">{{ session('success') }}</p>
        </div>
    @endif

    {{-- ================= ACCOUNT INFORMATION ================= --}}
    <div class="p-6 mb-5 bg-white border shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
        @include('profile.partials.update-profile-information-form')
    </div>

    {{-- ================= CHANGE PASSWORD ================= --}}
    <div id="password"
         class="p-6 bg-white border shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700
                {{ $mustChangePassword ? 'ring-2 ring-amber-400 dark:ring-amber-600' : '' }}">
        @include('profile.partials.update-password-form')
    </div>
</div>
@endsection
