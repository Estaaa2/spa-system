@extends('layouts.app')

@section('title', 'Pay Run')
@section('content')
@php
    // ── Tokens — same shapes as payroll/setup.blade.php (appointments.blade.php canon) ──
    $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
             . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
             . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';
    $btn = [
        'primary' => $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]',
        'edit'    => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        'neutral' => $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                   . 'dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
        'warn'    => $btnBase . ' border border-amber-300 bg-white text-amber-800 hover:bg-amber-50 '
                   . 'dark:border-amber-700 dark:bg-gray-800 dark:text-amber-300 dark:hover:bg-amber-900/20',
        'remove'  => $btnBase . ' bg-red-700 text-white hover:bg-red-800',
    ];
    $inputClass = 'w-full min-h-[44px] px-3 py-2 text-sm border border-gray-300 rounded-xl bg-white text-gray-900 '
                . 'focus:ring-[#8B7355] focus:border-[#8B7355] dark:border-gray-600 dark:bg-gray-700 dark:text-white';
    $labelClass = 'block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300';
    $card       = 'overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700';
    $cardHead   = 'px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700';
    $th         = 'px-4 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400';
    $thR        = $th . ' text-right';
    $td         = 'px-4 py-3 text-sm text-gray-700 dark:text-gray-300';
    $tdMoney    = 'px-4 py-3 text-sm text-right text-gray-900 whitespace-nowrap dark:text-white';
    $badgeBase  = 'inline-flex items-center gap-1 px-2.5 py-0.5 text-xs font-medium rounded-full whitespace-nowrap';

    $D          = \App\Services\Payroll\Display\PayrollDisplay::class;
    $statusMeta = $D::STATUS;
    $groupsMeta = $D::WARNING_GROUPS;
    $kindLabels = $D::KIND;
    $isDraft    = $run->isDraft();
    $isThirteenth = $run->run_type === \App\Models\PayrollRun::TYPE_THIRTEENTH_MONTH;
    $canAdjust  = $canEdit && $isDraft;
    $expandId   = (int) (session('expand') ?? (old('_modal') === 'adjustModal' ? old('_record') : 0));
    $warningCount = collect($warnings)->flatten(1)->count();
    $me = auth()->id();

    // ── Step actions: ONE source for button, modal copy and route (emitted to JS via @json) ──
    $actions = [];
    if ($canEdit) {
        if ($run->canRegenerate()) {
            $actions['regenerate'] = [
                'label' => 'Regenerate', 'icon' => 'fa-rotate', 'style' => 'edit', 'button' => 'primary',
                'title' => 'Regenerate this draft?',
                'body'  => 'Every computed line is rebuilt from current attendance, bookings, pay profiles and rates. Manual adjustment lines are kept. '
                         . ($isThirteenth ? 'The pay date stays ' . $header['pay_date'] . '.' : 'The dates follow the current pay schedule.'),
                'confirm' => 'Regenerate', 'url' => route('payroll.runs.regenerate', $run), 'method' => 'POST',
            ];
        }
        if ($run->canApprove()) {
            $actions['approve'] = [
                'label' => 'Approve', 'icon' => 'fa-circle-check', 'style' => 'primary', 'button' => 'primary',
                'title' => 'Approve this run?',
                'body'  => 'Approving freezes the payslips: no regenerating and no adjustments unless the run is sent back to draft. '
                         . ($warningCount > 0 ? "There are {$warningCount} warning(s) on this run — review them first. " : '')
                         . ((int) $run->generated_by === (int) $me ? 'You generated this run; approving it yourself will be shown on the run. ' : '')
                         . ($header['config_current'] ? '' : "Payroll rates changed since this draft was generated ({$header['config_version']} → {$header['current_config']}); regenerate before approving."),
                'confirm' => 'Approve Run', 'url' => route('payroll.runs.approve', $run), 'method' => 'POST',
            ];
        }
        if ($run->canDelete()) {
            $actions['delete'] = [
                'label' => 'Delete', 'icon' => 'fa-trash', 'style' => 'remove', 'button' => 'remove',
                'title' => 'Delete this draft run?',
                'body'  => 'The run, all of its payslips and every manual adjustment line are permanently deleted. Commission for its bookings becomes payable again in a new run.',
                'confirm' => 'Yes, Delete', 'url' => route('payroll.runs.destroy', $run), 'method' => 'DELETE',
            ];
        }
        if ($run->canSendBack()) {
            $actions['send_back'] = [
                'label' => 'Send Back to Draft', 'icon' => 'fa-arrow-rotate-left', 'style' => 'warn', 'button' => 'warn',
                'title' => 'Send this run back to draft?',
                'body'  => 'The approval is cleared. The run can then be regenerated or adjusted, and must be approved again before it can be finalized.',
                'confirm' => 'Send Back', 'url' => route('payroll.runs.send-back', $run), 'method' => 'POST',
            ];
        }
        if ($run->canFinalize()) {
            $actions['finalize'] = [
                'label' => 'Finalize', 'icon' => 'fa-lock', 'style' => 'primary', 'button' => 'primary',
                'title' => 'Finalize this run?',
                'body'  => 'Finalizing locks the register; later corrections go into a future run as adjustments. This cannot be undone.'
                         . ($run->cutoff_no === 1 ? ' Cutoff 2 of this month can be generated once this is finalized.' : '')
                         . ($isThirteenth ? '' : ' Its earnings then count toward the 13th-month basis.'),
                'confirm' => 'Finalize Run', 'url' => route('payroll.runs.finalize', $run), 'method' => 'POST',
            ];
        }
        if ($run->canRelease()) {
            $actions['release'] = [
                'label' => 'Release Payslips', 'icon' => 'fa-paper-plane', 'style' => 'primary', 'button' => 'primary',
                'title' => 'Release payslips to staff?',
                'body'  => 'Every staff member on this run will see their payslip under My Payslips. Release after pay has gone out. This cannot be undone.',
                'confirm' => 'Release', 'url' => route('payroll.runs.release', $run), 'method' => 'POST',
            ];
        }
    }

    $manualOptions = [
        'ADJ_EARNING'        => 'Taxable earning (e.g. SIL pay, pay correction)',
        'ADJ_EARNING_NONTAX' => 'Non-taxable earning (e.g. reimbursement, 13th-month balance)',
        'ADJ_DEDUCTION'      => 'Deduction (e.g. authorized recovery)',
    ];
    $ab = $errors->getBag('adjustment');
    $adjOld = old('_modal') === 'adjustModal';
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">

    <x-page-header :title="$header['title']" subtitle="Review the register, add adjustments on drafts, then approve, finalize and release.">
        <x-slot name="right">
            <a href="{{ route('payroll.index', ['year' => $run->period_start->format('Y')]) }}" class="{{ $btn['edit'] }}">
                <i class="text-xs fa-solid fa-arrow-left" aria-hidden="true"></i> All Runs
            </a>
        </x-slot>
    </x-page-header>

    {{-- ── Run header ──────────────────────────────────────────────── --}}
    <div class="{{ $card }}">
        <div class="flex flex-col gap-4 p-4 sm:p-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="{{ $badgeBase }} {{ $statusMeta[$run->status]['class'] }}">
                        <i class="fa-solid {{ $statusMeta[$run->status]['icon'] }} text-[10px]" aria-hidden="true"></i>{{ $statusMeta[$run->status]['label'] }}
                    </span>
                    @if($header['self_approved'])
                        <span class="{{ $badgeBase }} bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300" title="Allowed for small teams; the owner reviews as the compensating control">
                            <i class="fa-solid fa-user-check text-[10px]" aria-hidden="true"></i> Approved by the person who generated it
                        </span>
                    @endif
                </div>
                <dl class="grid grid-cols-2 text-sm gap-x-8 gap-y-2 sm:grid-cols-4">
                    <div><dt class="text-xs text-gray-500 dark:text-gray-400">Period</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $header['period'] }}</dd></div>
                    <div><dt class="text-xs text-gray-500 dark:text-gray-400">Cutoff</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $header['cutoff'] }}</dd></div>
                    <div><dt class="text-xs text-gray-500 dark:text-gray-400">Pay date</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $header['pay_date'] }}</dd></div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Rates version</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">
                            {{ $header['config_version'] }}
                            @if(! $header['config_current'] && $isDraft)
                                <span class="block text-xs font-normal text-amber-700 dark:text-amber-300">Current is {{ $header['current_config'] }} — regenerate</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            @if($actions)
                <div class="flex flex-wrap gap-2 lg:justify-end">
                    @foreach($actions as $key => $a)
                        <button type="button" class="{{ $btn[$a['style']] }}" onclick="openStepModal('{{ $key }}')">
                            <i class="text-xs fa-solid {{ $a['icon'] }}" aria-hidden="true"></i> {{ $a['label'] }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Who did each step --}}
        <ol class="grid grid-cols-2 border-t border-gray-200 sm:grid-cols-4 dark:border-gray-700" aria-label="Run history">
            @foreach($header['steps'] as $i => $s)
                <li class="px-4 py-3 sm:px-6 {{ $i > 0 ? 'border-l border-gray-200 dark:border-gray-700' : '' }} {{ $i === 2 ? 'max-sm:border-l-0 max-sm:border-t' : '' }} {{ $i === 3 ? 'max-sm:border-t' : '' }}">
                    <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide uppercase {{ $s['done'] ? 'text-gray-700 dark:text-gray-200' : 'text-gray-400 dark:text-gray-500' }}">
                        <i class="fa-solid {{ $s['done'] ? 'fa-circle-check text-emerald-600 dark:text-emerald-400' : 'fa-circle text-gray-300 dark:text-gray-600' }}" aria-hidden="true"></i>
                        {{ $s['label'] }}<span class="sr-only">{{ $s['done'] ? ' — done' : ' — not yet' }}</span>
                    </p>
                    @if($s['done'])
                        <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $s['by'] ?? '—' }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $s['at'] ?? '' }}</p>
                    @else
                        <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">—</p>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>

    {{-- ── Summary cards ───────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Payslips</p>
            <p class="mt-3 text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">{{ $register['count'] }}</p>
            <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">{{ $branchId ? ($register['branches'][$branchId] ?? '') : 'All branches' }}</span>
        </div>
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Gross Pay</p>
            <p class="mt-3 text-xl font-semibold text-gray-900 break-words sm:text-2xl dark:text-white">{{ $register['totals']['gross'] }}</p>
        </div>
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Net Pay</p>
            <p class="mt-3 text-xl font-semibold break-words sm:text-2xl text-emerald-700 dark:text-emerald-400">{{ $register['totals']['net'] }}</p>
        </div>
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Employer Contributions</p>
            <p class="mt-3 text-xl font-semibold text-gray-900 break-words sm:text-2xl dark:text-white">{{ $register['totals']['er'] }}</p>
            <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">On top of gross</span>
        </div>
    </div>

    {{-- ── Warnings ────────────────────────────────────────────────── --}}
    <section class="{{ $card }}" aria-labelledby="warningsTitle">
        <div class="flex flex-col gap-1 {{ $cardHead }} sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 id="warningsTitle" class="text-base font-semibold text-gray-900 dark:text-white">Warnings</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if($cached)
                        From the last generation, {{ $D::date($cached['generated_at'], 'M j, Y g:i A') }}@if($cached['adjusted_at']); updated after adjustments {{ $D::date($cached['adjusted_at'], 'M j, g:i A') }}@endif.
                    @else
                        Not available.
                    @endif
                </p>
            </div>
            @if($cached)
                <span class="text-sm text-gray-500 shrink-0 dark:text-gray-400">{{ $warningCount }} warning(s)</span>
            @endif
        </div>
        <div class="p-4 space-y-3 sm:p-6">
            @if(! $cached)
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    <i class="mr-1 text-gray-400 fa-solid fa-circle-info" aria-hidden="true"></i>
                    This run was generated before warnings were saved with each run.
                    @if($isDraft) Regenerate the draft to list them. @else The register below is unaffected. @endif
                </p>
            @elseif($warningCount === 0)
                <p class="text-sm text-gray-600 dark:text-gray-300"><i class="mr-1 fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400" aria-hidden="true"></i>No warnings.</p>
            @else
                @foreach($groupsMeta as $gKey => $g)
                    @php $list = $warnings[$gKey] ?? collect(); @endphp
                    @if($list->isNotEmpty())
                        <details class="border rounded-xl {{ $g['class'] }}" @if($gKey !== 'run') open @endif>
                            <summary class="flex items-center justify-between gap-3 px-4 min-h-[44px] cursor-pointer select-none {{ $g['text'] }}">
                                <span class="text-sm font-semibold"><i class="mr-1.5 fa-solid {{ $g['icon'] }}" aria-hidden="true"></i>{{ $g['label'] }}</span>
                                <span class="text-xs font-semibold">{{ $list->count() }}</span>
                            </summary>
                            <div class="px-4 pb-4">
                                <p class="mb-2 text-xs {{ $g['text'] }}">{{ $g['hint'] }}</p>
                                <ul class="space-y-2 text-sm text-gray-800 dark:text-gray-200">
                                    @foreach($list as $w)
                                        <li class="flex gap-2"><span aria-hidden="true" class="{{ $g['text'] }}">•</span><span>{{ $w['message'] }}</span></li>
                                    @endforeach
                                </ul>
                            </div>
                        </details>
                    @endif
                @endforeach
            @endif
        </div>
    </section>

    {{-- ── Register ───────────────────────────────────────────────── --}}
    <section class="{{ $card }}" aria-labelledby="registerTitle">
        <div class="flex flex-col gap-4 {{ $cardHead }} lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 id="registerTitle" class="text-base font-semibold text-gray-900 dark:text-white">Payroll Register</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">One row per staff member. Expand a row to see every line{{ $canAdjust ? ' and add adjustments' : '' }}.</p>
            </div>
            <div class="flex flex-wrap items-end gap-2">
            @if($register['count'] > 0)
                <a href="{{ route('payroll.runs.print', array_filter(['run' => $run->id, 'branch' => $branchId])) }}" class="{{ $btn['edit'] }}">
                    <i class="text-xs fa-solid fa-print" aria-hidden="true"></i> Print Payslips
                </a>
            @endif
            @if(count($register['branches']) > 1)
                <form method="GET" action="{{ route('payroll.runs.show', $run) }}" class="flex items-end gap-2" aria-label="Filter by home branch">
                    <div>
                        <label for="f_branch" class="{{ $labelClass }}">Home branch</label>
                        <select id="f_branch" name="branch" class="{{ $inputClass }} sm:w-48">
                            <option value="">All branches</option>
                            @foreach($register['branches'] as $id => $name)<option value="{{ $id }}" @selected($branchId === $id)>{{ $name }}</option>@endforeach
                        </select>
                    </div>
                    <button type="submit" class="{{ $btn['neutral'] }}"><i class="text-xs fa-solid fa-filter" aria-hidden="true"></i> Apply</button>
                </form>
            @endif
            </div>
        </div>

        <div class="md:overflow-x-auto">
            <table role="table" class="rt min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader" scope="col" class="{{ $th }}">Staff</th>
                        <th role="columnheader" scope="col" class="{{ $th }}">Home Branch</th>
                        <th role="columnheader" scope="col" class="{{ $thR }}">Days</th>
                        <th role="columnheader" scope="col" class="{{ $thR }}">Gross</th>
                        <th role="columnheader" scope="col" class="{{ $thR }}" title="SSS, PhilHealth and Pag-IBIG employee shares">Emp. Contrib.</th>
                        <th role="columnheader" scope="col" class="{{ $thR }}">WTAX</th>
                        <th role="columnheader" scope="col" class="{{ $thR }}">Other Ded.</th>
                        <th role="columnheader" scope="col" class="{{ $thR }}">Net</th>
                        <th role="columnheader" scope="col" class="{{ $thR }}" title="SSS, EC, PhilHealth and Pag-IBIG employer shares">Employer Contrib.</th>
                        <th role="columnheader" scope="col" class="{{ $th }}"><span class="sr-only">Details</span></th>
                    </tr>
                </thead>
                @forelse($register['rows'] as $row)
                    <tbody role="rowgroup" id="payslip-{{ $row['id'] }}" x-data="{ open: {{ $expandId === $row['id'] ? 'true' : 'false' }} }"
                           class="bg-white border-b border-gray-200 dark:bg-gray-800 dark:border-gray-700 scroll-mt-24">
                        <tr role="row" class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td role="cell" data-label="Staff" class="{{ $td }}">
                                <p class="font-medium text-gray-900 dark:text-white">{{ $row['name'] }}</p>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @if($row['is_mwe'])<span class="{{ $badgeBase }} bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300" title="Minimum wage earner for withholding tax (UNVERIFIED test — rates sheet §5)">MWE</span>@endif
                                    @if($row['manual'] > 0)<span class="{{ $badgeBase }} bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">{{ $row['manual'] }} adjustment(s)</span>@endif
                                    @if($row['short'])<span class="{{ $badgeBase }} bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300" title="Net pay reached ₱0 before every deduction was taken">Short deduction</span>@endif
                                    @if($row['orphan'])<span class="{{ $badgeBase }} bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300" title="Not eligible at the last generation; kept only for its manual lines">Manual lines only</span>@endif
                                </div>
                            </td>
                            <td role="cell" data-label="Home Branch" class="{{ $td }} whitespace-nowrap">{{ $row['branch'] }}</td>
                            <td role="cell" data-label="Days" class="{{ $tdMoney }}">{{ $row['days'] }}</td>
                            <td role="cell" data-label="Gross" class="{{ $tdMoney }} font-semibold">{{ $row['gross'] }}</td>
                            <td role="cell" data-label="Emp. Contrib." class="{{ $tdMoney }}">{{ $row['ee'] }}</td>
                            <td role="cell" data-label="WTAX" class="{{ $tdMoney }}">{{ $row['wtax'] }}</td>
                            <td role="cell" data-label="Other Ded." class="{{ $tdMoney }}">{{ $row['other'] }}</td>
                            <td role="cell" data-label="Net" class="{{ $tdMoney }} font-semibold text-emerald-700 dark:text-emerald-400">{{ $row['net'] }}</td>
                            <td role="cell" data-label="Employer Contrib." class="{{ $tdMoney }} text-gray-600 dark:text-gray-300">{{ $row['er'] }}</td>
                            <td role="cell" data-label="Details" class="px-4 py-3 rt-actions">
                                <button type="button" class="{{ $btn['edit'] }}" @click="open = !open"
                                        :aria-expanded="open ? 'true' : 'false'" aria-controls="lines-{{ $row['id'] }}">
                                    <i class="text-xs fa-solid" :class="open ? 'fa-chevron-up' : 'fa-chevron-down'" aria-hidden="true"></i>
                                    <span x-text="open ? 'Hide' : 'Lines'">Lines</span><span class="sr-only"> for {{ $row['name'] }}</span>
                                </button>
                            </td>
                        </tr>
                        <tr role="row" id="lines-{{ $row['id'] }}" x-show="open" x-cloak>
                            <td role="cell" colspan="10" class="px-4 pb-6 bg-gray-50 sm:px-6 dark:bg-gray-900/40 rt-detail">
                                <div class="flex flex-wrap items-center justify-between gap-2 pt-4 pb-3">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $row['name'] }} — payslip lines</p>
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('payroll.payslips.show', $row['id']) }}" class="{{ $btn['edit'] }}">
                                            <i class="text-xs fa-solid fa-file-invoice" aria-hidden="true"></i> Payslip<span class="sr-only"> of {{ $row['name'] }}</span>
                                        </a>
                                        @if($canAdjust)
                                            <button type="button" class="{{ $btn['primary'] }}"
                                                    data-adjust="{{ json_encode(['id' => $row['id'], 'name' => $row['name']]) }}" onclick="openAdjustModal(this)">
                                                <i class="text-xs fa-solid fa-plus" aria-hidden="true"></i> Add Adjustment<span class="sr-only"> for {{ $row['name'] }}</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                                    @foreach($kindLabels as $kind => $kindLabel)
                                        @php $lines = $row['groups'][$kind] ?? []; @endphp
                                        <div class="overflow-hidden bg-white border border-gray-200 rounded-xl dark:bg-gray-800 dark:border-gray-700 {{ $kind === 'employer_share' && $lines === [] ? 'hidden xl:block' : '' }}">
                                            <h3 class="px-4 py-2 text-xs font-semibold tracking-wide text-gray-500 uppercase border-b border-gray-200 dark:text-gray-400 dark:border-gray-700">{{ $kindLabel }}</h3>
                                            <ul class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                                @forelse($lines as $l)
                                                    <li class="flex items-start justify-between gap-3 px-4 py-2">
                                                        <div class="min-w-0 text-sm">
                                                            <p class="text-gray-900 dark:text-white">
                                                                {{ $l['label'] }}
                                                                @if($l['manual'])<span class="{{ $badgeBase }} ml-1 bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">Manual</span>@endif
                                                            </p>
                                                            @if($l['detail'] !== '')
                                                                <p class="text-xs {{ $l['short'] ? 'text-amber-700 dark:text-amber-300' : 'text-gray-500 dark:text-gray-400' }}">{{ $l['detail'] }}</p>
                                                            @endif
                                                            @if($l['booking'])
                                                                <p class="text-xs text-gray-600 dark:text-gray-300">
                                                                    <i class="fa-solid fa-receipt text-[10px] text-gray-400" aria-hidden="true"></i>
                                                                    Booking #{{ $l['booking']['id'] }}
                                                                    @if($l['booking']['missing'])
                                                                        · record not found
                                                                    @else
                                                                        · appointment {{ $l['booking']['date'] }}
                                                                        @if($l['booking']['service']) · {{ $l['booking']['service'] }} @endif
                                                                        @if($l['booking']['branch']) · {{ $l['booking']['branch'] }} @endif
                                                                        @if($l['booking']['customer']) · {{ $l['booking']['customer'] }} @endif
                                                                    @endif
                                                                </p>
                                                                @if($l['booking']['late'])
                                                                    <p class="text-xs text-blue-700 dark:text-blue-300">Earlier appointment — paid in this run because it became completed and fully paid after its own period.</p>
                                                                @endif
                                                            @endif
                                                            @if($l['branch'])
                                                                <p class="text-xs text-gray-500 dark:text-gray-400">Earned at {{ $l['branch'] }}</p>
                                                            @endif
                                                            @if($l['note'])
                                                                <p class="text-xs text-gray-500 break-words dark:text-gray-400">{{ $l['note'] }}</p>
                                                            @endif
                                                            @if($l['manual'] && $l['by'])
                                                                <p class="text-xs text-gray-400 dark:text-gray-500">Added by {{ $l['by'] }}</p>
                                                            @endif
                                                        </div>
                                                        <div class="flex items-start gap-1 shrink-0">
                                                            <span class="py-0.5 text-sm text-right text-gray-900 whitespace-nowrap dark:text-white">{{ $l['amount'] }}</span>
                                                            @if($canAdjust && $l['manual'])
                                                                <button type="button" aria-label="Remove adjustment {{ $l['label'] }}"
                                                                        data-line="{{ json_encode(['id' => $l['id'], 'label' => $l['label'], 'amount' => $l['amount'], 'name' => $row['name']]) }}" onclick="openRemoveLineModal(this)"
                                                                        class="inline-flex items-center justify-center -my-2 -mr-2 text-red-700 min-h-[44px] min-w-[44px] rounded-xl hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355]">
                                                                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                                                </button>
                                                            @endif
                                                        </div>
                                                    </li>
                                                @empty
                                                    <li class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">None</li>
                                                @endforelse
                                            </ul>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody role="rowgroup">
                        <tr role="row">
                            <td role="cell" colspan="10" class="px-6 py-12 text-center text-gray-500 rt-empty dark:text-gray-400">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="flex items-center justify-center w-12 h-12 mb-3 text-gray-400 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-500">
                                        <i class="text-lg fa-solid fa-user-group" aria-hidden="true"></i>
                                    </div>
                                    <p class="text-gray-600 dark:text-gray-400">No payslips{{ $branchId ? ' for this branch' : '' }}.</p>
                                    <p class="mt-1 text-sm">Check the warnings above for staff who were not paid.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @endforelse
                @if($register['count'] > 0)
                    <tfoot role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                        <tr role="row">
                            <th role="rowheader" scope="row" colspan="2" class="px-4 py-3 text-xs font-semibold text-right text-gray-600 uppercase rt-total dark:text-gray-300">Totals ({{ $register['count'] }})</th>
                            <td role="cell" data-label="Days" class="{{ $tdMoney }} font-semibold">{{ $isThirteenth ? '—' : $register['totals']['days'] }}</td>
                            <td role="cell" data-label="Gross" class="{{ $tdMoney }} font-bold">{{ $register['totals']['gross'] }}</td>
                            <td role="cell" data-label="Emp. Contrib." class="{{ $tdMoney }} font-bold">{{ $register['totals']['ee'] }}</td>
                            <td role="cell" data-label="WTAX" class="{{ $tdMoney }} font-bold">{{ $register['totals']['wtax'] }}</td>
                            <td role="cell" data-label="Other Ded." class="{{ $tdMoney }} font-bold">{{ $register['totals']['other'] }}</td>
                            <td role="cell" data-label="Net" class="{{ $tdMoney }} font-bold text-emerald-700 dark:text-emerald-400">{{ $register['totals']['net'] }}</td>
                            <td role="cell" data-label="Employer Contrib." class="{{ $tdMoney }} font-bold">{{ $register['totals']['er'] }}</td>
                            <td role="cell" class="rt-hide"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>

    {{-- ── Monthly contribution summary (cutoff 2 only) ─────────────── --}}
    @if($summary)
        @php $codes = [['SSS_EE', 'SSS EE'], ['SSS_ER', 'SSS ER'], ['SSS_EC', 'EC'], ['PHIC_EE', 'PhilHealth EE'], ['PHIC_ER', 'PhilHealth ER'], ['HDMF_EE', 'Pag-IBIG EE'], ['HDMF_ER', 'Pag-IBIG ER'], ['WTAX', 'WTAX']]; @endphp
        <section class="{{ $card }}" aria-labelledby="summaryTitle">
            <div class="{{ $cardHead }}">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 id="summaryTitle" class="text-base font-semibold text-gray-900 dark:text-white">Monthly Contribution Summary — {{ $summary['month_label'] }}</h2>
                    @if($summary['complete'])
                        <span class="{{ $badgeBase }} bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Complete</span>
                    @else
                        <span class="{{ $badgeBase }} bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">Incomplete — this run is not finalized yet</span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Totals to remit, from finalized and released runs only{{ $branchId ? ', for the selected home branch' : '' }}.
                    SSS, PhilHealth and Pag-IBIG are for the month the pay covers; WTAX is for the month it was withheld (runs paid in {{ $summary['month_label'] }}).
                </p>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    <i class="mr-1 fa-solid fa-circle-info" aria-hidden="true"></i>
                    Official agency file formats are not generated — use these figures when you file with SSS, PhilHealth, Pag-IBIG and BIR.
                </p>
                <div class="flex flex-wrap gap-x-6 gap-y-1 mt-2 text-xs text-gray-600 dark:text-gray-300">
                    <p>Contributions: @forelse($summary['contribution_runs'] as $r){{ $r['label'] }} ({{ $r['status'] }}{{ $r['included'] ? '' : ', not counted' }})@if(! $loop->last), @endif @empty none @endforelse</p>
                    <p>WTAX: @forelse($summary['wtax_runs'] as $r){{ $r['label'] }} ({{ $r['status'] }}{{ $r['included'] ? '' : ', not counted' }})@if(! $loop->last), @endif @empty none @endforelse</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th scope="col" class="{{ $th }}">Staff</th>
                            @foreach($codes as [$c, $l])<th scope="col" class="{{ $thR }} whitespace-nowrap">{{ $l }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        @forelse($summary['rows'] as $r)
                            <tr>
                                <th scope="row" class="{{ $td }} font-medium text-left text-gray-900 dark:text-white">{{ $r['name'] }}</th>
                                @foreach($codes as [$c, $l])
                                    <td class="{{ $tdMoney }}">
                                        {{ $r[$c] }}
                                        @if($r[$c.'_short'] ?? false)
                                            <span class="block text-xs text-amber-700 dark:text-amber-300" title="Net pay reached ₱0 — the employer advances the difference">withheld {{ $r[$c.'_withheld'] }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($codes) + 1 }}" class="px-6 py-8 text-sm text-center text-gray-500 dark:text-gray-400">Nothing to report yet — no finalized run for this month.</td></tr>
                        @endforelse
                    </tbody>
                    @if(count($summary['rows']) > 0)
                        <tfoot class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th scope="row" class="px-4 py-3 text-xs font-semibold text-left text-gray-600 uppercase dark:text-gray-300">Totals</th>
                                @foreach($codes as [$c, $l])<td class="{{ $tdMoney }} font-bold">{{ $summary['totals'][$c] }}</td>@endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>
    @endif
</div>

@if($actions)
{{-- ═══════════════════════════════════════════════════
     STEP CONFIRMATION MODAL (regenerate / approve / delete / send back / finalize / release)
     ═══════════════════════════════════════════════════ --}}
<div id="stepModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="alertdialog" aria-modal="true" aria-labelledby="stepModalTitle" aria-describedby="stepModalDesc"
             class="w-full max-w-md p-6 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <h2 id="stepModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white"></h2>
            <p id="stepModalDesc" class="mt-2 text-sm text-gray-600 dark:text-gray-300"></p>
            <form id="stepForm" method="POST" class="mt-4">
                @csrf
                <input type="hidden" name="_method" id="stepMethod" value="POST">
            </form>
            <div class="flex flex-col-reverse gap-2 mt-6 sm:flex-row sm:justify-end">
                <button type="button" onclick="closeModalById('stepModal')" class="w-full {{ $btn['neutral'] }} sm:w-auto">Cancel</button>
                <button type="submit" form="stepForm" id="stepConfirm" class="w-full sm:w-auto"></button>
            </div>
        </div>
    </div>
</div>
@endif

@if($canAdjust)
{{-- ═══════════════════════════════════════════════════
     ADD ADJUSTMENT MODAL
     ═══════════════════════════════════════════════════ --}}
<div id="adjustModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="adjustModalTitle" class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <form id="adjustForm" method="POST">
                @csrf
                <input type="hidden" name="_modal" value="adjustModal">
                <input type="hidden" name="_record" id="adj_record" value="">
                <div class="flex items-start justify-between gap-3 {{ $cardHead }}">
                    <div>
                        <h2 id="adjustModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Add Adjustment</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">For <span id="adj_name" class="font-medium text-gray-900 dark:text-white"></span>. The payslip is recomputed right away.</p>
                    </div>
                    <button type="button" onclick="closeModalById('adjustModal')" aria-label="Close dialog"
                            class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="px-4 py-6 space-y-4 sm:px-6">
                    <div>
                        <label for="adj_code" class="{{ $labelClass }}">Type</label>
                        <select id="adj_code" name="component_code" required class="{{ $inputClass }}">
                            @foreach($manualOptions as $code => $text)
                                @php $blocked = $isThirteenth && $code === 'ADJ_EARNING'; @endphp
                                <option value="{{ $code }}" @disabled($blocked) @selected($adjOld ? old('component_code') === $code : ($isThirteenth ? $code === 'ADJ_EARNING_NONTAX' : $code === 'ADJ_EARNING'))>
                                    {{ $text }}{{ $blocked ? ' — not on 13th-month runs' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @if($ab->has('component_code'))<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $ab->first('component_code') }}</p>@endif
                    </div>
                    <div>
                        <label for="adj_label" class="{{ $labelClass }}">Label on the payslip</label>
                        <input type="text" id="adj_label" name="label" required maxlength="100" class="{{ $inputClass }}"
                               placeholder="e.g. Service incentive leave — 2 days" value="{{ $adjOld ? old('label') : '' }}">
                        @if($ab->has('label'))<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $ab->first('label') }}</p>@endif
                    </div>
                    <div>
                        <label for="adj_amount" class="{{ $labelClass }}">Amount (₱)</label>
                        <input type="text" inputmode="decimal" id="adj_amount" name="amount" required maxlength="20" class="{{ $inputClass }}"
                               placeholder="e.g. 1500.00" value="{{ $adjOld ? old('amount') : '' }}" aria-describedby="adj_amount_help">
                        <p id="adj_amount_help" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Always a positive number — choose “Deduction” to take money off.</p>
                        @if($ab->has('amount'))<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $ab->first('amount') }}</p>@endif
                    </div>
                    <div>
                        <label for="adj_note" class="{{ $labelClass }}">Note <span class="font-normal text-gray-500">(optional, shown on the payslip)</span></label>
                        <textarea id="adj_note" name="note" rows="2" maxlength="255" class="{{ $inputClass }}"
                                  placeholder="Why this adjustment is made">{{ $adjOld ? old('note') : '' }}</textarea>
                        @if($ab->has('note'))<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $ab->first('note') }}</p>@endif
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Deductions other than government contributions and tax need the employee's written authorization (Labor Code Art. 113).
                        @unless($isThirteenth) A taxable earning also changes this cutoff's withholding tax{{ (int) $run->cutoff_no === 2 ? ' and the month\'s contributions' : '' }}. @endunless
                    </p>
                </div>
                <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeModalById('adjustModal')" class="w-full {{ $btn['neutral'] }} sm:w-auto">Cancel</button>
                        <button type="submit" class="w-full {{ $btn['primary'] }} sm:w-auto">Add Adjustment</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════
     REMOVE ADJUSTMENT MODAL
     ═══════════════════════════════════════════════════ --}}
<div id="removeLineModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="alertdialog" aria-modal="true" aria-labelledby="removeLineTitle" aria-describedby="removeLineDesc"
             class="w-full max-w-md p-6 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <h2 id="removeLineTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Remove Adjustment</h2>
            <p id="removeLineDesc" class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                Remove <span id="rl_label" class="font-medium text-gray-900 dark:text-white"></span> from <span id="rl_name"></span>'s payslip?
                The payslip is recomputed. A payslip that only held manual lines is removed with its last line.
            </p>
            <div class="flex flex-col-reverse gap-2 mt-6 sm:flex-row sm:justify-end">
                <button type="button" onclick="closeModalById('removeLineModal')" class="w-full {{ $btn['neutral'] }} sm:w-auto">Keep It</button>
                <form id="removeLineForm" method="POST" class="sm:w-auto">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full {{ $btn['remove'] }} sm:w-auto">Yes, Remove</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

<script>
(function () {
    'use strict';
@if($actions || $canAdjust)
    // ── Shared modal behaviour (same as payroll/setup.blade.php) ──
    const MODAL_IDS = ['stepModal', 'adjustModal', 'removeLineModal'];
    const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    let lastFocused = null;

    function topmostOpenModal() {
        for (let i = MODAL_IDS.length - 1; i >= 0; i--) {
            const el = document.getElementById(MODAL_IDS[i]);
            if (el && !el.classList.contains('hidden')) return el;
        }
        return null;
    }
    function openModal(id, focusSelector) {
        const el = document.getElementById(id);
        if (!el) return;
        lastFocused = document.activeElement;
        el.classList.remove('hidden');
        const target = (focusSelector && el.querySelector(focusSelector)) || el.querySelector(FOCUSABLE);
        if (target) target.focus();
    }
    function closeModal(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('hidden');
        if (lastFocused && document.contains(lastFocused)) lastFocused.focus();
        lastFocused = null;
    }
    document.addEventListener('keydown', function (e) {
        const modal = topmostOpenModal();
        if (!modal) return;
        if (e.key === 'Escape') { e.preventDefault(); closeModal(modal.id); return; }
        if (e.key !== 'Tab') return;
        const items = Array.from(modal.querySelectorAll(FOCUSABLE)).filter(el => el.offsetParent !== null);
        if (!items.length) return;
        const first = items[0], last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
    // Backdrop closes confirmations only; the adjustment form holds typed values.
    ['stepModal', 'removeLineModal'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('click', e => { if (e.target === el) closeModal(id); });
    });
    window.closeModalById = closeModal;

    // Double-submit guard for every POST form on the page.
    document.querySelectorAll('form[method="POST"]').forEach(f => {
        let submitting = false;
        f.addEventListener('submit', e => {
            if (submitting) { e.preventDefault(); return; }
            if (!f.checkValidity()) return;
            submitting = true;
            const btns = [...f.querySelectorAll('button[type="submit"]'), ...(f.id ? document.querySelectorAll('button[form="' + f.id + '"]') : [])];
            btns.forEach(b => { b.disabled = true; b.classList.add('opacity-70', 'cursor-not-allowed'); });
        });
    });
@endif

@if($actions)
    // ── Step confirmations — copy and routes from the PHP $actions map ──
    const ACTIONS = @json($actions);
    const BTN = @json($btn);
    window.openStepModal = function (key) {
        const a = ACTIONS[key];
        if (!a) return;
        document.getElementById('stepModalTitle').textContent = a.title;
        document.getElementById('stepModalDesc').textContent = a.body;
        document.getElementById('stepForm').action = a.url;
        document.getElementById('stepMethod').value = a.method;
        const c = document.getElementById('stepConfirm');
        c.className = 'w-full sm:w-auto ' + BTN[a.button];
        c.textContent = a.confirm;
        openModal('stepModal', '#stepModal button[type="button"]');
    };
@endif

@if($canAdjust)
    // ── Adjustments ──
    const ROUTES = {
        add:    @json(route('payroll.payslips.lines.store', '__ID__')),
        remove: @json(route('payroll.lines.destroy', '__ID__')),
    };
    const routeFor = (t, id) => t.replace('__ID__', encodeURIComponent(id));
    let restoring = false;

    function openAdjustModal(btn) {
        const d = JSON.parse(btn.dataset.adjust);
        document.getElementById('adjustForm').action = routeFor(ROUTES.add, d.id);
        document.getElementById('adj_record').value = d.id;
        document.getElementById('adj_name').textContent = d.name;
        if (!restoring) {
            document.getElementById('adj_label').value = '';
            document.getElementById('adj_amount').value = '';
            document.getElementById('adj_note').value = '';
        }
        openModal('adjustModal', '#adj_code');
    }
    function openRemoveLineModal(btn) {
        const d = JSON.parse(btn.dataset.line);
        document.getElementById('removeLineForm').action = routeFor(ROUTES.remove, d.id);
        document.getElementById('rl_label').textContent = d.label + ' (' + d.amount + ')';
        document.getElementById('rl_name').textContent = d.name;
        openModal('removeLineModal', '#removeLineModal button[type="button"]');
    }
    window.openAdjustModal = openAdjustModal;
    window.openRemoveLineModal = openRemoveLineModal;

    // Re-open the adjustment modal after a validation error, with the typed values.
    const OLD_MODAL = @json(old('_modal'));
    const OLD_RECORD = @json(old('_record'));
    if (OLD_MODAL === 'adjustModal' && OLD_RECORD) {
        const src = Array.from(document.querySelectorAll('[data-adjust]')).find(b => String(JSON.parse(b.dataset.adjust).id) === String(OLD_RECORD));
        if (src) { restoring = true; openAdjustModal(src); restoring = false; }
    }
@endif
}());
</script>

<style>
[x-cloak] { display: none !important; }

@media (max-width: 767px) {
    .rt, .rt tbody, .rt tfoot, .rt tr, .rt td, .rt th[role="rowheader"] { display: block; width: 100%; }
    .rt thead { display: none; }
    .rt tr { padding: 0.75rem 1rem; }
    .rt td, .rt th[role="rowheader"] { padding: 0.375rem 0 !important; text-align: left !important; }
    .rt td[data-label]::before {
        content: attr(data-label);
        display: block;
        margin-bottom: 0.125rem;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #6b7280;
    }
    .rt td.rt-actions { padding-top: 0.75rem !important; }
    .rt td.rt-actions::before, .rt td.rt-detail::before { display: none; }
    .rt td.rt-detail { padding: 0 !important; }
    .rt td.rt-empty { padding: 2rem 0 !important; text-align: center !important; }
    .rt td.rt-hide { display: none; }
}
@media (max-width: 767px) and (prefers-color-scheme: dark) {
    .rt td[data-label]::before { color: #9ca3af; }
}
</style>
@endsection
