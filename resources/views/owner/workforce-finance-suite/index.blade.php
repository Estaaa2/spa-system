@extends('layouts.app')

@section('content')
<div class="max-w-5xl p-6 mx-auto">
    <x-page-header
        title="Workforce & Finance Suite"
        subtitle="Enable advanced workforce and finance modules for your spa branches."
    />

    @if (session('success'))
        <div class="p-4 mt-6 text-sm text-green-800 border border-green-200 rounded-2xl bg-green-50 dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('success') }}
        </div>
    @endif

    @if (($spa->business_tier ?? null) !== 'professional')
        <div class="p-6 mt-6 border border-yellow-200 shadow-sm rounded-2xl bg-yellow-50 dark:bg-yellow-900/10 dark:border-yellow-800">
            <div class="flex items-start gap-3">
                <div class="mt-1 text-yellow-600 dark:text-yellow-400">
                    <i class="fa-solid fa-lock"></i>
                </div>

                <div>
                    <h2 class="text-lg font-semibold text-yellow-900 dark:text-yellow-200">
                        Available on Professional only
                    </h2>

                    <p class="mt-1 text-sm text-yellow-800 dark:text-yellow-300">
                        Your spa is currently on the
                        <span class="font-semibold">{{ ucfirst($spa->business_tier ?? 'basic') }}</span>
                        plan. Upgrade to
                        <span class="font-semibold">Professional</span>
                        to enable the Workforce &amp; Finance Suite per branch.
                    </p>

                    @if (Route::has('owner.subscription.index'))
                        <div class="mt-4">
                            <a href="{{ route('owner.subscription.index') }}"
                               class="inline-flex items-center px-4 py-2 text-sm font-medium text-white rounded-xl bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:opacity-90">
                                <i class="mr-2 fa-solid fa-credit-card"></i>
                                Go to Subscription &amp; Billing
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('owner.workforce-finance-suite.update') }}" class="mt-6 space-y-6">
            @csrf
            @method('PUT')

            <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="px-6 py-4 border-b dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Suite Overview
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Enable this suite for branches that should access advanced workforce and finance tools.
                    </p>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="p-4 border rounded-xl dark:border-gray-700">
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200">People</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Staff Accounts, Hiring, Applicants, Interviews
                            </p>
                        </div>

                        <div class="p-4 border rounded-xl dark:border-gray-700">
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Attendance &amp; Leave</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Attendance tracking and leave workflows
                            </p>
                        </div>

                        <div class="p-4 border rounded-xl dark:border-gray-700">
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Finance</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Payroll now, revenue and billing later
                            </p>
                        </div>

                        <div class="p-4 border rounded-xl dark:border-gray-700">
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Branch-based</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Turn the suite on only where needed
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="px-6 py-4 border-b dark:border-gray-700">
                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Branch Access
                            </h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Choose which branches can use the Workforce &amp; Finance Suite.
                            </p>
                        </div>

                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $branches->count() }} {{ \Illuminate\Support\Str::plural('branch', $branches->count()) }}
                        </div>
                    </div>
                </div>

                <div class="p-6 space-y-4">
                    @forelse ($branches as $branch)
                        <div class="flex flex-col gap-4 p-4 border rounded-xl dark:border-gray-700 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                        {{ $branch->name }}
                                        @if(!empty($branch->location))
                                            <span class="font-normal text-gray-500 dark:text-gray-400">
                                                — {{ $branch->location }}
                                            </span>
                                        @endif
                                    </h3>

                                    @if($branch->is_main)
                                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-[#8B7355]/10 text-[#6F5430] border border-[#8B7355]/20 dark:text-[#D2A85B]">
                                            Main Branch
                                        </span>
                                    @endif

                                    @if($branch->has_workforce_finance_suite)
                                        <span class="inline-flex px-2 py-0.5 text-xs font-medium text-green-700 bg-green-100 border border-green-200 rounded-full dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
                                            Enabled
                                        </span>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 text-xs font-medium text-gray-700 bg-gray-100 border border-gray-200 rounded-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300">
                                            Disabled
                                        </span>
                                    @endif
                                </div>

                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Enable advanced workforce and finance modules for this branch.
                                </p>
                            </div>

                            <div class="flex items-start gap-3 lg:min-w-[260px] lg:justify-end">
                                <input type="hidden" name="branches[{{ $branch->id }}]" value="0">

                                <label class="inline-flex items-start gap-3 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        name="branches[{{ $branch->id }}]"
                                        value="1"
                                        {{ old("branches.{$branch->id}", $branch->has_workforce_finance_suite) ? 'checked' : '' }}
                                        class="w-4 h-4 mt-1 text-[#8B7355] border-gray-300 rounded focus:ring-[#8B7355] dark:bg-gray-700 dark:border-gray-600"
                                    >
                                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                        Enable for this branch
                                    </span>
                                </label>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-sm text-center text-gray-500 border rounded-xl dark:border-gray-700 dark:text-gray-400">
                            No branches found for this spa.
                        </div>
                    @endforelse
                </div>

                <div class="flex justify-end gap-2 px-6 py-4 border-t dark:border-gray-700">
                    <a href="{{ route('dashboard') }}"
                       class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                        Cancel
                    </a>

                    <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white rounded-xl bg-gradient-to-r from-[#8B7355] to-[#6F5430] hover:opacity-90">
                        Save Changes
                    </button>
                </div>
            </div>
        </form>
    @endif
</div>
@endsection
