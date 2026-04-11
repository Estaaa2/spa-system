@extends('layouts.app')

@section('content')
<div class="p-6 mx-auto space-y-6 max-w-7xl">
    <x-page-header
        title="Profile"
        subtitle="Manage your account information and security settings."
    />

    {{-- ================= ACCOUNT INFORMATION ================= --}}
    <div class="p-6 mb-5 bg-white border shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
        @include('profile.partials.update-profile-information-form')
    </div>

    {{-- ================= CHANGE PASSWORD ================= --}}
    <div id="password" class="p-6 bg-white border shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
        @include('profile.partials.update-password-form')
    </div>
</div>

@endsection
