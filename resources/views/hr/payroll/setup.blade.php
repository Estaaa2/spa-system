@extends('layouts.app')

@section('title', 'Payroll Setup')
@section('content')
@php
    $user    = auth()->user();
    $canEdit = $user?->hasBranchPermission('edit payroll') ?? false;

    // ── Tokens — same shapes as appointments.blade.php / staff/index.blade.php ──
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
    $th         = 'px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400';
    $badgeBase  = 'inline-flex items-center gap-1 px-2.5 py-0.5 text-xs font-medium rounded-full';

    // ── Status / tier maps — ONE source, used by Blade and emitted to JS via @json ──
    $ruleStateMeta = [
        'active'    => ['label' => 'Active',    'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
        'scheduled' => ['label' => 'Scheduled', 'class' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'],
        'ended'     => ['label' => 'Ended',     'class' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'],
    ];
    $tierMeta = [
        'branch_target'  => ['rank' => 1, 'label' => 'This branch + this service',  'class' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'],
        'spa_target'     => ['rank' => 2, 'label' => 'All branches + this service', 'class' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'],
        'branch_default' => ['rank' => 3, 'label' => 'This branch default',         'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'],
        'spa_default'    => ['rank' => 4, 'label' => 'Spa-wide default',            'class' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300'],
    ];
    $lockedClass = 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';

    // ── Lookups ──
    $branchNames = $branches->pluck('name', 'id')->all();

    $hasPayrollAccess = $spa->hasAccess()
        && $spa->hasFeature('payroll');

    $payrollBranches = $hasPayrollAccess
        ? $branches
        : collect();

    $payrollBranchCount = $payrollBranches->count();
    $targetNames = [
        'treatment' => $treatments->mapWithKeys(fn ($t) => [$t->id => $t->name . ($t->deleted_at ? ' (deleted)' : '')])->all(),
        'package'   => $packages->mapWithKeys(fn ($p) => [$p->id => $p->name . ($p->deleted_at ? ' (deleted)' : '')])->all(),
    ];
    $liveTreatments = $treatments->whereNull('deleted_at')->values();
    $livePackages   = $packages->whereNull('deleted_at')->values();

    $ruleState = function ($r) use ($today) {
        if ($r->effective_from->toDateString() > $today) return 'scheduled';
        if ($r->effective_to && $r->effective_to->toDateString() < $today) return 'ended';
        return 'active';
    };
    $ruleValue = fn ($r) => $r->method === \App\Models\CommissionRule::METHOD_PERCENT
        ? rtrim(rtrim((string) $r->value, '0'), '.') . '%'
        : '₱' . number_format((float) $r->value, 2) . ' flat';
    $ruleTarget = fn ($r) => $r->target_type === \App\Models\CommissionRule::TARGET_DEFAULT
        ? 'Default (any service)'
        : (ucfirst($r->target_type) . ': ' . ($targetNames[$r->target_type][$r->target_id] ?? "#{$r->target_id} (not found)"));

    $missingWage = $payrollBranches
    ->filter(fn ($branch) => $branch->min_daily_wage === null)
    ->count();
    $activeRules = $rules->filter(fn ($r) => $ruleState($r) === 'active')->count();

    // Per-form error bags (controller: HandlesPayrollSetupForms).
    $bag = fn (string $name) => $errors->getBag($name);
    $oldModal  = old('_modal');
    $oldRecord = old('_record');

    $tabs = [
        'schedule'   => ['icon' => 'fa-calendar-days', 'short' => 'Schedule',   'long' => 'Pay Schedule',       'bags' => ['schedule']],
        'wages'      => ['icon' => 'fa-scale-balanced', 'short' => 'Wage', 'long' => 'Branch Minimum Wage', 'bags' => ['wage']],
        'commission' => ['icon' => 'fa-percent',        'short' => 'Commission', 'long' => 'Commission Rules',   'bags' => ['ruleCreate', 'ruleEdit', 'ruleChange', 'ruleEnd', 'ruleDelete']],
    ];
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl" x-data="payrollSetupPage()" x-init="init()">

    <x-page-header
        title="Payroll Setup"
        subtitle="Pay schedule, branch minimum wages and commission rules used by every payroll run (spa-wide)."
    >
        <x-slot name="right">
            <a href="{{ route('payroll.staff.index') }}" class="{{ $btn['edit'] }}">
                <i class="text-xs fa-solid fa-users" aria-hidden="true"></i>
                Staff Pay Setup
            </a>
        </x-slot>
    </x-page-header>

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">First Cutoff</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">{{ $spa->payroll_first_cutoff_day }}</h3>
                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">Pay +{{ $spa->payroll_pay_day_offset }} days</span>
            </div>
        </div>
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                Payroll Branches
            </p>

            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold text-gray-900 whitespace-nowrap sm:text-3xl dark:text-white">
                    {{ $payrollBranchCount }}/{{ $branches->count() }}
                </h3>

                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                    Business payroll access
                </span>
            </div>
        </div>
        <div class="p-4 border shadow-sm sm:p-5 rounded-2xl {{ $missingWage > 0 ? 'bg-amber-50 border-amber-200 dark:bg-amber-900/10 dark:border-amber-800' : 'bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-700' }}">
            <p class="text-xs font-semibold tracking-wide uppercase {{ $missingWage > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-gray-500 dark:text-gray-400' }}">Missing Min. Wage</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold sm:text-3xl {{ $missingWage > 0 ? 'text-amber-900 dark:text-amber-200' : 'text-gray-900 dark:text-white' }}">{{ $missingWage }}</h3>
                <span class="text-xs sm:text-sm {{ $missingWage > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-gray-500 dark:text-gray-400' }}">Suite branches</span>
            </div>
        </div>
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Commission Rules</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">{{ $activeRules }}</h3>
                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">Active today</span>
            </div>
        </div>
    </div>

    @if(session('warning'))
        <div role="status" class="p-4 border border-amber-200 rounded-2xl bg-amber-50 dark:bg-amber-900/10 dark:border-amber-800">
            <p class="text-sm text-amber-800 dark:text-amber-300"><i class="mr-1 fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ session('warning') }}</p>
        </div>
    @endif

    @if (! $hasPayrollAccess)
        <div role="status" class="p-4 border border-purple-200 rounded-2xl bg-purple-50 dark:border-purple-800 dark:bg-purple-900/10">
            <p class="text-sm font-semibold text-purple-900 dark:text-purple-200">
                <i class="mr-1 fa-solid fa-lock" aria-hidden="true"></i>
                Payroll requires an active Business plan
            </p>

            <p class="mt-2 text-sm text-purple-800 dark:text-purple-300">
                Payroll is unavailable until the spa has an active subscription with the payroll feature.
            </p>

            @if (Route::has('owner.subscription.index'))
                <a
                    href="{{ route('owner.subscription.index') }}"
                    class="mt-3 inline-flex min-h-[44px] items-center gap-2 rounded-xl bg-[#8B7355] px-4 py-2 text-sm font-semibold text-white hover:bg-[#7A6348]"
                >
                    <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
                    View Subscription Plans
                </a>
            @endif
        </div>
    @elseif ($payrollBranchCount === 0)
        <div role="status" class="p-4 border rounded-2xl border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/10">
            <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">
                <i class="mr-1 fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                No active branches are available for payroll
            </p>

            <p class="mt-2 text-sm text-amber-800 dark:text-amber-300">
                Add an active branch before configuring payroll.
            </p>
        </div>
    @endif

    {{-- Section switcher — same pattern as branches/edit.blade.php --}}
    <div role="tablist" aria-label="Payroll setup sections"
         @keydown.arrow-right.prevent="cycleTab(1)" @keydown.arrow-left.prevent="cycleTab(-1)"
         class="flex gap-1 p-1.5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        @foreach($tabs as $key => $t)
            @php $tabHasErrors = collect($t['bags'])->contains(fn ($b) => $errors->hasBag($b) && $errors->getBag($b)->any()); @endphp
            <button @click="tab = '{{ $key }}'" type="button"
                    class="setup-tab flex items-center justify-center flex-1 min-w-0 gap-2 px-3 min-h-[44px] text-sm font-medium transition rounded-xl text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                    role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
                    :aria-selected="tab === '{{ $key }}' ? 'true' : 'false'"
                    :tabindex="tab === '{{ $key }}' ? 0 : -1">
                <i class="text-xs fa-solid {{ $t['icon'] }}" aria-hidden="true"></i>
                <span class="sm:hidden">{{ $t['short'] }}</span>
                <span class="hidden sm:inline">{{ $t['long'] }}</span>
                @if($tabHasErrors)
                    <span class="flex items-center justify-center w-4 h-4 text-[10px] font-bold text-white bg-red-500 rounded-full" title="This section has errors">!</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- ═════════════════════════════════════════════════════════════════
         TAB 1 — SCHEDULE
         ═════════════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'schedule'" x-cloak role="tabpanel" id="panel-schedule" aria-labelledby="tab-schedule" class="space-y-6">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="{{ $card }}">
                <div class="{{ $cardHead }}">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Semi-monthly Pay Schedule</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Cutoff 1 runs from day 1 to the first-cutoff day; cutoff 2 runs to the end of the month. Pay day is the cutoff end plus the offset.
                    </p>
                </div>
                <form method="POST" action="{{ route('payroll.setup.schedule') }}" class="p-4 space-y-4 sm:p-6">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_modal" value="">
                    <fieldset @disabled(!$canEdit) class="space-y-4">
                        <div>
                            <label for="payroll_first_cutoff_day" class="{{ $labelClass }}">First cutoff ends on day</label>
                            <input type="number" id="payroll_first_cutoff_day" name="payroll_first_cutoff_day" min="1" max="27" required
                                   value="{{ old('payroll_first_cutoff_day', $spa->payroll_first_cutoff_day) }}" class="{{ $inputClass }}"
                                   aria-describedby="cutoffHelp">
                            <p id="cutoffHelp" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Must be day {{ 31 - $maxInterval }} or {{ $maxInterval }} so both cutoffs stay within {{ $maxInterval }} days (Labor Code Art. 103).
                            </p>
                            @if($bag('schedule')->has('payroll_first_cutoff_day'))
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $bag('schedule')->first('payroll_first_cutoff_day') }}</p>
                            @endif
                        </div>
                        <div>
                            <label for="payroll_pay_day_offset" class="{{ $labelClass }}">Pay day is this many days after each cutoff ends</label>
                            <input type="number" id="payroll_pay_day_offset" name="payroll_pay_day_offset" min="0" max="{{ $maxInterval }}" required
                                   value="{{ old('payroll_pay_day_offset', $spa->payroll_pay_day_offset) }}" class="{{ $inputClass }}">
                            @if($bag('schedule')->has('payroll_pay_day_offset'))
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $bag('schedule')->first('payroll_pay_day_offset') }}</p>
                            @endif
                        </div>
                    </fieldset>
                    @if($canEdit)
                        <div class="flex pt-1 sm:justify-end">
                            <button type="submit" class="w-full {{ $btn['primary'] }} sm:w-auto">
                                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save Schedule
                            </button>
                        </div>
                    @else
                        <p class="text-xs text-gray-500 dark:text-gray-400"><i class="mr-1 fa-solid fa-lock" aria-hidden="true"></i>You can view the schedule but not change it.</p>
                    @endif
                </form>
            </div>

            <div class="{{ $card }}">
                <div class="{{ $cardHead }}">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Upcoming Periods</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">How payroll will cut the next two months with the saved settings.</p>
                </div>
                <div class="p-4 space-y-4 sm:p-6">
                    @foreach($periodPreview as $m)
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $m['label'] }}</p>
                            @if($m['error'])
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $m['error'] }}</p>
                            @else
                                <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                                    @foreach($m['periods'] as $p)
                                        <li>
                                            Cutoff {{ $p['cutoff'] }}:
                                            {{ \Illuminate\Support\Carbon::parse($p['start'])->format('M j') }}–{{ \Illuminate\Support\Carbon::parse($p['end'])->format('M j') }}
                                            <span class="text-gray-500 dark:text-gray-400">({{ $p['days'] }} days)</span>
                                            · paid {{ \Illuminate\Support\Carbon::parse($p['pay'])->format('M j, Y') }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                    <p class="text-xs text-gray-500 dark:text-gray-400">Existing draft runs keep their dates until regenerated; finalized runs never change.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Read-only facts --}}
            <div class="{{ $card }}">
                <div class="{{ $cardHead }}">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">How Payroll Runs</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Fixed rules — not settings.</p>
                </div>
                <ul class="p-4 space-y-3 text-sm text-gray-700 sm:p-6 dark:text-gray-300">
                    <li class="flex gap-3"><i class="mt-0.5 text-gray-400 fa-solid fa-building-columns" aria-hidden="true"></i>
                        <span>SSS, PhilHealth and Pag-IBIG are deducted in full on the <strong>2nd cutoff</strong> of each month. Cutoff 2 can only run after cutoff 1 of the same month is finalized.</span></li>
                    <li class="flex gap-3"><i class="mt-0.5 text-gray-400 fa-solid fa-receipt" aria-hidden="true"></i>
                        <span>Commission counts only bookings that are <strong>completed and fully paid</strong>, and only for staff whose pay profile has commission turned on.</span></li>
                    <li class="flex gap-3"><i class="mt-0.5 text-gray-400 fa-solid fa-code-branch" aria-hidden="true"></i>
                        <span>Only active staff whose <strong>home branch has a minimum wage on file</strong> are included in payroll.</span>
                    <li class="flex gap-3"><i class="mt-0.5 text-gray-400 fa-solid fa-scale-balanced" aria-hidden="true"></i>
                        <span>Withholding tax is computed each cutoff; year-end annualization is done by your bookkeeper.</span></li>
                </ul>
            </div>

            {{-- Days-per-year factors (replaces the deprecated monthly_rate_divisor — data model v3.1) --}}
            <div class="{{ $card }}">
                <div class="{{ $cardHead }}">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Days-per-Year Factors</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Set automatically from each staff member's pay basis and rest days (DOLE Advisory No. 001-10). Nothing to enter here.
                    </p>
                </div>
                <div class="p-4 sm:p-6">
                    <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($factors as $f)
                            <div class="flex items-center justify-between py-2 text-sm">
                                <dt class="text-gray-600 dark:text-gray-300">{{ $f['label'] }}</dt>
                                <dd class="font-semibold text-gray-900 dark:text-white">{{ $f['factor'] ?? '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        Monthly-paid daily rate = monthly rate × 12 ÷ 365. Daily-paid staff must have 1 or 2 rest days a week to be paid.
                        The 365 factor is marked VERIFY in the rates sheet — confirm with your accountant.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- ═════════════════════════════════════════════════════════════════
         TAB 2 — BRANCH MINIMUM WAGE
         ═════════════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'wages'" x-cloak role="tabpanel" id="panel-wages" aria-labelledby="tab-wages" class="space-y-6">
        <div class="p-4 border border-blue-200 rounded-2xl bg-blue-50 dark:bg-blue-900/10 dark:border-blue-800">
            <p class="text-sm text-blue-800 dark:text-blue-300">
                <i class="mr-1 fa-solid fa-circle-info" aria-hidden="true"></i>
                Calabarzon's current order is <strong>Wage Order No. IVA-22</strong>. The rate depends on each branch's locality and number of workers, so enter the amount that applies to that branch — Levictas does not fill it in for you.
                Payroll tops up any worked day that pays less than the branch's minimum.
            </p>
        </div>

        <div class="{{ $card }}">
            <div class="flex flex-col gap-1 {{ $cardHead }} sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Branch Minimum Daily Wage</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Each staff member's minimum wage comes from the branch where they worked that day.</p>
                </div>
                <span class="text-sm text-gray-500 shrink-0 dark:text-gray-400">{{ $branches->count() }} branch(es)</span>
            </div>
            <div class="md:overflow-x-auto">
                <table role="table" class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700">
                    <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                        <tr role="row">
                            <th role="columnheader" class="{{ $th }}">Branch</th>
                            <th role="columnheader" class="{{ $th }}">Payroll</th>
                            <th role="columnheader" class="{{ $th }} text-right">Min. Daily Wage</th>
                            <th role="columnheader" class="{{ $th }}">Wage Order</th>
                            <th role="columnheader" class="{{ $th }}">Effective From</th>
                            @if($canEdit)<th role="columnheader" class="{{ $th }}">Actions</th>@endif
                        </tr>
                    </thead>
                    <tbody role="rowgroup" class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        @forelse($branches as $b)
                            @php
                                $wageRow = [
                                    'id' => $b->id, 'name' => $b->name,
                                    'min_daily_wage' => $b->min_daily_wage !== null ? (string) $b->min_daily_wage : '',
                                    'wage_order_ref' => $b->wage_order_ref ?? '',
                                    'min_wage_effective_from' => $b->min_wage_effective_from?->toDateString() ?? '',
                                ];
                            @endphp
                            <tr role="row" class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td role="cell" data-label="Branch" class="px-6 py-4">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $b->name }}
                                        @if($b->is_main)<i class="ml-1 text-xs text-amber-500 fa-solid fa-crown" title="Main branch" aria-label="Main branch"></i>@endif
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $b->location }}</p>
                                </td>
                                <td role="cell" data-label="Payroll" class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        @if ($hasPayrollAccess)
                                            <span class="{{ $badgeBase }} bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300">
                                                <i class="fa-solid fa-money-check-dollar text-[10px]" aria-hidden="true"></i>
                                                Payroll enabled
                                            </span>
                                        @else
                                            <span class="{{ $badgeBase }} bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                                Payroll unavailable
                                            </span>
                                        @endif
                                        @if($b->min_daily_wage === null)
                                            <span class="{{ $badgeBase }} bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                                <i class="fa-solid fa-triangle-exclamation text-[10px]" aria-hidden="true"></i>
                                                Missing rate — payroll cannot calculate this branch's staff
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td role="cell" data-label="Min. Daily Wage" class="px-6 py-4 text-sm font-semibold text-right text-gray-900 whitespace-nowrap dark:text-white">
                                    {{ $b->min_daily_wage !== null ? '₱' . number_format((float) $b->min_daily_wage, 2) : '—' }}
                                </td>
                                <td role="cell" data-label="Wage Order" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $b->wage_order_ref ?? '—' }}</td>
                                <td role="cell" data-label="Effective From" class="px-6 py-4 text-sm text-gray-700 whitespace-nowrap dark:text-gray-300">
                                    {{ $b->min_daily_wage !== null ? ($b->min_wage_effective_from?->format('M d, Y') ?? '—') : '—' }}
                                    @if($b->min_daily_wage !== null && $b->min_wage_effective_from && $b->min_wage_effective_from->toDateString() > $today)
                                        <span class="{{ $badgeBase }} mt-1 bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300" title="Payroll already uses this rate for every day it computes">Future date — already in use</span>
                                    @endif
                                </td>
                                @if($canEdit)
                                    <td role="cell" data-label="Actions" class="px-6 py-4 rt-actions">
                                        <button type="button" class="{{ $btn['edit'] }}" data-wage='@json($wageRow)' onclick="openWageModal(this)">
                                            <i class="text-xs fa-solid fa-pen" aria-hidden="true"></i><span class="whitespace-nowrap">{{ $b->min_daily_wage === null ? 'Set Rate' : 'Edit' }}</span>
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr role="row">
                                <td role="cell" colspan="{{ $canEdit ? 6 : 5 }}" class="px-6 py-12 text-center text-gray-500 rt-empty dark:text-gray-400">No branches yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═════════════════════════════════════════════════════════════════
         TAB 3 — COMMISSION RULES
         ═════════════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'commission'" x-cloak role="tabpanel" id="panel-commission" aria-labelledby="tab-commission" class="space-y-6">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Resolution order --}}
            <div class="{{ $card }}">
                <div class="{{ $cardHead }}">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Which Rule Wins</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">For each booking, payroll uses the first rule that matches, in this order, on the appointment date.</p>
                </div>
                <ol class="p-4 space-y-2 sm:p-6">
                    @foreach($tierMeta as $key => $m)
                        <li class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                            <span class="flex items-center justify-center w-6 h-6 text-xs font-semibold rounded-full {{ $m['class'] }}">{{ $m['rank'] }}</span>
                            {{ $m['label'] }}
                        </li>
                    @endforeach
                </ol>
                <p class="px-4 pb-4 text-xs text-gray-500 sm:px-6 dark:text-gray-400">
                    Percent rules apply to the booking total (or the list price when the total is 0). No matching rule means no commission for that booking — payroll lists it as a warning.
                </p>
            </div>

            {{-- Preview --}}
            <div class="{{ $card }}">
                <div class="{{ $cardHead }}">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Check Which Rule Applies</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Uses the same lookup as the payroll run.</p>
                </div>
                <form id="previewForm" class="p-4 space-y-4 sm:p-6" novalidate>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="preview_target" class="{{ $labelClass }}">Service</label>
                            <select id="preview_target" class="{{ $inputClass }}">
                                <option value="default">Any other service (default)</option>
                                @if($liveTreatments->isNotEmpty())
                                    <optgroup label="Treatments">
                                        @foreach($liveTreatments as $t)
                                            <option value="treatment_{{ $t->id }}" data-branch="{{ $t->branch_id }}">{{ $t->name }} — {{ $branchNames[$t->branch_id] ?? 'no branch' }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                @if($livePackages->isNotEmpty())
                                    <optgroup label="Packages">
                                        @foreach($livePackages as $p)
                                            <option value="package_{{ $p->id }}" data-branch="{{ $p->branch_id }}">{{ $p->name }} — {{ $branchNames[$p->branch_id] ?? 'no branch' }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                        </div>
                        <div>
                            <label for="preview_branch" class="{{ $labelClass }}">Booked at branch</label>
                            <select id="preview_branch" class="{{ $inputClass }}">
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="preview_date" class="{{ $labelClass }}">Appointment date</label>
                            <input type="date" id="preview_date" value="{{ $today }}" class="{{ $inputClass }}">
                        </div>
                    </div>
                    <div class="flex sm:justify-end">
                        <button type="submit" id="previewSubmit" class="w-full {{ $btn['neutral'] }} sm:w-auto" @disabled($branches->isEmpty())>
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Check
                        </button>
                    </div>
                    <div id="previewResult" role="status" aria-live="polite" class="hidden p-4 text-sm rounded-2xl bg-gray-50 dark:bg-gray-900/40">
                        <div class="flex flex-wrap items-center gap-2">
                            <span id="previewTier" class="{{ $badgeBase }}"></span>
                            <span id="previewRuleId" class="text-xs text-gray-500 dark:text-gray-400"></span>
                        </div>
                        <p id="previewText" class="mt-2 font-medium text-gray-900 dark:text-white"></p>
                        <p id="previewRange" class="mt-1 text-xs text-gray-500 dark:text-gray-400"></p>
                    </div>
                </form>
            </div>
        </div>

        {{-- Add rule --}}
        @if($canEdit)
            @php $eb = $bag('ruleCreate'); @endphp
            <div class="{{ $card }}">
                <div class="{{ $cardHead }}">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Add Commission Rule</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">To change an existing rate, use “Change from date” on that rule instead — it keeps the history. Each treatment or package belongs to one branch, so a service rule only ever applies at the branch that sells it.</p>
                </div>
                <form method="POST" action="{{ route('payroll.setup.rules.store') }}" id="ruleCreateForm" class="p-4 space-y-4 sm:p-6" data-rule-form>
                    @csrf
                    <input type="hidden" name="_modal" value="">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label for="rc_branch" class="{{ $labelClass }}">Branch</label>
                            <select id="rc_branch" name="branch_id" class="{{ $inputClass }}">
                                <option value="">All branches</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" @selected((string) old('branch_id') === (string) $b->id && !$oldModal)>{{ $b->name }}</option>
                                @endforeach
                            </select>
                            @if($eb->has('branch_id'))<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $eb->first('branch_id') }}</p>@endif
                        </div>
                        <div>
                            <label for="rc_target_type" class="{{ $labelClass }}">Applies to</label>
                            <select id="rc_target_type" name="target_type" class="{{ $inputClass }}" data-target-type>
                                @foreach(['default' => 'Default (any service)', 'treatment' => 'A treatment', 'package' => 'A package'] as $v => $l)
                                    <option value="{{ $v }}" @selected(old('target_type', 'default') === $v && !$oldModal)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div data-target-wrap>
                            <label for="rc_target_id" class="{{ $labelClass }}">Treatment / package</label>
                            <select id="rc_target_id" name="target_id" class="{{ $inputClass }}" data-target-id data-old="{{ $oldModal ? '' : old('target_id') }}"></select>
                            @if($eb->has('target_id'))<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $eb->first('target_id') }}</p>@endif
                        </div>
                        <div>
                            <label for="rc_method" class="{{ $labelClass }}">Method</label>
                            <select id="rc_method" name="method" class="{{ $inputClass }}" data-method>
                                <option value="percent" @selected(old('method', 'percent') === 'percent' && !$oldModal)>Percent of booking</option>
                                <option value="flat" @selected(old('method') === 'flat' && !$oldModal)>Flat amount per booking</option>
                            </select>
                        </div>
                        <div>
                            <label for="rc_value" class="{{ $labelClass }}"><span data-value-label>Value</span></label>
                            <input type="text" inputmode="decimal" id="rc_value" name="value" required value="{{ $oldModal ? '' : old('value') }}" class="{{ $inputClass }}" placeholder="e.g. 10">
                            @if($eb->has('value'))<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $eb->first('value') }}</p>@endif
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="rc_from" class="{{ $labelClass }}">From</label>
                                <input type="date" id="rc_from" name="effective_from" required value="{{ $oldModal ? $today : old('effective_from', $today) }}" class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label for="rc_to" class="{{ $labelClass }}">To <span class="font-normal text-gray-500">(optional)</span></label>
                                <input type="date" id="rc_to" name="effective_to" value="{{ $oldModal ? '' : old('effective_to') }}" class="{{ $inputClass }}">
                            </div>
                        </div>
                    </div>
                    @foreach(['effective_from', 'effective_to', 'method', 'target_type'] as $f)
                        @if($eb->has($f))<p class="text-xs text-red-600 dark:text-red-400">{{ $eb->first($f) }}</p>@endif
                    @endforeach
                    <div class="flex sm:justify-end">
                        <button type="submit" class="w-full {{ $btn['primary'] }} sm:w-auto">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i> Add Rule
                        </button>
                    </div>
                </form>
            </div>
        @endif

        {{-- Rules table --}}
        <div class="{{ $card }}">
            <div class="flex flex-col gap-1 {{ $cardHead }} sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Commission Rules</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Rules used by a finalized payroll run are locked: they can only be ended or changed from a later date.</p>
                </div>
                <span class="text-sm text-gray-500 shrink-0 dark:text-gray-400">{{ $rules->count() }} rule(s)</span>
            </div>
            @if($bag('ruleDelete')->any())
                <p class="px-6 pt-4 text-sm text-red-600 dark:text-red-400">{{ $bag('ruleDelete')->first() }}</p>
            @endif
            <div class="md:overflow-x-auto">
                <table role="table" class="min-w-full divide-y divide-gray-200 rt dark:divide-gray-700">
                    <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                        <tr role="row">
                            <th role="columnheader" class="{{ $th }}">Rule</th>
                            <th role="columnheader" class="{{ $th }}">Applies To</th>
                            <th role="columnheader" class="{{ $th }}">Commission</th>
                            <th role="columnheader" class="{{ $th }}">Effective</th>
                            <th role="columnheader" class="{{ $th }}">Status</th>
                            @if($canEdit)<th role="columnheader" class="{{ $th }}">Actions</th>@endif
                        </tr>
                    </thead>
                    <tbody role="rowgroup" class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        @forelse($rules as $r)
                            @php
                                $state  = $ruleState($r);
                                $usage  = $ruleUsage[$r->id] ?? ['locked_through' => null, 'pending' => 0];
                                $locked = $usage['locked_through'] !== null;
                                $tier   = $tierMeta[\App\Services\Payroll\PayrollSetupService::tierLabel($r)];
                                $isOpen = $r->effective_to === null || $r->effective_to->toDateString() >= $today;
                                $rowData = [
                                    'id' => $r->id, 'branch_id' => $r->branch_id, 'target_type' => $r->target_type, 'target_id' => $r->target_id,
                                    'method' => $r->method, 'value' => rtrim(rtrim((string) $r->value, '0'), '.'),
                                    'effective_from' => $r->effective_from->toDateString(), 'effective_to' => $r->effective_to?->toDateString(),
                                    'locked_through' => $usage['locked_through'], 'label' => $ruleTarget($r),
                                ];
                            @endphp
                            <tr role="row" class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td role="cell" data-label="Rule" class="px-6 py-4">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">#{{ $r->id }}</p>
                                    <span class="{{ $badgeBase }} mt-1 whitespace-nowrap {{ $tier['class'] }}" title="Priority {{ $tier['rank'] }}">{{ $tier['rank'] }} · {{ $r->branch_id ? 'Branch' : 'Spa-wide' }}</span>
                                </td>
                                <td role="cell" data-label="Applies To" class="px-6 py-4">
                                    <p class="text-sm text-gray-900 dark:text-white">{{ $ruleTarget($r) }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $r->branch_id ? ($branchNames[$r->branch_id] ?? "Branch #{$r->branch_id}") : 'All branches' }}</p>
                                </td>
                                <td role="cell" data-label="Commission" class="px-6 py-4 text-sm font-semibold text-gray-900 whitespace-nowrap dark:text-white">{{ $ruleValue($r) }}</td>
                                <td role="cell" data-label="Effective" class="px-6 py-4 text-sm text-gray-700 whitespace-nowrap dark:text-gray-300">
                                    {{ $r->effective_from->format('M d, Y') }} – {{ $r->effective_to?->format('M d, Y') ?? 'open' }}
                                </td>
                                <td role="cell" data-label="Status" class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        <span class="{{ $badgeBase }} {{ $ruleStateMeta[$state]['class'] }}">{{ $ruleStateMeta[$state]['label'] }}</span>
                                        @if($locked)
                                            <span class="{{ $badgeBase }} {{ $lockedClass }}" title="Used by a finalized run through {{ $usage['locked_through'] }}">
                                                <i class="fa-solid fa-lock text-[10px]" aria-hidden="true"></i> Paid through {{ \Illuminate\Support\Carbon::parse($usage['locked_through'])->format('M d') }}
                                            </span>
                                        @endif
                                        @if($usage['pending'] > 0)
                                            <span class="{{ $badgeBase }} bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300" title="Regenerate those drafts after changing this rule">In {{ $usage['pending'] }} draft/approved payslip(s)</span>
                                        @endif
                                    </div>
                                </td>
                                @if($canEdit)
                                    <td role="cell" data-label="Actions" class="px-6 py-4 rt-actions">
                                        <div class="flex flex-wrap gap-2">
                                            @if($isOpen)
                                                <button type="button" class="{{ $btn['edit'] }}" data-rule='@json($rowData)' onclick="openRuleChangeModal(this)">
                                                    <i class="text-xs fa-solid fa-arrows-rotate" aria-hidden="true"></i><span>Change</span>
                                                </button>
                                                <button type="button" class="{{ $btn['warn'] }}" data-rule='@json($rowData)' onclick="openRuleEndModal(this)">
                                                    <i class="text-xs fa-solid fa-flag-checkered" aria-hidden="true"></i><span>End</span>
                                                </button>
                                            @endif
                                            @unless($locked)
                                                <button type="button" class="{{ $btn['edit'] }}" data-rule='@json($rowData)' onclick="openRuleEditModal(this)">
                                                    <i class="text-xs fa-solid fa-pen" aria-hidden="true"></i><span>Edit</span>
                                                </button>
                                                <button type="button" class="{{ $btn['remove'] }}" data-rule='@json($rowData)' onclick="openRuleDeleteModal(this)">
                                                    <i class="text-xs fa-solid fa-trash" aria-hidden="true"></i><span>Delete</span>
                                                </button>
                                            @endunless
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr role="row">
                                <td role="cell" colspan="{{ $canEdit ? 6 : 5 }}" class="px-6 py-12 text-center text-gray-500 rt-empty dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="mb-3 text-4xl text-gray-400 fa-solid fa-percent" aria-hidden="true"></i>
                                        <p class="mb-2 text-gray-600 dark:text-gray-400">No commission rules yet</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Without a rule, completed bookings earn no commission. Start with a spa-wide default.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if($canEdit)
{{-- ═══════════════════════════════════════════════════
     WAGE MODAL
     ═══════════════════════════════════════════════════ --}}
@php $eb = $bag('wage'); @endphp
<div id="wageModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="wageModalTitle" class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <form id="wageForm" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="_modal" value="wageModal">
                <input type="hidden" name="_record" id="wage_record" value="">
                <div class="flex items-start justify-between gap-3 {{ $cardHead }}">
                    <div>
                        <h2 id="wageModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Minimum Wage</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">For <span id="wageBranchName" class="font-medium text-gray-900 dark:text-white"></span>.</p>
                    </div>
                    <button type="button" onclick="closeModalById('wageModal')" aria-label="Close dialog"
                            class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="px-4 py-6 space-y-4 sm:px-6">
                    <div>
                        <label for="wage_amount" class="{{ $labelClass }}">Minimum daily wage (₱)</label>
                        <input type="text" inputmode="decimal" id="wage_amount" name="min_daily_wage" required class="{{ $inputClass }}"
                               value="{{ $oldModal === 'wageModal' ? old('min_daily_wage') : '' }}">
                        @if($eb->has('min_daily_wage'))<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $eb->first('min_daily_wage') }}</p>@endif
                    </div>
                    <div>
                        <label for="wage_ref" class="{{ $labelClass }}">Wage order reference</label>
                        <input type="text" id="wage_ref" name="wage_order_ref" required maxlength="255" class="{{ $inputClass }}" placeholder="e.g. Wage Order No. IVA-22"
                               value="{{ $oldModal === 'wageModal' ? old('wage_order_ref') : '' }}">
                        @if($eb->has('wage_order_ref'))<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $eb->first('wage_order_ref') }}</p>@endif
                    </div>
                    <div>
                        <label for="wage_from" class="{{ $labelClass }}">Effective from</label>
                        <input type="date" id="wage_from" name="min_wage_effective_from" required class="{{ $inputClass }}"
                               value="{{ $oldModal === 'wageModal' ? old('min_wage_effective_from') : '' }}">
                        @if($eb->has('min_wage_effective_from'))<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $eb->first('min_wage_effective_from') }}</p>@endif
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Payroll uses the current amount for every date it computes; finalized runs keep the amount they used.</p>
                </div>
                <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeModalById('wageModal')" class="w-full {{ $btn['neutral'] }} sm:w-auto">Cancel</button>
                        <button type="submit" class="w-full {{ $btn['primary'] }} sm:w-auto">Save Rate</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════
     RULE EDIT MODAL (unlocked rules only)
     ═══════════════════════════════════════════════════ --}}
@php $eb = $bag('ruleEdit'); $isOld = $oldModal === 'ruleEditModal'; @endphp
<div id="ruleEditModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="ruleEditModalTitle" class="w-full max-w-2xl bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <form id="ruleEditForm" method="POST" data-rule-form>
                @csrf
                @method('PUT')
                <input type="hidden" name="_modal" value="ruleEditModal">
                <input type="hidden" name="_record" id="re_record" value="">
                <div class="flex items-start justify-between gap-3 {{ $cardHead }}">
                    <div>
                        <h2 id="ruleEditModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Edit Rule <span id="re_title_id"></span></h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Only rules no finalized payroll has used can be edited.</p>
                    </div>
                    <button type="button" onclick="closeModalById('ruleEditModal')" aria-label="Close dialog"
                            class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="grid grid-cols-1 gap-4 px-4 py-6 sm:px-6 md:grid-cols-2">
                    <div>
                        <label for="re_branch" class="{{ $labelClass }}">Branch</label>
                        <select id="re_branch" name="branch_id" class="{{ $inputClass }}" data-old="{{ $isOld ? old('branch_id') : '' }}">
                            <option value="">All branches</option>
                            @foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label for="re_target_type" class="{{ $labelClass }}">Applies to</label>
                        <select id="re_target_type" name="target_type" class="{{ $inputClass }}" data-target-type data-old="{{ $isOld ? old('target_type') : '' }}">
                            <option value="default">Default (any service)</option>
                            <option value="treatment">A treatment</option>
                            <option value="package">A package</option>
                        </select>
                    </div>
                    <div data-target-wrap class="md:col-span-2">
                        <label for="re_target_id" class="{{ $labelClass }}">Treatment / package</label>
                        <select id="re_target_id" name="target_id" class="{{ $inputClass }}" data-target-id data-old="{{ $isOld ? old('target_id') : '' }}"></select>
                    </div>
                    <div>
                        <label for="re_method" class="{{ $labelClass }}">Method</label>
                        <select id="re_method" name="method" class="{{ $inputClass }}" data-method data-old="{{ $isOld ? old('method') : '' }}">
                            <option value="percent">Percent of booking</option>
                            <option value="flat">Flat amount per booking</option>
                        </select>
                    </div>
                    <div>
                        <label for="re_value" class="{{ $labelClass }}"><span data-value-label>Value</span></label>
                        <input type="text" inputmode="decimal" id="re_value" name="value" required class="{{ $inputClass }}" value="{{ $isOld ? old('value') : '' }}">
                    </div>
                    <div>
                        <label for="re_from" class="{{ $labelClass }}">From</label>
                        <input type="date" id="re_from" name="effective_from" required class="{{ $inputClass }}" value="{{ $isOld ? old('effective_from') : '' }}">
                    </div>
                    <div>
                        <label for="re_to" class="{{ $labelClass }}">To <span class="font-normal text-gray-500">(optional)</span></label>
                        <input type="date" id="re_to" name="effective_to" class="{{ $inputClass }}" value="{{ $isOld ? old('effective_to') : '' }}">
                    </div>
                    @if($eb->any())
                        <ul class="space-y-1 text-xs text-red-600 md:col-span-2 dark:text-red-400">
                            @foreach($eb->all() as $m)<li>{{ $m }}</li>@endforeach
                        </ul>
                    @endif
                </div>
                <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeModalById('ruleEditModal')" class="w-full {{ $btn['neutral'] }} sm:w-auto">Cancel</button>
                        <button type="submit" class="w-full {{ $btn['primary'] }} sm:w-auto">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════
     RULE CHANGE-FROM-DATE MODAL
     ═══════════════════════════════════════════════════ --}}
@php $eb = $bag('ruleChange'); $isOld = $oldModal === 'ruleChangeModal'; @endphp
<div id="ruleChangeModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="ruleChangeModalTitle" class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <form id="ruleChangeForm" method="POST" data-rule-form>
                @csrf
                <input type="hidden" name="_modal" value="ruleChangeModal">
                <input type="hidden" name="_record" id="rch_record" value="">
                <div class="flex items-start justify-between gap-3 {{ $cardHead }}">
                    <div>
                        <h2 id="ruleChangeModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Change Rate From a Date</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><span id="rch_label"></span> — the current rule ends the day before, and a new rule starts on the date you pick.</p>
                    </div>
                    <button type="button" onclick="closeModalById('ruleChangeModal')" aria-label="Close dialog"
                            class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="grid grid-cols-1 gap-4 px-4 py-6 sm:px-6 sm:grid-cols-2">
                    <div>
                        <label for="rch_method" class="{{ $labelClass }}">Method</label>
                        <select id="rch_method" name="method" class="{{ $inputClass }}" data-method data-old="{{ $isOld ? old('method') : '' }}">
                            <option value="percent">Percent of booking</option>
                            <option value="flat">Flat amount per booking</option>
                        </select>
                    </div>
                    <div>
                        <label for="rch_value" class="{{ $labelClass }}"><span data-value-label>Value</span></label>
                        <input type="text" inputmode="decimal" id="rch_value" name="value" required class="{{ $inputClass }}" value="{{ $isOld ? old('value') : '' }}">
                    </div>
                    <div>
                        <label for="rch_from" class="{{ $labelClass }}">New rate starts</label>
                        <input type="date" id="rch_from" name="effective_from" required class="{{ $inputClass }}" value="{{ $isOld ? old('effective_from') : '' }}">
                    </div>
                    <div>
                        <label for="rch_to" class="{{ $labelClass }}">New rate ends <span class="font-normal text-gray-500">(optional)</span></label>
                        <input type="date" id="rch_to" name="effective_to" class="{{ $inputClass }}" value="{{ $isOld ? old('effective_to') : '' }}">
                    </div>
                    <p id="rch_lock" class="hidden text-xs sm:col-span-2 text-amber-700 dark:text-amber-300"></p>
                    @if($eb->any())
                        <ul class="space-y-1 text-xs text-red-600 sm:col-span-2 dark:text-red-400">
                            @foreach($eb->all() as $m)<li>{{ $m }}</li>@endforeach
                        </ul>
                    @endif
                </div>
                <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeModalById('ruleChangeModal')" class="w-full {{ $btn['neutral'] }} sm:w-auto">Cancel</button>
                        <button type="submit" class="w-full {{ $btn['primary'] }} sm:w-auto">Save New Rate</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════
     RULE END MODAL
     ═══════════════════════════════════════════════════ --}}
@php $eb = $bag('ruleEnd'); $isOld = $oldModal === 'ruleEndModal'; @endphp
<div id="ruleEndModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="ruleEndModalTitle" class="w-full max-w-md bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <form id="ruleEndForm" method="POST">
                @csrf
                <input type="hidden" name="_modal" value="ruleEndModal">
                <input type="hidden" name="_record" id="rend_record" value="">
                <div class="px-4 py-6 space-y-4 sm:px-6">
                    <div>
                        <h2 id="ruleEndModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">End Rule</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><span id="rend_label"></span>. Bookings after the end date fall through to the next rule in the order.</p>
                    </div>
                    <div>
                        <label for="rend_to" class="{{ $labelClass }}">Last day this rule applies</label>
                        <input type="date" id="rend_to" name="effective_to" required class="{{ $inputClass }}" value="{{ $isOld ? old('effective_to') : '' }}">
                        <p id="rend_lock" class="hidden mt-1 text-xs text-amber-700 dark:text-amber-300"></p>
                        @if($eb->any())<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $eb->first() }}</p>@endif
                    </div>
                    <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeModalById('ruleEndModal')" class="w-full {{ $btn['neutral'] }} sm:w-auto">Cancel</button>
                        <button type="submit" class="w-full {{ $btn['warn'] }} sm:w-auto">End Rule</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════
     RULE DELETE MODAL
     ═══════════════════════════════════════════════════ --}}
<div id="ruleDeleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="alertdialog" aria-modal="true" aria-labelledby="ruleDeleteModalTitle" aria-describedby="ruleDeleteModalDesc"
             class="w-full max-w-md p-6 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <h2 id="ruleDeleteModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Delete Rule</h2>
            <p id="ruleDeleteModalDesc" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                This permanently deletes <span id="rdel_label" class="font-medium text-gray-900 dark:text-white"></span>.
                Draft runs that used it must be regenerated. To stop a rule but keep its history, use “End” instead.
            </p>
            <div class="flex flex-col-reverse gap-2 mt-6 sm:flex-row sm:justify-end">
                <button type="button" onclick="closeModalById('ruleDeleteModal')" class="w-full {{ $btn['neutral'] }} sm:w-auto">Keep Rule</button>
                <form id="ruleDeleteForm" method="POST" class="sm:w-auto">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="_modal" value="">
                    <button type="submit" class="w-full {{ $btn['remove'] }} sm:w-auto">Yes, Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

<script>
// Alpine component for the tab switcher — same behaviour as branches/edit.blade.php.
function payrollSetupPage() {
    return {
        tab: @json($tab),
        init() {
            this.$watch('tab', (value) => {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', value);
                window.history.replaceState({}, '', url.toString());
            });
        },
        cycleTab(direction) {
            const order = @json(array_keys($tabs));
            const next = (order.indexOf(this.tab) + direction + order.length) % order.length;
            this.tab = order[next];
            this.$nextTick(() => document.getElementById('tab-' + this.tab)?.focus());
        },
    };
}

(function () {
    'use strict';

    // One PHP source → Blade and JS.
    const TIER_META  = @json($tierMeta);
    const BADGE_BASE = @json($badgeBase);
    const TARGETS = {
        treatment: @json($liveTreatments->map(fn ($t) => ['id' => $t->id, 'name' => $t->name . ' — ' . ($branchNames[$t->branch_id] ?? 'no branch')])->values()),
        package:   @json($livePackages->map(fn ($p) => ['id' => $p->id, 'name' => $p->name . ' — ' . ($branchNames[$p->branch_id] ?? 'no branch')])->values()),
    };
    const TARGET_NAMES = @json($targetNames);
    const BRANCH_NAMES = @json($branchNames);
    const ROUTE_PREVIEW = @json(route('payroll.setup.rules.preview'));

    function fmtDate(ymd) {
        if (!ymd) return 'open';
        const d = new Date(ymd + 'T00:00:00');
        return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
    }
    function fmtValue(method, value) {
        const n = Number(value);
        return method === 'percent'
            ? String(parseFloat(value)) + '%'
            : '₱' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' flat';
    }
    function dayAfter(ymd) {
        const d = new Date(ymd + 'T00:00:00'); d.setDate(d.getDate() + 1);
        return [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
    }

    // ════════════════════════════════════════════════════════════════
    // RULE FORMS — target list follows the "Applies to" choice;
    // value label follows the method.
    // ════════════════════════════════════════════════════════════════
    function wireRuleForm(form) {
        const typeSel   = form.querySelector('[data-target-type]');
        const idSel     = form.querySelector('[data-target-id]');
        const wrap      = form.querySelector('[data-target-wrap]');
        const methodSel = form.querySelector('[data-method]');
        const valLabel  = form.querySelector('[data-value-label]');

        function fillTargets(selected) {
            if (!typeSel || !idSel) return;
            const type = typeSel.value;
            const list = TARGETS[type] || [];
            idSel.innerHTML = '';
            const hasTarget = type === 'treatment' || type === 'package';
            wrap.classList.toggle('hidden', !hasTarget);
            idSel.disabled = !hasTarget;
            idSel.required = hasTarget;
            if (!hasTarget) return;
            const ph = document.createElement('option');
            ph.value = ''; ph.textContent = 'Choose a ' + type;
            idSel.appendChild(ph);
            list.forEach(t => {
                const o = document.createElement('option');
                o.value = t.id; o.textContent = t.name;
                idSel.appendChild(o);
            });
            // A rule may point at a since-deleted service; keep it selectable when editing.
            if (selected && !list.some(t => String(t.id) === String(selected))) {
                const o = document.createElement('option');
                o.value = selected; o.textContent = (TARGET_NAMES[type] || {})[selected] || ('#' + selected);
                idSel.appendChild(o);
            }
            idSel.value = selected ? String(selected) : '';
        }
        function syncMethod() {
            if (!methodSel || !valLabel) return;
            valLabel.textContent = methodSel.value === 'percent' ? 'Percent (0–100)' : 'Amount per booking (₱)';
        }

        if (typeSel) typeSel.addEventListener('change', () => fillTargets(''));
        if (methodSel) methodSel.addEventListener('change', syncMethod);
        form._fillTargets = fillTargets;
        form._syncMethod  = syncMethod;
        fillTargets(idSel ? idSel.dataset.old : '');
        syncMethod();
    }
    document.querySelectorAll('[data-rule-form]').forEach(wireRuleForm);

    // ════════════════════════════════════════════════════════════════
    // "WHICH RULE APPLIES" PREVIEW
    // ════════════════════════════════════════════════════════════════
    const previewForm = document.getElementById('previewForm');
    const previewTarget = document.getElementById('preview_target');
    const previewBranch = document.getElementById('preview_branch');
    if (previewTarget && previewBranch) {
        // Default the branch to the one that owns the chosen treatment/package.
        previewTarget.addEventListener('change', () => {
            const b = previewTarget.selectedOptions[0]?.dataset.branch;
            if (b && previewBranch.querySelector('option[value="' + b + '"]')) previewBranch.value = b;
        });
    }
    if (previewForm) {
        previewForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const box   = document.getElementById('previewResult');
            const tier  = document.getElementById('previewTier');
            const idEl  = document.getElementById('previewRuleId');
            const text  = document.getElementById('previewText');
            const range = document.getElementById('previewRange');
            const btn   = document.getElementById('previewSubmit');

            const params = new URLSearchParams({
                branch_id: previewBranch.value,
                target: previewTarget.value,
                date: document.getElementById('preview_date').value,
            });
            btn.disabled = true;
            try {
                const res = await fetch(ROUTE_PREVIEW + '?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                box.classList.remove('hidden');
                if (!res.ok) {
                    tier.className = BADGE_BASE + ' bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
                    tier.textContent = 'Error';
                    idEl.textContent = '';
                    text.textContent = data.message || 'Could not check this combination.';
                    range.textContent = '';
                    return;
                }
                if (!data.rule) {
                    tier.className = BADGE_BASE + ' bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
                    tier.textContent = 'No rule';
                    idEl.textContent = '';
                    text.textContent = 'No commission — no rule matches this service, branch and date.';
                    range.textContent = '';
                    return;
                }
                const meta = TIER_META[data.tier];
                const r = data.rule;
                tier.className = BADGE_BASE + ' ' + meta.class;
                tier.textContent = meta.rank + ' · ' + meta.label;
                idEl.textContent = 'Rule #' + r.id;
                const target = r.target_type === 'default'
                    ? 'default'
                    : (r.target_type + ' ' + ((TARGET_NAMES[r.target_type] || {})[r.target_id] || ('#' + r.target_id)));
                text.textContent = fmtValue(r.method, r.value) + ' — ' + target + ', ' + (r.branch_id ? (BRANCH_NAMES[r.branch_id] || 'branch') : 'all branches');
                range.textContent = 'Effective ' + fmtDate(r.effective_from) + ' – ' + fmtDate(r.effective_to);
            } catch (err) {
                box.classList.remove('hidden');
                tier.className = BADGE_BASE + ' bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
                tier.textContent = 'Error';
                text.textContent = 'Network error — try again.';
                idEl.textContent = ''; range.textContent = '';
            } finally {
                btn.disabled = false;
            }
        });
    }

@if($canEdit)
    // ════════════════════════════════════════════════════════════════
    // SHARED MODAL BEHAVIOUR (same as staff/index.blade.php)
    // ════════════════════════════════════════════════════════════════
    const MODAL_IDS = ['wageModal', 'ruleEditModal', 'ruleChangeModal', 'ruleEndModal', 'ruleDeleteModal'];
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
    // Backdrop closes the delete confirmation only; form modals hold typed values.
    ['ruleDeleteModal'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('click', e => { if (e.target === el) closeModal(id); });
    });

    const ROUTES = {
        wage:   @json(route('payroll.setup.wages.update', '__ID__')),
        edit:   @json(route('payroll.setup.rules.update', '__ID__')),
        change: @json(route('payroll.setup.rules.supersede', '__ID__')),
        end:    @json(route('payroll.setup.rules.end', '__ID__')),
        del:    @json(route('payroll.setup.rules.destroy', '__ID__')),
    };
    const routeFor = (t, id) => t.replace('__ID__', encodeURIComponent(id));
    const rowData = (btn) => JSON.parse(btn.dataset.rule || btn.dataset.wage || '{}');

    // Re-opened after a validation error: keep the typed (old) values.
    let restoring = false;

    function openWageModal(btn) {
        const d = rowData(btn);
        document.getElementById('wageForm').action = routeFor(ROUTES.wage, d.id);
        document.getElementById('wage_record').value = d.id;
        document.getElementById('wageBranchName').textContent = d.name;
        if (!restoring) {
            document.getElementById('wage_amount').value = d.min_daily_wage || '';
            document.getElementById('wage_ref').value = d.wage_order_ref || '';
            document.getElementById('wage_from').value = d.min_wage_effective_from || '';
        }
        openModal('wageModal', '#wage_amount');
    }

    function openRuleEditModal(btn) {
        const d = rowData(btn);
        const form = document.getElementById('ruleEditForm');
        form.action = routeFor(ROUTES.edit, d.id);
        document.getElementById('re_record').value = d.id;
        document.getElementById('re_title_id').textContent = '#' + d.id;
        const pick = (id, fallback) => { const el = document.getElementById(id); return restoring && el.dataset.old !== undefined && el.dataset.old !== '' ? el.dataset.old : fallback; };
        document.getElementById('re_branch').value = pick('re_branch', d.branch_id ?? '') ?? '';
        document.getElementById('re_target_type').value = pick('re_target_type', d.target_type);
        document.getElementById('re_method').value = pick('re_method', d.method);
        form._fillTargets(pick('re_target_id', d.target_id ?? ''));
        form._syncMethod();
        if (!restoring) {
            document.getElementById('re_value').value = d.value;
            document.getElementById('re_from').value = d.effective_from;
            document.getElementById('re_to').value = d.effective_to || '';
        }
        openModal('ruleEditModal', '#re_branch');
    }

    function lockNote(el, d, verb) {
        if (d.locked_through) {
            el.textContent = 'Paid by a finalized run through ' + fmtDate(d.locked_through) + ' — the ' + verb + ' must be on or after ' + fmtDate(verb === 'end date' ? d.locked_through : dayAfter(d.locked_through)) + '.';
            el.classList.remove('hidden');
        } else {
            el.classList.add('hidden');
        }
    }

    function openRuleChangeModal(btn) {
        const d = rowData(btn);
        const form = document.getElementById('ruleChangeForm');
        form.action = routeFor(ROUTES.change, d.id);
        document.getElementById('rch_record').value = d.id;
        document.getElementById('rch_label').textContent = 'Rule #' + d.id + ' (' + d.label + ', ' + fmtValue(d.method, d.value) + ')';
        const m = document.getElementById('rch_method');
        m.value = restoring && m.dataset.old ? m.dataset.old : d.method;
        form._syncMethod();
        if (!restoring) {
            document.getElementById('rch_value').value = '';
            document.getElementById('rch_to').value = '';
            const today = @json($today);
            const earliest = d.locked_through ? dayAfter(d.locked_through) : dayAfter(d.effective_from);
            document.getElementById('rch_from').value = today > earliest ? today : earliest;
        }
        lockNote(document.getElementById('rch_lock'), d, 'new start date');
        openModal('ruleChangeModal', '#rch_value');
    }

    function openRuleEndModal(btn) {
        const d = rowData(btn);
        document.getElementById('ruleEndForm').action = routeFor(ROUTES.end, d.id);
        document.getElementById('rend_record').value = d.id;
        document.getElementById('rend_label').textContent = 'Rule #' + d.id + ' (' + d.label + ', ' + fmtValue(d.method, d.value) + ')';
        if (!restoring) {
            const today = @json($today);
            const earliest = d.locked_through && d.locked_through > d.effective_from ? d.locked_through : d.effective_from;
            document.getElementById('rend_to').value = today > earliest ? today : earliest;
        }
        lockNote(document.getElementById('rend_lock'), d, 'end date');
        openModal('ruleEndModal', '#rend_to');
    }

    function openRuleDeleteModal(btn) {
        const d = rowData(btn);
        document.getElementById('ruleDeleteForm').action = routeFor(ROUTES.del, d.id);
        document.getElementById('rdel_label').textContent = 'rule #' + d.id + ' (' + d.label + ')';
        openModal('ruleDeleteModal', 'button[type="button"]');
    }

    window.openWageModal       = openWageModal;
    window.openRuleEditModal   = openRuleEditModal;
    window.openRuleChangeModal = openRuleChangeModal;
    window.openRuleEndModal    = openRuleEndModal;
    window.openRuleDeleteModal = openRuleDeleteModal;
    window.closeModalById      = closeModal;

    // Re-open the modal a validation error belongs to, with the typed values.
    const OLD_MODAL  = @json($oldModal);
    const OLD_RECORD = @json($oldRecord);
    const OPENERS = { wageModal: [openWageModal, 'data-wage'], ruleEditModal: [openRuleEditModal, 'data-rule'],
                      ruleChangeModal: [openRuleChangeModal, 'data-rule'], ruleEndModal: [openRuleEndModal, 'data-rule'] };
    if (OLD_MODAL && OPENERS[OLD_MODAL] && OLD_RECORD) {
        const [fn, attr] = OPENERS[OLD_MODAL];
        const src = Array.from(document.querySelectorAll('[' + attr + ']'))
            .find(b => String(JSON.parse(b.getAttribute(attr)).id) === String(OLD_RECORD));
        if (src) { restoring = true; fn(src); restoring = false; }
    }

    // Double-submit guard for every POST form on the page.
    document.querySelectorAll('form[method="POST"]').forEach(f => {
        let submitting = false;
        f.addEventListener('submit', e => {
            if (submitting) { e.preventDefault(); return; }
            if (!f.checkValidity()) return;
            submitting = true;
            f.querySelectorAll('button[type="submit"]').forEach(b => { b.disabled = true; b.classList.add('opacity-70', 'cursor-not-allowed'); });
        });
    });
@endif
}());
</script>

<style>
[x-cloak] { display: none !important; }

/* Selected tab — same treatment as .branch-tab in branches/edit.blade.php */
.setup-tab[aria-selected="true"] {
    background-image: linear-gradient(to right, #7A6348, #6F5430);
    color: #ffffff;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

@media (max-width: 767px) {
    .rt, .rt tbody, .rt tr, .rt td { display: block; width: 100%; }
    .rt thead { display: none; }
    .rt tr { padding: 0.75rem 1rem; }
    .rt td { padding: 0.375rem 0 !important; text-align: left !important; }
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
    .rt td.rt-empty { padding: 2rem 0 !important; text-align: center !important; }
}
@media (max-width: 767px) and (prefers-color-scheme: dark) {
    .rt td[data-label]::before { color: #9ca3af; }
}
</style>
@endsection
