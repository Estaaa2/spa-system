@extends('layouts.app')

@section('title', 'Review Staff ID')

@section('content')

@php
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800 '
             . 'disabled:opacity-60 disabled:cursor-not-allowed';

    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'edit' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        'neutral' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
        'approve' => $btnBase . ' bg-emerald-700 text-white hover:bg-emerald-800',
        'remove' => $btnBase . ' bg-red-700 text-white hover:bg-red-800',
    ];

    $card = 'overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700';
    $cardHead = 'px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700';
    $inputClass = 'block w-full p-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-300 rounded-xl '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:bg-gray-700 dark:border-gray-600 '
                . 'dark:placeholder-gray-400 dark:text-white';
    $badgeClass = 'px-2.5 py-1 text-xs font-medium rounded-full';

    $isPending = $verification->isPending();
    $date = fn ($at) => $at?->format('M d, Y h:i A');

    $statusBadge = [
        'pending' => ['Pending review', 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'],
        'approved' => ['Approved', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
        'rejected' => ['Rejected', 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'],
        'withdrawn' => ['Withdrawn by staff', 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300'],
        'superseded' => ['Replaced', 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300'],
    ][$verification->status] ?? [ucfirst($verification->status), 'bg-slate-100 text-slate-700'];

    $panels = [
        'photo' => ['Profile photo', 'The face customers will see.', 'fa-user'],
        'id' => [$verification->idTypeLabel(), 'Front of the ID.', 'fa-id-card'],
        'selfie' => ['Selfie holding the ID', 'Should be the same person and the same ID.', 'fa-camera'],
    ];

    $checks = [
        'same_person' => 'The profile photo, the ID photo and the selfie show the same person.',
        'name_matches' => 'The name on the ID matches: ' . $verification->name_reviewed,
        'same_id' => 'The ID in the selfie is the same ID as in the ID photo.',
        'valid_id' => 'It is a ' . $verification->idTypeLabel() . ', it looks genuine, and it is not expired.',
    ];

    $fileLabels = ['id' => 'ID', 'selfie' => 'Selfie', 'photo' => 'Profile photo'];
    $canDecide = $isPending && ! $photoChanged && ! $nameChanged;
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">
    <div>
        <a href="{{ route('admin.identity-verifications.index') }}"
           class="inline-flex items-center gap-2 min-h-[44px] text-sm font-medium text-[#8B7355] hover:text-[#6F5430] dark:text-[#C4A97D] dark:hover:text-[#D8C09A]">
            <i class="text-xs fa-solid fa-arrow-left" aria-hidden="true"></i>
            Staff ID Reviews
        </a>

        <x-page-header
            :title="$user?->name ?? $verification->name_reviewed"
            :subtitle="($verification->spa?->name ?? 'No spa') . ($user?->staff?->branch ? ' · ' . $user->staff->branch->name : '') . ' · submitted ' . $date($verification->submitted_at)"
            :showClock="false"
        >
            <x-slot name="right">
                <span class="{{ $badgeClass }} {{ $statusBadge[1] }}">{{ $statusBadge[0] }}</span>
            </x-slot>
        </x-page-header>
    </div>

    {{-- ── Notices ── --}}
    @if ($accountClosed)
        <div class="flex items-start gap-3 p-4 border border-red-200 bg-red-50 rounded-2xl dark:bg-red-900/10 dark:border-red-800" role="alert">
            <i class="mt-0.5 fa-solid fa-user-slash text-red-600 dark:text-red-400" aria-hidden="true"></i>
            <p class="text-sm text-red-800 dark:text-red-300">This account was deleted. The request can't be approved; reject it to delete the files.</p>
        </div>
    @endif

    @if ($isPending && ($photoChanged || $nameChanged))
        <div class="flex items-start gap-3 p-4 border border-amber-200 bg-amber-50 rounded-2xl dark:bg-amber-900/10 dark:border-amber-800" role="alert">
            <i class="mt-0.5 fa-solid fa-triangle-exclamation text-amber-600 dark:text-amber-400" aria-hidden="true"></i>
            <p class="text-sm text-amber-800 dark:text-amber-300">
                The staff member's {{ $photoChanged ? 'profile photo' : 'name' }} changed after they submitted, so this request no longer matches their account.
                It will close automatically; they need to submit again.
            </p>
        </div>
    @endif

    @if ($isPending && $canDecide)
        <div class="flex items-start gap-3 p-4 border border-blue-200 bg-blue-50 rounded-2xl dark:bg-blue-900/10 dark:border-blue-800">
            <i class="mt-0.5 fa-solid fa-circle-info text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
            <p class="text-sm text-blue-800 dark:text-blue-300">
                Click a photo to enlarge it. When you approve or reject, the ID and selfie are <strong>deleted permanently</strong> and can't be opened again.
                Only the result, the ID type, your name and the date are kept.
            </p>
        </div>
    @endif

    {{-- ── The three images ── --}}
    <section aria-labelledby="imagesHeading" class="{{ $card }}">
        <div class="{{ $cardHead }}">
            <h2 id="imagesHeading" class="text-base font-semibold text-gray-900 dark:text-white">Compare</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Profile photo, ID and selfie, side by side.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 p-4 sm:p-6 md:grid-cols-3">
            @foreach ($panels as $key => [$label, $hint, $icon])
                <figure class="flex flex-col">
                    @if ($files[$key])
                        <button type="button" data-lightbox="{{ $key }}"
                                class="relative block overflow-hidden bg-gray-100 border border-gray-200 group rounded-2xl aspect-[4/5] dark:bg-gray-900 dark:border-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355]">
                            <img src="{{ $files[$key] }}" alt="{{ $label }} of {{ $verification->name_reviewed }}"
                                 data-lightbox-src="{{ $key }}" data-lightbox-label="{{ $label }}"
                                 class="object-contain w-full h-full" referrerpolicy="no-referrer">
                            <span class="absolute inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-white rounded-lg bottom-2 right-2 bg-black/60">
                                <i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i> Enlarge
                            </span>
                        </button>
                    @else
                        <div class="flex flex-col items-center justify-center gap-2 p-4 text-center bg-gray-50 border border-gray-300 border-dashed rounded-2xl aspect-[4/5] dark:bg-gray-900/40 dark:border-gray-600">
                            <i class="text-3xl text-gray-300 fa-solid {{ $icon }} dark:text-gray-600" aria-hidden="true"></i>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                @if ($key !== 'photo' && $verification->files_purged_at)
                                    Deleted {{ $date($verification->files_purged_at) }}
                                @elseif ($key === 'photo')
                                    This photo was replaced.
                                @else
                                    File not found.
                                @endif
                            </p>
                        </div>
                    @endif
                    <figcaption class="mt-2">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $label }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- ── Decision / result ── --}}
        <div class="space-y-6 lg:col-span-2">
            @if ($isPending)
                <section aria-labelledby="decisionHeading" class="{{ $card }}">
                    <div class="{{ $cardHead }}">
                        <h2 id="decisionHeading" class="text-base font-semibold text-gray-900 dark:text-white">Decision</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">The staff member is emailed the result.</p>
                    </div>

                    <div class="grid grid-cols-1 divide-y divide-gray-200 md:grid-cols-2 md:divide-y-0 md:divide-x dark:divide-gray-700">
                        {{-- Approve --}}
                        <form id="approveForm" method="POST" action="{{ route('admin.identity-verifications.approve', $verification) }}" class="p-4 space-y-4 sm:p-6">
                            @csrf
                            <fieldset @disabled(! $canDecide || $accountClosed)>
                                <legend class="text-sm font-semibold text-gray-900 dark:text-white">Approve: tick all four</legend>
                                <div class="mt-3 space-y-2">
                                    @foreach ($checks as $value => $text)
                                        <label class="flex items-start gap-3 p-3 border border-gray-200 cursor-pointer rounded-xl hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-900/40">
                                            <input type="checkbox" name="checks[]" value="{{ $value }}" data-approve-check
                                                   class="mt-0.5 w-5 h-5 border-gray-300 rounded text-emerald-700 focus:ring-emerald-600 dark:border-gray-600 dark:bg-gray-700">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $text }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('checks')
                                    <p class="mt-2 text-xs text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
                                @enderror
                                <button type="button" id="approveButton" disabled
                                        class="w-full mt-4 {{ $btn['approve'] }}">
                                    <i class="text-xs fa-solid fa-circle-check" aria-hidden="true"></i>
                                    Approve
                                </button>
                            </fieldset>
                        </form>

                        {{-- Reject --}}
                        <form id="rejectForm" method="POST" action="{{ route('admin.identity-verifications.reject', $verification) }}" class="p-4 space-y-4 sm:p-6">
                            @csrf
                            <fieldset>
                                <legend class="text-sm font-semibold text-gray-900 dark:text-white">Reject: tell them what to fix</legend>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Tap a common reason to add it, then edit it if needed. The staff member sees this text.</p>
                                <div class="flex flex-wrap gap-2 mt-3">
                                    @foreach ($quickReasons as $reason)
                                        <button type="button" data-quick-reason="{{ $reason }}"
                                                class="px-3 py-2 min-h-[44px] text-xs text-left text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                                            {{ \Illuminate\Support\Str::limit($reason, 48) }}
                                        </button>
                                    @endforeach
                                </div>
                                <label for="rejection_reason" class="block mt-4 mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Reason <span class="text-red-500">*</span>
                                </label>
                                <textarea id="rejection_reason" name="rejection_reason" rows="4" maxlength="1000" required
                                          class="{{ $inputClass }}"
                                          placeholder="Explain what needs to be corrected.">{{ old('rejection_reason') }}</textarea>
                                @if ($errors->reject->has('rejection_reason'))
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400" role="alert">{{ $errors->reject->first('rejection_reason') }}</p>
                                @endif
                                <button type="button" id="rejectButton"
                                        class="w-full mt-4 {{ $btn['remove'] }}">
                                    <i class="text-xs fa-solid fa-circle-xmark" aria-hidden="true"></i>
                                    Reject
                                </button>
                            </fieldset>
                        </form>
                    </div>
                </section>
            @else
                <section aria-labelledby="resultHeading" class="{{ $card }}">
                    <div class="{{ $cardHead }}">
                        <h2 id="resultHeading" class="text-base font-semibold text-gray-900 dark:text-white">Result</h2>
                    </div>
                    <dl class="grid grid-cols-1 gap-4 p-4 text-sm sm:p-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                            <dd class="mt-1"><span class="{{ $badgeClass }} {{ $statusBadge[1] }}">{{ $statusBadge[0] }}</span></dd>
                        </div>
                        @if ($verification->reviewed_at)
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Reviewed by</dt>
                                <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ $verification->reviewer?->name ?? 'Deleted admin' }} · {{ $date($verification->reviewed_at) }}</dd>
                            </div>
                        @endif
                        @if ($verification->consent_withdrawn_at)
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Consent withdrawn</dt>
                                <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ $date($verification->consent_withdrawn_at) }}</dd>
                            </div>
                        @endif
                        @if ($verification->superseded_reason)
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Replaced because</dt>
                                <dd class="mt-1 font-medium text-gray-900 dark:text-white">
                                    {{ $verification->superseded_reason === 'name_changed' ? 'The staff member\'s name changed.' : 'The staff member changed their profile photo.' }}
                                </dd>
                            </div>
                        @endif
                        @if ($verification->rejection_reason)
                            <div class="sm:col-span-2">
                                <dt class="text-gray-500 dark:text-gray-400">Reason given</dt>
                                <dd class="p-3 mt-1 text-gray-900 border border-red-100 bg-red-50 rounded-xl dark:bg-red-900/10 dark:border-red-900 dark:text-gray-100">{{ $verification->rejection_reason }}</dd>
                            </div>
                        @endif
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500 dark:text-gray-400">ID and selfie</dt>
                            <dd class="mt-1 font-medium text-gray-900 dark:text-white">
                                {{ $verification->files_purged_at ? 'Deleted ' . $date($verification->files_purged_at) : 'Not deleted yet (removed by the daily clean-up)' }}
                            </dd>
                        </div>
                    </dl>
                </section>
            @endif

            {{-- ── Earlier requests ── --}}
            @if ($history->isNotEmpty())
                <section aria-labelledby="historyHeading" class="{{ $card }}">
                    <div class="{{ $cardHead }}">
                        <h2 id="historyHeading" class="text-base font-semibold text-gray-900 dark:text-white">Other requests from this staff member</h2>
                    </div>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($history as $past)
                            <li class="flex flex-col gap-1 px-4 py-3 text-sm sm:px-6 sm:flex-row sm:items-center sm:justify-between">
                                <span class="text-gray-700 dark:text-gray-300">
                                    {{ $date($past->submitted_at) }} · {{ $past->idTypeLabel() }}
                                    @if ($past->rejection_reason)
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">“{{ \Illuminate\Support\Str::limit($past->rejection_reason, 90) }}”</span>
                                    @endif
                                </span>
                                <a href="{{ route('admin.identity-verifications.show', $past) }}"
                                   class="text-sm font-medium text-[#8B7355] hover:text-[#6F5430] dark:text-[#C4A97D] dark:hover:text-[#D8C09A] whitespace-nowrap">
                                    {{ ucfirst($past->status === 'superseded' ? 'replaced' : $past->status) }} · View
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        {{-- ── Details + audit ── --}}
        <div class="space-y-6">
            <section aria-labelledby="detailsHeading" class="{{ $card }}">
                <div class="{{ $cardHead }}">
                    <h2 id="detailsHeading" class="text-base font-semibold text-gray-900 dark:text-white">Details</h2>
                </div>
                <dl class="p-4 space-y-3 text-sm sm:p-6">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Name to match on the ID</dt>
                        <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">{{ $verification->name_reviewed }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Email</dt>
                        <dd class="mt-0.5 font-medium text-gray-900 break-all dark:text-white">{{ $user?->email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Role</dt>
                        <dd class="mt-0.5 font-medium text-gray-900 dark:text-white">
                            {{ ucwords(str_replace('_', ' ', $user?->getRoleNames()->first() ?? '—')) }}
                            @if ($user?->staff?->performs_treatments && ! $user->hasRole('therapist'))
                                <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">Marked "Performs treatments" by the spa</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">ID type chosen</dt>
                        <dd class="mt-0.5 font-medium text-gray-900 dark:text-white">{{ $verification->idTypeLabel() }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Consent</dt>
                        <dd class="mt-0.5 font-medium text-gray-900 dark:text-white">
                            {{ $date($verification->consent_given_at) }}
                            <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">Notice version {{ $verification->consent_version }}, via {{ $verification->consent_channel === 'api' ? 'mobile app' : 'website' }}</span>
                        </dd>
                    </div>
                </dl>
            </section>

            <section aria-labelledby="auditHeading" class="{{ $card }}">
                <div class="{{ $cardHead }}">
                    <h2 id="auditHeading" class="text-base font-semibold text-gray-900 dark:text-white">Who opened the files</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Latest 20. Repeat views within 10 minutes count once.</p>
                </div>
                @if ($verification->fileViews->isEmpty())
                    <p class="px-4 py-6 text-sm text-gray-500 sm:px-6 dark:text-gray-400">Not opened yet.</p>
                @else
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($verification->fileViews as $view)
                            <li class="px-4 py-3 text-sm sm:px-6">
                                <span class="font-medium text-gray-900 dark:text-white">{{ $view->viewer?->name ?? 'Deleted admin' }}</span>
                                <span class="text-gray-500 dark:text-gray-400">opened the {{ strtolower($fileLabels[$view->file] ?? $view->file) }}</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $date($view->viewed_at) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════
     LIGHTBOX
     ═══════════════════════════════════════════════════ --}}
{{-- Same structure and look as the landing page's spa photo viewer
     (welcome.blade.php #photoLightbox): dark in both colour schemes, round
     buttons at the sides, caption and counter underneath, tap outside or swipe.
     One addition: Zoom, so the reviewer can read the small print on an ID. --}}
<div id="lightboxModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="lightboxTitle">
    <div class="absolute inset-0 bg-black/90"></div>

    <div class="lightbox-shell">
        <button type="button" data-close-modal="lightboxModal" class="lightbox-btn lightbox-btn-close" aria-label="Close photo viewer">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>

        <button type="button" id="lightboxZoom" class="lightbox-btn lightbox-btn-zoom" aria-pressed="false" aria-label="Zoom in">
            <i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i>
        </button>

        <button type="button" id="lightboxPrev" class="lightbox-btn lightbox-btn-prev" aria-label="Previous photo">
            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
        </button>

        <div class="lightbox-stage" id="lightboxStage">
            <div class="lightbox-scroller" id="lightboxScroller">
                <img id="lightboxImage" class="lightbox-image" alt="" referrerpolicy="no-referrer">
            </div>
            <div class="lightbox-footer">
                <p class="lightbox-caption" id="lightboxTitle"></p>
                <p class="lightbox-counter" id="lightboxCounter" aria-live="polite"></p>
            </div>
        </div>

        <button type="button" id="lightboxNext" class="lightbox-btn lightbox-btn-next" aria-label="Next photo">
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        </button>
    </div>
</div>

<style>
    /* Copied from welcome.blade.php's photo lightbox so both viewers match.
       Dark in both colour schemes by design, so no prefers-color-scheme branch. */
    .lightbox-shell {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        padding: 72px 16px 24px;
    }
    .lightbox-stage {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 100%;
        max-width: 1100px;
        max-height: 100%;
    }
    .lightbox-scroller {
        display: flex;
        align-items: center;
        justify-content: center;
        max-width: 100%;
        max-height: calc(100vh - 200px);
    }
    .lightbox-image {
        display: block;
        max-width: 100%;
        max-height: calc(100vh - 200px);
        object-fit: contain;
        border-radius: 12px;
        user-select: none;
        -webkit-user-drag: none;
        cursor: zoom-in;
    }
    @supports (height: 100dvh) {
        .lightbox-scroller,
        .lightbox-image { max-height: calc(100dvh - 200px); }
    }
    /* Zoomed: actual size inside a scrollable box. */
    .lightbox-scroller.is-zoomed {
        display: block;
        width: 100%;
        height: calc(100vh - 200px);
        overflow: auto;
        border-radius: 12px;
    }
    .lightbox-scroller.is-zoomed .lightbox-image {
        max-width: none;
        max-height: none;
        cursor: zoom-out;
    }
    .lightbox-footer {
        width: 100%;
        max-width: 720px;
        margin-top: 14px;
        text-align: center;
    }
    .lightbox-caption {
        font-size: 13px;
        font-weight: 500;
        line-height: 1.45;
        color: #fff;
    }
    .lightbox-counter {
        margin-top: 6px;
        font-size: 11px;
        letter-spacing: .04em;
        color: rgba(255, 255, 255, .7);
    }
    .lightbox-btn {
        position: absolute;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border: 0;
        border-radius: 9999px;
        background: rgba(255, 255, 255, .12);
        color: #fff;
        font-size: 16px;
        cursor: pointer;
        transition: background-color .15s ease;
    }
    .lightbox-btn:hover { background: rgba(255, 255, 255, .24); }
    .lightbox-btn:focus-visible { outline: 2px solid #C4A97D; outline-offset: 2px; }
    .lightbox-btn[hidden] { display: none; }
    .lightbox-btn-close { top: 16px; right: 16px; }
    .lightbox-btn-zoom { top: 16px; right: 72px; }
    .lightbox-btn-zoom[aria-pressed="true"] { background: rgba(255, 255, 255, .32); }
    .lightbox-btn-prev { left: 16px; top: 50%; transform: translateY(-50%); }
    .lightbox-btn-next { right: 16px; top: 50%; transform: translateY(-50%); }
    @media (max-width: 639px) {
        .lightbox-shell { padding: 64px 8px 20px; }
        .lightbox-btn-prev { left: 8px; }
        .lightbox-btn-next { right: 8px; }
        .lightbox-btn-prev,
        .lightbox-btn-next { background: rgba(0, 0, 0, .55); }
        .lightbox-btn-prev:hover,
        .lightbox-btn-next:hover { background: rgba(0, 0, 0, .7); }
    }
</style>

{{-- ═══════════════════════════════════════════════════
     CONFIRM DECISION
     ═══════════════════════════════════════════════════ --}}
@if ($isPending)
<div id="confirmModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="alertdialog" aria-modal="true" aria-labelledby="confirmModalTitle" aria-describedby="confirmModalBody"
             class="w-full max-w-md p-6 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <h2 id="confirmModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white"></h2>
            <p id="confirmModalBody" class="mt-2 text-sm text-gray-500 dark:text-gray-400"></p>
            <div class="flex flex-col-reverse gap-2 mt-6 sm:flex-row sm:justify-end">
                <button type="button" data-close-modal="confirmModal" class="w-full {{ $btn['neutral'] }} sm:w-auto">Go back</button>
                <button type="button" id="confirmModalGo" class="w-full sm:w-auto"></button>
            </div>
        </div>
    </div>
</div>
@endif

<script>
(function () {
    'use strict';

    const NAME = @json($user?->first_name ?? 'the staff member');
    const BTN_APPROVE = @json($btn['approve']);
    const BTN_REMOVE = @json($btn['remove']);

    // ════════════════════════════════════════════════════════════════
    // SHARED MODAL BEHAVIOUR (same as admin/registered-spas)
    // ════════════════════════════════════════════════════════════════
    const MODAL_IDS = ['confirmModal', 'lightboxModal'];
    const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    const el = id => document.getElementById(id);
    let lastFocused = null;

    function topmostOpenModal() {
        for (let i = MODAL_IDS.length - 1; i >= 0; i--) {
            const m = el(MODAL_IDS[i]);
            if (m && !m.classList.contains('hidden')) return m;
        }
        return null;
    }

    function openModal(id, focusEl) {
        const m = el(id);
        if (!m) return;
        lastFocused = document.activeElement;
        m.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        (focusEl || m.querySelector(FOCUSABLE))?.focus();
    }

    function closeModal(id) {
        const m = el(id);
        if (!m) return;
        m.classList.add('hidden');
        if (!topmostOpenModal()) document.body.classList.remove('overflow-hidden');
        if (lastFocused && document.contains(lastFocused)) lastFocused.focus();
        lastFocused = null;
    }

    document.querySelectorAll('[data-close-modal]').forEach(b =>
        b.addEventListener('click', () => closeModal(b.dataset.closeModal)));

    el('confirmModal')?.addEventListener('click', e => { if (e.target === el('confirmModal')) closeModal('confirmModal'); });

    document.addEventListener('keydown', function (e) {
        const modal = topmostOpenModal();
        if (!modal) return;

        if (e.key === 'Escape') { e.preventDefault(); closeModal(modal.id); return; }

        if (modal.id === 'lightboxModal') {
            if (e.key === 'ArrowRight') { e.preventDefault(); showImage(current + 1); return; }
            if (e.key === 'ArrowLeft') { e.preventDefault(); showImage(current - 1); return; }
            if (e.key === 'z' || e.key === 'Z') { e.preventDefault(); toggleZoom(); return; }
        }
        if (e.key !== 'Tab') return;

        const items = Array.from(modal.querySelectorAll(FOCUSABLE)).filter(x => x.offsetParent !== null);
        if (!items.length) return;
        const first = items[0], last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });

    // ════════════════════════════════════════════════════════════════
    // LIGHTBOX (reuses the loaded <img>, so no extra download)
    // ════════════════════════════════════════════════════════════════
    const sources = Array.from(document.querySelectorAll('[data-lightbox-src]'));
    const lightbox = el('lightboxModal');
    const lbImage = el('lightboxImage');
    const lbScroller = el('lightboxScroller');
    const lbZoom = el('lightboxZoom');
    let current = 0;
    let zoomed = false;

    function setZoom(on) {
        zoomed = on;
        lbScroller.classList.toggle('is-zoomed', on);
        // Actual size, or at least ~1.75x the box for small images.
        lbImage.style.width = on ? Math.round(Math.max(lbImage.naturalWidth, lbScroller.clientWidth * 1.75)) + 'px' : '';
        lbZoom.setAttribute('aria-pressed', on ? 'true' : 'false');
        lbZoom.setAttribute('aria-label', on ? 'Fit to screen' : 'Zoom in');
        lbZoom.querySelector('i').className = 'fa-solid ' + (on ? 'fa-magnifying-glass-minus' : 'fa-magnifying-glass-plus');
    }

    function toggleZoom() { setZoom(!zoomed); }

    function showImage(index) {
        if (!sources.length) return;
        current = (index + sources.length) % sources.length;
        const src = sources[current];
        lbImage.src = src.currentSrc || src.src;
        lbImage.alt = src.alt;
        el('lightboxTitle').textContent = src.dataset.lightboxLabel;
        el('lightboxCounter').textContent = (current + 1) + ' / ' + sources.length;
        setZoom(false);
    }

    document.querySelectorAll('[data-lightbox]').forEach(btn => {
        btn.addEventListener('click', () => {
            const index = sources.findIndex(s => s.dataset.lightboxSrc === btn.dataset.lightbox);
            showImage(index < 0 ? 0 : index);
            openModal('lightboxModal', lightbox.querySelector('.lightbox-btn-close'));
        });
    });
    lbImage?.addEventListener('click', toggleZoom);
    lbZoom?.addEventListener('click', toggleZoom);
    el('lightboxPrev')?.addEventListener('click', () => showImage(current - 1));
    el('lightboxNext')?.addEventListener('click', () => showImage(current + 1));
    if (sources.length < 2) {
        el('lightboxPrev').hidden = true;
        el('lightboxNext').hidden = true;
    }

    // Tap outside the photo closes it, like the landing page viewer.
    lightbox?.addEventListener('click', e => {
        if (e.target.closest('.lightbox-image, .lightbox-btn, .lightbox-scroller.is-zoomed')) return;
        closeModal('lightboxModal');
    });

    // Horizontal swipe (phones, Median). Not while zoomed: then a swipe pans.
    let touchX = 0, touchY = 0, tracking = false;
    el('lightboxStage')?.addEventListener('touchstart', e => {
        tracking = e.touches.length === 1 && !zoomed;
        if (!tracking) return;
        touchX = e.touches[0].clientX;
        touchY = e.touches[0].clientY;
    }, { passive: true });
    el('lightboxStage')?.addEventListener('touchend', e => {
        if (!tracking) return;
        tracking = false;
        const dx = e.changedTouches[0].clientX - touchX;
        const dy = e.changedTouches[0].clientY - touchY;
        if (Math.abs(dx) < 50 || Math.abs(dx) <= Math.abs(dy)) return;
        showImage(current + (dx < 0 ? 1 : -1));
    }, { passive: true });

    // ════════════════════════════════════════════════════════════════
    // DECISION FORMS
    // ════════════════════════════════════════════════════════════════
    const approveForm = el('approveForm');
    const rejectForm = el('rejectForm');
    const approveButton = el('approveButton');
    const checks = Array.from(document.querySelectorAll('[data-approve-check]'));
    const reason = el('rejection_reason');
    let pendingForm = null;

    function syncApprove() {
        if (approveButton) approveButton.disabled = approveButton.closest('fieldset')?.disabled || !checks.every(c => c.checked);
    }
    checks.forEach(c => c.addEventListener('change', syncApprove));
    syncApprove();

    document.querySelectorAll('[data-quick-reason]').forEach(btn => {
        btn.addEventListener('click', () => {
            const text = btn.dataset.quickReason;
            if (reason.value.includes(text)) return;
            reason.value = reason.value.trim() ? reason.value.trim() + ' ' + text : text;
            reason.focus();
        });
    });

    function confirmDecision(form, title, body, label, classes) {
        pendingForm = form;
        el('confirmModalTitle').textContent = title;
        el('confirmModalBody').textContent = body;
        const go = el('confirmModalGo');
        go.textContent = label;
        go.className = 'w-full sm:w-auto ' + classes;
        openModal('confirmModal', go);
    }

    approveButton?.addEventListener('click', () => {
        if (!checks.every(c => c.checked)) return;
        confirmDecision(approveForm,
            'Approve ' + NAME + '?',
            'They will be able to receive online home-service bookings, and customers will see a verified badge. The ID and selfie are deleted permanently.',
            'Approve and delete files', BTN_APPROVE);
    });

    el('rejectButton')?.addEventListener('click', () => {
        if (reason.value.trim().length < 5) {
            reason.setCustomValidity('Write the reason the staff member will see (at least 5 characters).');
            reason.reportValidity();
            reason.setCustomValidity('');
            reason.focus();
            return;
        }
        confirmDecision(rejectForm,
            'Reject this request?',
            NAME + ' will be emailed your reason and can submit again. The ID and selfie are deleted permanently.',
            'Reject and delete files', BTN_REMOVE);
    });

    el('confirmModalGo')?.addEventListener('click', function () {
        if (!pendingForm || pendingForm.dataset.submitting === 'true') return;
        pendingForm.dataset.submitting = 'true';
        this.disabled = true;
        this.textContent = 'Saving…';
        pendingForm.submit();
    });
}());
</script>
@endsection
