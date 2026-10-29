@extends('layouts.app')

@section('title', 'Staff ID Reviews')

@section('content')

@php
    // Same tokens as admin/registered-spas/index.blade.php.
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';

    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'edit' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
    ];

    $inputClass = 'block w-full p-2.5 min-h-[44px] text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 '
                . 'dark:placeholder-gray-400 dark:text-white';

    $tabBase = 'flex items-center justify-center flex-1 gap-2 min-h-[44px] px-3 text-sm font-medium whitespace-nowrap transition rounded-xl';
    $tabActive = $tabBase . ' text-white shadow-sm bg-gradient-to-r from-[#7A6348] to-[#6F5430]';
    $tabInactive = $tabBase . ' text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700';

    $thClass = 'px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400';
    $badgeClass = 'px-2.5 py-1 text-xs font-medium rounded-full whitespace-nowrap';

    $tabs = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'closed' => 'Closed',
    ];

    $statusBadges = [
        'pending' => ['Pending', 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'],
        'approved' => ['Approved', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
        'rejected' => ['Rejected', 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'],
        'withdrawn' => ['Withdrawn', 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300'],
        'superseded' => ['Replaced', 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300'],
    ];

    $emptyText = [
        'pending' => 'No requests are waiting for review.',
        'approved' => 'No approved requests yet.',
        'rejected' => 'No rejected requests.',
        'closed' => 'No withdrawn or replaced requests.',
    ];

    $isPendingTab = $tab === 'pending';
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">
    <x-page-header
        title="Staff ID Reviews"
        subtitle="Check that each staff member's profile photo, government ID and selfie show the same person."
    />

    <nav aria-label="Request status"
        class="flex gap-1 p-1.5 overflow-x-auto bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        @foreach ($tabs as $value => $label)
            @php $isCurrent = $value === $tab; @endphp
            <a href="{{ route('admin.identity-verifications.index', array_filter(['tab' => $value, 'q' => $q, 'spa' => $spaId])) }}"
                @if ($isCurrent) aria-current="page" @endif
                class="{{ $isCurrent ? $tabActive : $tabInactive }}">
                {{ $label }}
                <span class="text-xs opacity-75">{{ $counts[$value] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    @if ($isPendingTab && ($counts['pending'] ?? 0) > 0)
        <div class="flex items-start gap-3 p-4 border border-blue-200 bg-blue-50 rounded-2xl dark:bg-blue-900/10 dark:border-blue-800">
            <i class="mt-0.5 fa-solid fa-circle-info text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
            <p class="text-sm text-blue-800 dark:text-blue-300">
                Oldest requests are listed first. The ID and selfie are deleted as soon as you approve or reject, so decide in one sitting.
                Every time you open them, it is recorded.
            </p>
        </div>
    @endif

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-col gap-3 px-4 py-4 border-b border-gray-200 lg:flex-row lg:items-center lg:justify-between sm:px-6 dark:border-gray-700">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ $tabs[$tab] }} Requests</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @switch($tab)
                        @case('pending') Waiting for your decision. @break
                        @case('approved') Approved requests. Their ID and selfie were deleted after approval. @break
                        @case('rejected') Rejected requests and the reasons given. @break
                        @default Withdrawn by the staff member, or replaced after a photo or name change.
                    @endswitch
                </p>
            </div>

            <form method="GET" action="{{ route('admin.identity-verifications.index') }}" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <input type="hidden" name="tab" value="{{ $tab }}">

                <label for="spaFilter" class="sr-only">Filter by spa</label>
                <select id="spaFilter" name="spa" class="{{ $inputClass }} sm:w-56">
                    <option value="">All spas</option>
                    @foreach ($spas as $spaOption)
                        <option value="{{ $spaOption->id }}" @selected($spaId === $spaOption->id)>{{ $spaOption->name }}</option>
                    @endforeach
                </select>

                <input type="search" name="q" value="{{ $q }}"
                    aria-label="Search staff name, email or spa"
                    placeholder="Search staff or spa"
                    class="{{ $inputClass }} sm:w-56">

                <button type="submit" class="{{ $btn['primary'] }}">Search</button>

                @if ($q !== '' || $spaId)
                    <a href="{{ route('admin.identity-verifications.index', ['tab' => $tab]) }}" class="{{ $btn['edit'] }}">Clear</a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="{{ $thClass }}">Staff member</th>
                        <th class="{{ $thClass }}">Spa / Branch</th>
                        <th class="{{ $thClass }}">ID type</th>
                        <th class="{{ $thClass }}">Submitted</th>
                        @unless ($isPendingTab)
                            <th class="{{ $thClass }}">Result</th>
                        @endunless
                        <th class="{{ $thClass }}"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    @forelse ($verifications as $row)
                        @php
                            $member = $row->user;
                            [$badgeLabel, $badgeColors] = $statusBadges[$row->status] ?? [ucfirst($row->status), $statusBadges['withdrawn'][1]];
                        @endphp
                        <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <x-avatar :user="$member" size="md" />
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $member?->name ?? $row->name_reviewed }}
                                            @if ($member?->trashed())
                                                <span class="ml-1 {{ $badgeClass }} bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">Account deleted</span>
                                            @endif
                                        </p>
                                        <p class="text-xs text-gray-500 truncate dark:text-gray-400">{{ $member?->email }}</p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $row->spa?->name ?? '—' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $member?->staff?->branch?->name }}</p>
                            </td>

                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ $row->idTypeLabel() }}
                            </td>

                            <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap dark:text-gray-400">
                                {{ $row->submitted_at?->format('M d, Y') }}
                                <span class="block text-xs">{{ $row->submitted_at?->format('h:i A') }}</span>
                            </td>

                            @unless ($isPendingTab)
                                <td class="px-6 py-4">
                                    <span class="{{ $badgeClass }} {{ $badgeColors }}">{{ $badgeLabel }}</span>
                                    @if ($row->reviewed_at)
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $row->reviewer?->name ?? 'Admin' }} · {{ $row->reviewed_at->format('M d, Y') }}
                                        </p>
                                    @elseif ($row->consent_withdrawn_at)
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Withdrawn {{ $row->consent_withdrawn_at->format('M d, Y') }}</p>
                                    @endif
                                </td>
                            @endunless

                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('admin.identity-verifications.show', $row) }}"
                                   class="{{ $isPendingTab ? $btn['primary'] : $btn['edit'] }}">
                                    <i class="text-xs fa-solid {{ $isPendingTab ? 'fa-user-check' : 'fa-eye' }}" aria-hidden="true"></i>
                                    {{ $isPendingTab ? 'Review' : 'View' }}
                                    <span class="sr-only">{{ $member?->name ?? $row->name_reviewed }}</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isPendingTab ? 5 : 6 }}" class="px-6 py-12 text-sm text-center text-gray-500 dark:text-gray-400">
                                {{ ($q !== '' || $spaId) ? 'No requests match your search.' : $emptyText[$tab] }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($verifications->hasPages())
            <div class="px-4 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
                {{ $verifications->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
