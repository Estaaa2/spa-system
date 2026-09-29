@extends('layouts.app')

@section('title', 'Pay Setup — ' . $name)
@section('content')
@php
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
    $errText    = 'mt-1 text-xs text-red-600 dark:text-red-400';

    $dayNames = \App\Models\StaffPayProfile::DAY_NAMES;
    $freqLabels = [
        'per_cutoff'         => 'Every cutoff',
        'per_day_worked'     => 'Per day worked',
        'second_cutoff_only' => '2nd cutoff only',
    ];
    $componentLabels = [
        'ALLOWANCE'       => 'Allowance',
        'LOAN_DEDUCTION'  => 'Loan deduction',
        'OTHER_DEDUCTION' => 'Other deduction',
    ];
    $recurringCodes = \App\Services\Payroll\PayrollSetupService::RECURRING_CODES;
    $idStateMeta = [
        'set'        => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'missing'    => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'unreadable' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    ];

    $current = $profiles->first(fn ($p) => $p->effective_from->toDateString() <= $today
        && ($p->effective_to === null || $p->effective_to->toDateString() >= $today));
    $currentCheck = $current ? $wageChecks[$current->id] : null;
    $idsComplete = collect($ids)->every(fn ($i) => $i['state'] === 'set');
    $activeItems = $recurring->filter(fn ($i) => $i->is_active && $i->start_date->toDateString() <= $today
        && ($i->end_date === null || $i->end_date->toDateString() >= $today))->count();

    $bag = fn (string $n) => $errors->getBag($n);
    $oldModal  = old('_modal');
    $oldRecord = old('_record');
    $role = $staff->user?->getRoleNames()->first();
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl">
    <x-page-header :title="$name" :subtitle="($role ? ucfirst($role) . ' · ' : '') . ($home?->name ?? 'No home branch')">
        <x-slot name="right">
            <a href="{{ route('payroll.staff.index') }}" class="{{ $btn['edit'] }}">
                <i class="text-xs fa-solid fa-arrow-left" aria-hidden="true"></i> All Staff
            </a>
        </x-slot>
    </x-page-header>

    @unless($inPayroll)
        <div class="p-4 border border-amber-200 rounded-2xl bg-amber-50 dark:bg-amber-900/10 dark:border-amber-800">
            <p class="text-sm text-amber-800 dark:text-amber-300">
                <i class="mr-1 fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                Not included in payroll — {{ $home ? "home branch “{$home->name}” does not have the Workforce & Finance Suite" : 'no home branch is assigned' }}. You can still prepare the setup below.
            </p>
        </div>
    @endunless

    @if(session('warning'))
        <div role="status" class="p-4 border border-amber-200 rounded-2xl bg-amber-50 dark:bg-amber-900/10 dark:border-amber-800">
            <p class="text-sm text-amber-800 dark:text-amber-300"><i class="mr-1 fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ session('warning') }}</p>
        </div>
    @endif

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Current Pay</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">{{ $current ? '₱' . number_format((float) $current->base_rate, 2) : '—' }}</h3>
                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">{{ $current ? 'per ' . ($current->pay_basis === 'monthly' ? 'month' : 'day') : 'No profile today' }}</span>
            </div>
        </div>
        <div class="p-4 border shadow-sm sm:p-5 rounded-2xl {{ $currentCheck && $currentCheck['below'] ? 'bg-amber-50 border-amber-200 dark:bg-amber-900/10 dark:border-amber-800' : 'bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-700' }}">
            <p class="text-xs font-semibold tracking-wide uppercase {{ $currentCheck && $currentCheck['below'] ? 'text-amber-700 dark:text-amber-300' : 'text-gray-500 dark:text-gray-400' }}">Daily Rate vs Min. Wage</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold sm:text-3xl {{ $currentCheck && $currentCheck['below'] ? 'text-amber-900 dark:text-amber-200' : 'text-gray-900 dark:text-white' }}">
                    {{ $currentCheck && $currentCheck['daily'] !== null ? '₱' . number_format((float) $currentCheck['daily'], 2) : '—' }}
                </h3>
                <span class="text-xs sm:text-sm {{ $currentCheck && $currentCheck['below'] ? 'text-amber-700 dark:text-amber-300' : 'text-gray-500 dark:text-gray-400' }}">
                    {{ $minDaily !== null ? 'min ₱' . number_format((float) $minDaily, 0) : 'No branch min' }}
                </span>
            </div>
        </div>
        <div class="p-4 border shadow-sm sm:p-5 rounded-2xl {{ $idsComplete ? 'bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-700' : 'bg-amber-50 border-amber-200 dark:bg-amber-900/10 dark:border-amber-800' }}">
            <p class="text-xs font-semibold tracking-wide uppercase {{ $idsComplete ? 'text-gray-500 dark:text-gray-400' : 'text-amber-700 dark:text-amber-300' }}">Statutory IDs</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold sm:text-3xl {{ $idsComplete ? 'text-gray-900 dark:text-white' : 'text-amber-900 dark:text-amber-200' }}">{{ collect($ids)->where('state', 'set')->count() }}/4</h3>
                <span class="text-xs sm:text-sm {{ $idsComplete ? 'text-gray-500 dark:text-gray-400' : 'text-amber-700 dark:text-amber-300' }}">{{ $idsComplete ? 'Complete' : 'Missing numbers' }}</span>
            </div>
        </div>
        <div class="p-4 bg-white border border-gray-200 shadow-sm sm:p-5 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Recurring Items</p>
            <div class="flex flex-col mt-3 sm:flex-row sm:items-end sm:justify-between">
                <h3 class="text-2xl font-semibold text-gray-900 sm:text-3xl dark:text-white">{{ $activeItems }}</h3>
                <span class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">Active today</span>
            </div>
        </div>
    </div>

    {{-- ═════════════════════════════════════════════════════════════════
         PAY PROFILES
         ═════════════════════════════════════════════════════════════════ --}}
    <section id="profiles" class="{{ $card }}" aria-labelledby="profilesTitle">
        <div class="{{ $cardHead }}">
            <h2 id="profilesTitle" class="text-base font-semibold text-gray-900 dark:text-white">Pay Profile</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Each profile covers a date range. To change pay, add a new profile from the date it takes effect — the previous one ends the day before.
                Profiles used by a finalized payroll run are locked.
            </p>
        </div>

        @if($canEdit)
            @php $eb = $bag('profileCreate'); @endphp
            <form method="POST" action="{{ route('payroll.staff.profiles.store', $staff) }}" class="p-4 space-y-4 border-b border-gray-200 sm:p-6 dark:border-gray-700" data-profile-form>
                @csrf
                <input type="hidden" name="_modal" value="">
                <p class="text-sm font-semibold text-gray-700 dark:text-white">New profile from date</p>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div>
                        <label for="pc_from" class="{{ $labelClass }}">Effective from</label>
                        <input type="date" id="pc_from" name="effective_from" required class="{{ $inputClass }}" value="{{ $oldModal ? $today : old('effective_from', $today) }}">
                        @if($eb->has('effective_from'))<p class="{{ $errText }}">{{ $eb->first('effective_from') }}</p>@endif
                    </div>
                    <div>
                        <label for="pc_basis" class="{{ $labelClass }}">Pay basis</label>
                        <select id="pc_basis" name="pay_basis" class="{{ $inputClass }}" data-basis>
                            <option value="daily" @selected(($oldModal ? 'daily' : old('pay_basis', 'daily')) === 'daily')>Daily-paid</option>
                            <option value="monthly" @selected(!$oldModal && old('pay_basis') === 'monthly')>Monthly-paid</option>
                        </select>
                    </div>
                    <div>
                        <label for="pc_rate" class="{{ $labelClass }}"><span data-rate-label>Daily rate (₱)</span></label>
                        <input type="text" inputmode="decimal" id="pc_rate" name="base_rate" required class="{{ $inputClass }}" value="{{ $oldModal ? '' : old('base_rate') }}" placeholder="e.g. 600 (0 allowed)" aria-describedby="pc_rate_hint">
                        @if($eb->has('base_rate'))<p class="{{ $errText }}">{{ $eb->first('base_rate') }}</p>@endif
                    </div>
                    <div class="flex items-end">
                        <label class="inline-flex items-center gap-3 min-h-[44px] text-sm text-gray-700 cursor-pointer dark:text-gray-300">
                            <input type="hidden" name="commission_enabled" value="0">
                            <input type="checkbox" name="commission_enabled" value="1" class="w-5 h-5 border-gray-300 rounded text-[#8B7355] focus:ring-[#8B7355] dark:border-gray-600 dark:bg-gray-700"
                                   @checked($oldModal ? false : old('commission_enabled', '0') === '1')>
                            Earns commission
                        </label>
                    </div>
                </div>
                <p id="pc_rate_hint" class="text-xs text-gray-500 dark:text-gray-400" data-rate-hint
                   data-daily="{{ $minDaily !== null ? 'Branch minimum: ₱' . number_format((float) $minDaily, 2) . ' a day. A lower rate is allowed — payroll tops each worked day up to the minimum.' : 'The home branch has no minimum wage set yet.' }}"
                   data-monthly="{{ $minMonthly !== null ? 'Branch minimum is about ₱' . number_format((float) $minMonthly, 2) . ' a month (daily minimum × 365 ÷ 12). A lower rate is allowed — payroll tops each worked day up.' : 'The home branch has no minimum wage set yet.' }}"></p>
                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Weekly rest days</legend>
                    <div class="flex flex-wrap gap-2">
                        @php $oldRest = $oldModal ? ['Sunday'] : (array) old('rest_days', ['Sunday']); @endphp
                        @foreach($dayNames as $d)
                            <label class="inline-flex items-center gap-2 min-h-[44px] px-3 text-sm border border-gray-300 cursor-pointer rounded-xl dark:border-gray-600 dark:text-gray-300 has-[:checked]:border-[#8B7355] has-[:checked]:bg-[#8B7355]/10">
                                <input type="checkbox" name="rest_days[]" value="{{ $d }}" @checked(in_array($d, $oldRest, true))
                                       class="w-4 h-4 border-gray-300 rounded text-[#8B7355] focus:ring-[#8B7355] dark:border-gray-600 dark:bg-gray-700">
                                {{ substr($d, 0, 3) }}
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Daily-paid staff need 1 or 2 rest days (only those have a DOLE days-per-year factor).</p>
                    @if($eb->has('rest_days'))<p class="{{ $errText }}">{{ $eb->first('rest_days') }}</p>@endif
                </fieldset>
                @foreach(['pay_basis', 'commission_enabled', 'profile'] as $f)
                    @if($eb->has($f))<p class="{{ $errText }}">{{ $eb->first($f) }}</p>@endif
                @endforeach
                <div class="flex sm:justify-end">
                    <button type="submit" class="w-full {{ $btn['primary'] }} sm:w-auto"><i class="fa-solid fa-plus" aria-hidden="true"></i> Save Profile</button>
                </div>
            </form>
        @endif

        @if($bag('profileDelete')->any())
            <p class="px-6 pt-4 text-sm text-red-600 dark:text-red-400">{{ $bag('profileDelete')->first() }}</p>
        @endif
        <div class="md:overflow-x-auto">
            <table role="table" class="rt min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader" class="{{ $th }}">Effective</th>
                        <th role="columnheader" class="{{ $th }}">Pay</th>
                        <th role="columnheader" class="{{ $th }}">Commission</th>
                        <th role="columnheader" class="{{ $th }}">Rest Days</th>
                        <th role="columnheader" class="{{ $th }}">Status</th>
                        @if($canEdit)<th role="columnheader" class="{{ $th }}">Actions</th>@endif
                    </tr>
                </thead>
                <tbody role="rowgroup" class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    @forelse($profiles as $p)
                        @php
                            $u = $profileUsage[$p->id] ?? ['locked_through' => null, 'pending' => 0];
                            $chk = $wageChecks[$p->id];
                            $isCurrent = $current && $current->id === $p->id;
                            $pRow = [
                                'id' => $p->id, 'effective_from' => $p->effective_from->toDateString(), 'effective_to' => $p->effective_to?->toDateString(),
                                'pay_basis' => $p->pay_basis, 'base_rate' => (string) $p->base_rate, 'commission_enabled' => $p->commission_enabled,
                                'rest_days' => $p->rest_days ?? [],
                            ];
                        @endphp
                        <tr role="row" class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td role="cell" data-label="Effective" class="px-6 py-4 text-sm text-gray-700 whitespace-nowrap dark:text-gray-300">
                                {{ $p->effective_from->format('M d, Y') }} – {{ $p->effective_to?->format('M d, Y') ?? 'open' }}
                            </td>
                            <td role="cell" data-label="Pay" class="px-6 py-4">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">₱{{ number_format((float) $p->base_rate, 2) }} / {{ $p->pay_basis === 'monthly' ? 'month' : 'day' }}</p>
                                @if($p->pay_basis === 'monthly' && $chk['daily'] !== null)
                                    <p class="text-xs text-gray-500 dark:text-gray-400">≈ ₱{{ number_format((float) $chk['daily'], 2) }} / day</p>
                                @endif
                                @if($chk['below'])
                                    <span class="{{ $badgeBase }} mt-1 bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300" title="Payroll adds a minimum-wage top-up for each worked day">
                                        <i class="fa-solid fa-arrow-down text-[10px]" aria-hidden="true"></i> Below min. wage (₱{{ number_format((float) $chk['min'], 2) }})
                                    </span>
                                @endif
                            </td>
                            <td role="cell" data-label="Commission" class="px-6 py-4">
                                <span class="{{ $badgeBase }} {{ $p->commission_enabled ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">{{ $p->commission_enabled ? 'On' : 'Off' }}</span>
                            </td>
                            <td role="cell" data-label="Rest Days" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ collect($p->rest_days ?? [])->map(fn ($d) => substr($d, 0, 3))->implode(', ') ?: '—' }}
                            </td>
                            <td role="cell" data-label="Status" class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @if($isCurrent)
                                        <span class="{{ $badgeBase }} bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Current</span>
                                    @elseif($p->effective_from->toDateString() > $today)
                                        <span class="{{ $badgeBase }} bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">Scheduled</span>
                                    @else
                                        <span class="{{ $badgeBase }} bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">Past</span>
                                    @endif
                                    @if($u['locked_through'])
                                        <span class="{{ $badgeBase }} bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300" title="Used by a finalized run through {{ $u['locked_through'] }}">
                                            <i class="fa-solid fa-lock text-[10px]" aria-hidden="true"></i> Paid through {{ \Illuminate\Support\Carbon::parse($u['locked_through'])->format('M d') }}
                                        </span>
                                    @endif
                                    @if($u['pending'] > 0)
                                        <span class="{{ $badgeBase }} bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">In {{ $u['pending'] }} draft/approved run(s)</span>
                                    @endif
                                </div>
                            </td>
                            @if($canEdit)
                                <td role="cell" data-label="Actions" class="px-6 py-4 rt-actions">
                                    @if($u['locked_through'])
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Locked — add a new profile to change</span>
                                    @else
                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" class="{{ $btn['edit'] }}" data-profile='@json($pRow)' onclick="openProfileEditModal(this)">
                                                <i class="text-xs fa-solid fa-pen" aria-hidden="true"></i><span>Edit</span>
                                            </button>
                                            <button type="button" class="{{ $btn['remove'] }}" data-profile='@json($pRow)' onclick="openProfileDeleteModal(this)">
                                                <i class="text-xs fa-solid fa-trash" aria-hidden="true"></i><span>Delete</span>
                                            </button>
                                        </div>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr role="row">
                            <td role="cell" colspan="{{ $canEdit ? 6 : 5 }}" class="px-6 py-12 text-center text-gray-500 rt-empty dark:text-gray-400">
                                <i class="mb-3 text-4xl text-gray-400 fa-solid fa-file-invoice-dollar" aria-hidden="true"></i>
                                <p class="text-gray-600 dark:text-gray-400">No pay profile yet — this staff member is not paid until one is added.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($lastFinalized)
            <p class="px-4 py-3 text-xs text-gray-500 border-t border-gray-200 sm:px-6 dark:border-gray-700 dark:text-gray-400">
                Payroll is finalized for this staff member through {{ \Illuminate\Support\Carbon::parse($lastFinalized)->format('M d, Y') }}. Changes never recompute finalized runs — correct past pay with an adjustment line in a later run.
            </p>
        @endif
    </section>

    {{-- ═════════════════════════════════════════════════════════════════
         STATUTORY IDS
         ═════════════════════════════════════════════════════════════════ --}}
    <section id="ids" class="{{ $card }}" aria-labelledby="idsTitle">
        <div class="flex flex-col gap-3 {{ $cardHead }} sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 id="idsTitle" class="text-base font-semibold text-gray-900 dark:text-white">Statutory IDs</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Stored encrypted. Contributions and tax are computed without them, but remittance needs every number.</p>
            </div>
            @if($canEdit)
                <div class="flex flex-wrap gap-2 shrink-0">
                    <button type="button" id="revealIds" class="{{ $btn['neutral'] }}" aria-pressed="false" onclick="toggleReveal()">
                        <i class="text-xs fa-solid fa-eye" aria-hidden="true"></i><span>Reveal</span>
                    </button>
                    <button type="button" class="{{ $btn['edit'] }}" onclick="openIdsModal()">
                        <i class="text-xs fa-solid fa-pen" aria-hidden="true"></i><span>Edit IDs</span>
                    </button>
                </div>
            @endif
        </div>
        <dl class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4 sm:p-6">
            @foreach($ids as $attr => $i)
                <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-900/40">
                    <dt class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">{{ $i['label'] }}</dt>
                    <dd class="mt-2">
                        @if($i['state'] === 'set')
                            <span class="font-mono text-sm text-gray-900 dark:text-white" data-masked>{{ $i['masked'] }}</span>
                            @if($canEdit)
                                {{-- Filled only by the logged Reveal request; never rendered server-side. --}}
                                <span class="hidden font-mono text-sm text-gray-900 dark:text-white" data-plain="{{ $attr }}"></span>
                            @endif
                        @else
                            <span class="{{ $badgeBase }} {{ $idStateMeta[$i['state']] }}">{{ $i['state'] === 'missing' ? 'Missing' : 'Unreadable — re-enter' }}</span>
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
        @if($idWarnings !== [])
            <div class="px-4 pb-4 sm:px-6">
                <ul class="p-4 space-y-1 text-sm border border-amber-200 rounded-2xl bg-amber-50 text-amber-800 dark:bg-amber-900/10 dark:border-amber-800 dark:text-amber-300">
                    @foreach($idWarnings as $w)<li><i class="mr-1 fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $w }}</li>@endforeach
                </ul>
            </div>
        @endif
        <p id="revealError" role="alert" class="hidden px-6 pb-4 text-sm text-red-600 dark:text-red-400"></p>
        @if($bag('ids')->any())
            <p class="px-6 pb-4 text-sm text-red-600 dark:text-red-400">{{ $bag('ids')->first() }}</p>
        @endif
        <p class="px-4 pb-4 text-xs text-gray-500 sm:px-6 dark:text-gray-400">Viewing the full numbers is recorded (who and when).</p>
    </section>

    {{-- ═════════════════════════════════════════════════════════════════
         RECURRING ITEMS
         ═════════════════════════════════════════════════════════════════ --}}
    <section id="recurring" class="{{ $card }}" aria-labelledby="recurringTitle">
        <div class="{{ $cardHead }}">
            <h2 id="recurringTitle" class="text-base font-semibold text-gray-900 dark:text-white">Recurring Allowances &amp; Deductions</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Added to every payroll run while active. A loan is a fixed deduction with an end date (balances are not tracked).
                Deductions need the employee's written authorization (Labor Code Art. 113).
            </p>
        </div>

        @if($canEdit)
            @php $eb = $bag('recurringCreate'); $o = fn ($k, $d = '') => $oldModal ? $d : old($k, $d); @endphp
            <form method="POST" action="{{ route('payroll.staff.recurring.store', $staff) }}" id="recurringForm" class="p-4 space-y-4 border-b border-gray-200 sm:p-6 dark:border-gray-700">
                @csrf
                <input type="hidden" name="_modal" value="">
                <p class="text-sm font-semibold text-gray-700 dark:text-white">Add item</p>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div>
                        <label for="ri_kind" class="{{ $labelClass }}">Type</label>
                        <select id="ri_kind" name="kind" class="{{ $inputClass }}">
                            <option value="earning" @selected($o('kind', 'earning') === 'earning')>Allowance (earning)</option>
                            <option value="deduction" @selected($o('kind') === 'deduction')>Deduction</option>
                        </select>
                    </div>
                    <div>
                        <label for="ri_code" class="{{ $labelClass }}">Component</label>
                        <select id="ri_code" name="component_code" class="{{ $inputClass }}" data-old="{{ $o('component_code') }}"></select>
                        @if($eb->has('component_code'))<p class="{{ $errText }}">{{ $eb->first('component_code') }}</p>@endif
                    </div>
                    <div class="md:col-span-2">
                        <label for="ri_label" class="{{ $labelClass }}">Label</label>
                        <input type="text" id="ri_label" name="label" required maxlength="100" class="{{ $inputClass }}" value="{{ $o('label') }}" placeholder="e.g. Transportation allowance">
                        @if($eb->has('label'))<p class="{{ $errText }}">{{ $eb->first('label') }}</p>@endif
                    </div>
                    <div>
                        <label for="ri_amount" class="{{ $labelClass }}">Amount (₱)</label>
                        <input type="text" inputmode="decimal" id="ri_amount" name="amount" required class="{{ $inputClass }}" value="{{ $o('amount') }}">
                        @if($eb->has('amount'))<p class="{{ $errText }}">{{ $eb->first('amount') }}</p>@endif
                    </div>
                    <div>
                        <label for="ri_freq" class="{{ $labelClass }}">Frequency</label>
                        <select id="ri_freq" name="frequency" class="{{ $inputClass }}">
                            @foreach($freqLabels as $v => $l)<option value="{{ $v }}" @selected($o('frequency', 'per_cutoff') === $v)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label for="ri_start" class="{{ $labelClass }}">Start date</label>
                        <input type="date" id="ri_start" name="start_date" required class="{{ $inputClass }}" value="{{ $o('start_date', $today) }}">
                        @if($eb->has('start_date'))<p class="{{ $errText }}">{{ $eb->first('start_date') }}</p>@endif
                    </div>
                    <div>
                        <label for="ri_end" class="{{ $labelClass }}">End date <span class="font-normal text-gray-500">(optional)</span></label>
                        <input type="date" id="ri_end" name="end_date" class="{{ $inputClass }}" value="{{ $o('end_date') }}">
                        @if($eb->has('end_date'))<p class="{{ $errText }}">{{ $eb->first('end_date') }}</p>@endif
                    </div>
                    <div id="ri_auth_wrap" class="md:col-span-4">
                        <label for="ri_auth" class="{{ $labelClass }}">Written authorization reference</label>
                        <input type="text" id="ri_auth" name="authorization_ref" maxlength="255" class="{{ $inputClass }}" value="{{ $o('authorization_ref') }}" placeholder="e.g. Signed salary-deduction form dated 2026-09-01, filed in 201 file">
                        @if($eb->has('authorization_ref'))<p class="{{ $errText }}">{{ $eb->first('authorization_ref') }}</p>@endif
                    </div>
                </div>
                <div class="flex sm:justify-end">
                    <button type="submit" class="w-full {{ $btn['primary'] }} sm:w-auto"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add Item</button>
                </div>
            </form>
        @endif

        @if($bag('recurringDelete')->any())
            <p class="px-6 pt-4 text-sm text-red-600 dark:text-red-400">{{ $bag('recurringDelete')->first() }}</p>
        @endif
        <div class="md:overflow-x-auto">
            <table role="table" class="rt min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead role="rowgroup" class="bg-gray-50 dark:bg-gray-900">
                    <tr role="row">
                        <th role="columnheader" class="{{ $th }}">Item</th>
                        <th role="columnheader" class="{{ $th }} text-right">Amount</th>
                        <th role="columnheader" class="{{ $th }}">Frequency</th>
                        <th role="columnheader" class="{{ $th }}">Dates</th>
                        <th role="columnheader" class="{{ $th }}">Status</th>
                        @if($canEdit)<th role="columnheader" class="{{ $th }}">Actions</th>@endif
                    </tr>
                </thead>
                <tbody role="rowgroup" class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    @forelse($recurring as $item)
                        @php
                            $u = $recurringUsage[$item->id] ?? ['locked_through' => null, 'pending' => 0];
                            $ended = $item->end_date && $item->end_date->toDateString() < $today;
                            $scheduled = $item->start_date->toDateString() > $today;
                            $iRow = ['id' => $item->id, 'label' => $item->label, 'start_date' => $item->start_date->toDateString(),
                                     'end_date' => $item->end_date?->toDateString(), 'locked_through' => $u['locked_through']];
                        @endphp
                        <tr role="row" class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td role="cell" data-label="Item" class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $item->label }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    <span class="{{ $item->kind === 'deduction' ? 'text-red-700 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400' }}">{{ $componentLabels[$item->component_code] ?? $item->component_code }}</span>
                                    @if($item->authorization_ref) · Auth: {{ $item->authorization_ref }} @endif
                                </p>
                            </td>
                            <td role="cell" data-label="Amount" class="px-6 py-4 text-sm font-semibold text-right whitespace-nowrap {{ $item->kind === 'deduction' ? 'text-red-700 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                                {{ $item->kind === 'deduction' ? '−' : '' }}₱{{ number_format((float) $item->amount, 2) }}
                            </td>
                            <td role="cell" data-label="Frequency" class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $freqLabels[$item->frequency] ?? $item->frequency }}</td>
                            <td role="cell" data-label="Dates" class="px-6 py-4 text-sm text-gray-700 whitespace-nowrap dark:text-gray-300">
                                {{ $item->start_date->format('M d, Y') }} – {{ $item->end_date?->format('M d, Y') ?? 'open' }}
                            </td>
                            <td role="cell" data-label="Status" class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @if(!$item->is_active)
                                        <span class="{{ $badgeBase }} bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">Inactive</span>
                                    @elseif($ended)
                                        <span class="{{ $badgeBase }} bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">Ended</span>
                                    @elseif($scheduled)
                                        <span class="{{ $badgeBase }} bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">Scheduled</span>
                                    @else
                                        <span class="{{ $badgeBase }} bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Active</span>
                                    @endif
                                    @if($u['locked_through'])
                                        <span class="{{ $badgeBase }} bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"><i class="fa-solid fa-lock text-[10px]" aria-hidden="true"></i> Paid through {{ \Illuminate\Support\Carbon::parse($u['locked_through'])->format('M d') }}</span>
                                    @endif
                                </div>
                            </td>
                            @if($canEdit)
                                <td role="cell" data-label="Actions" class="px-6 py-4 rt-actions">
                                    <div class="flex flex-wrap gap-2">
                                        @if(!$ended)
                                            <button type="button" class="{{ $btn['warn'] }}" data-item='@json($iRow)' onclick="openStopModal(this)">
                                                <i class="text-xs fa-solid fa-circle-stop" aria-hidden="true"></i><span>Stop</span>
                                            </button>
                                        @endif
                                        @unless($u['locked_through'])
                                            <button type="button" class="{{ $btn['remove'] }}" data-item='@json($iRow)' onclick="openItemDeleteModal(this)">
                                                <i class="text-xs fa-solid fa-trash" aria-hidden="true"></i><span>Delete</span>
                                            </button>
                                        @endunless
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr role="row">
                            <td role="cell" colspan="{{ $canEdit ? 6 : 5 }}" class="px-6 py-12 text-center text-gray-500 rt-empty dark:text-gray-400">No recurring allowances or deductions.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

@if($canEdit)
{{-- ═══════════════════════════════════════════════════
     PROFILE EDIT MODAL
     ═══════════════════════════════════════════════════ --}}
@php $eb = $bag('profileEdit'); $isOld = $oldModal === 'profileEditModal'; @endphp
<div id="profileEditModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="profileEditTitle" class="w-full max-w-2xl bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <form id="profileEditForm" method="POST" data-profile-form>
                @csrf
                @method('PUT')
                <input type="hidden" name="_modal" value="profileEditModal">
                <input type="hidden" name="_record" id="pe_record" value="">
                <div class="flex items-start justify-between gap-3 {{ $cardHead }}">
                    <div>
                        <h2 id="profileEditTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Edit Pay Profile</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Only profiles no finalized payroll has used can be edited.</p>
                    </div>
                    <button type="button" onclick="closeModalById('profileEditModal')" aria-label="Close dialog"
                            class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="px-4 py-6 space-y-4 sm:px-6">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="pe_from" class="{{ $labelClass }}">Effective from</label>
                            <input type="date" id="pe_from" name="effective_from" required class="{{ $inputClass }}" value="{{ $isOld ? old('effective_from') : '' }}">
                        </div>
                        <div>
                            <label for="pe_to" class="{{ $labelClass }}">Effective to <span class="font-normal text-gray-500">(optional)</span></label>
                            <input type="date" id="pe_to" name="effective_to" class="{{ $inputClass }}" value="{{ $isOld ? old('effective_to') : '' }}">
                        </div>
                        <div>
                            <label for="pe_basis" class="{{ $labelClass }}">Pay basis</label>
                            <select id="pe_basis" name="pay_basis" class="{{ $inputClass }}" data-basis data-old="{{ $isOld ? old('pay_basis') : '' }}">
                                <option value="daily">Daily-paid</option>
                                <option value="monthly">Monthly-paid</option>
                            </select>
                        </div>
                        <div>
                            <label for="pe_rate" class="{{ $labelClass }}"><span data-rate-label>Daily rate (₱)</span></label>
                            <input type="text" inputmode="decimal" id="pe_rate" name="base_rate" required class="{{ $inputClass }}" value="{{ $isOld ? old('base_rate') : '' }}">
                        </div>
                    </div>
                    <label class="inline-flex items-center gap-3 min-h-[44px] text-sm text-gray-700 cursor-pointer dark:text-gray-300">
                        <input type="hidden" name="commission_enabled" value="0">
                        <input type="checkbox" id="pe_comm" name="commission_enabled" value="1" data-old="{{ $isOld ? old('commission_enabled') : '' }}"
                               class="w-5 h-5 border-gray-300 rounded text-[#8B7355] focus:ring-[#8B7355] dark:border-gray-600 dark:bg-gray-700">
                        Earns commission
                    </label>
                    <fieldset>
                        <legend class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Weekly rest days</legend>
                        <div class="flex flex-wrap gap-2" id="pe_rest" data-old='@json($isOld ? (array) old('rest_days', []) : null)'>
                            @foreach($dayNames as $d)
                                <label class="inline-flex items-center gap-2 min-h-[44px] px-3 text-sm border border-gray-300 cursor-pointer rounded-xl dark:border-gray-600 dark:text-gray-300 has-[:checked]:border-[#8B7355] has-[:checked]:bg-[#8B7355]/10">
                                    <input type="checkbox" name="rest_days[]" value="{{ $d }}" class="w-4 h-4 border-gray-300 rounded text-[#8B7355] focus:ring-[#8B7355] dark:border-gray-600 dark:bg-gray-700">
                                    {{ substr($d, 0, 3) }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    @if($eb->any())
                        <ul class="space-y-1 text-xs text-red-600 dark:text-red-400">@foreach($eb->all() as $m)<li>{{ $m }}</li>@endforeach</ul>
                    @endif
                </div>
                <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeModalById('profileEditModal')" class="w-full {{ $btn['neutral'] }} sm:w-auto">Cancel</button>
                        <button type="submit" class="w-full {{ $btn['primary'] }} sm:w-auto">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- PROFILE DELETE --}}
<div id="profileDeleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="alertdialog" aria-modal="true" aria-labelledby="profileDeleteTitle" aria-describedby="profileDeleteDesc"
             class="w-full max-w-md p-6 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <h2 id="profileDeleteTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Delete Pay Profile</h2>
            <p id="profileDeleteDesc" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                This permanently deletes the profile for <span id="pdel_range" class="font-medium text-gray-900 dark:text-white"></span>. Dates it covered will have no pay profile.
            </p>
            <div class="flex flex-col-reverse gap-2 mt-6 sm:flex-row sm:justify-end">
                <button type="button" onclick="closeModalById('profileDeleteModal')" class="w-full {{ $btn['neutral'] }} sm:w-auto">Keep Profile</button>
                <form id="profileDeleteForm" method="POST" class="sm:w-auto">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full {{ $btn['remove'] }} sm:w-auto">Yes, Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- STATUTORY IDS MODAL --}}
<div id="idsModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="idsModalTitle" class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <form id="idsForm" method="POST" action="{{ route('payroll.staff.ids.update', $staff) }}" autocomplete="off">
                @csrf
                @method('PUT')
                <input type="hidden" name="_modal" value="idsModal">
                <input type="hidden" name="_record" value="{{ $staff->id }}">
                <div class="flex items-start justify-between gap-3 {{ $cardHead }}">
                    <div>
                        <h2 id="idsModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Statutory IDs</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Type only the numbers you want to add or change. Blank fields keep what is on file.</p>
                    </div>
                    <button type="button" onclick="closeModalById('idsModal')" aria-label="Close dialog"
                            class="inline-flex items-center justify-center text-gray-500 min-h-[44px] min-w-[44px] rounded-xl hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="px-4 py-6 space-y-4 sm:px-6">
                    @foreach($ids as $attr => $i)
                        <div>
                            <label for="id_{{ $attr }}" class="{{ $labelClass }}">{{ $i['label'] }} number</label>
                            <input type="text" id="id_{{ $attr }}" name="{{ $attr }}" maxlength="20" inputmode="numeric" spellcheck="false" autocomplete="off"
                                   class="{{ $inputClass }} font-mono"
                                   placeholder="{{ $i['state'] === 'set' ? 'On file: ' . $i['masked'] . ' — blank keeps it' : 'Not on file' }}">
                            @if($i['state'] !== 'missing')
                                <label class="inline-flex items-center gap-2 mt-1 min-h-[44px] text-xs text-gray-600 cursor-pointer dark:text-gray-400">
                                    <input type="checkbox" name="clear[]" value="{{ $attr }}" class="w-4 h-4 text-red-700 border-gray-300 rounded focus:ring-red-700 dark:border-gray-600 dark:bg-gray-700">
                                    Remove the {{ $i['label'] }} number on file
                                </label>
                            @endif
                            @if($bag('ids')->has($attr))<p class="{{ $errText }}">{{ $bag('ids')->first($attr) }}</p>@endif
                        </div>
                    @endforeach
                </div>
                <div class="px-4 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl sm:px-6 dark:bg-gray-900 dark:border-gray-700">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeModalById('idsModal')" class="w-full {{ $btn['neutral'] }} sm:w-auto">Cancel</button>
                        <button type="submit" class="w-full {{ $btn['primary'] }} sm:w-auto">Save IDs</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- RECURRING STOP --}}
@php $eb = $bag('recurringStop'); $isOld = $oldModal === 'stopModal'; @endphp
<div id="stopModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="dialog" aria-modal="true" aria-labelledby="stopModalTitle" class="w-full max-w-md bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <form id="stopForm" method="POST">
                @csrf
                <input type="hidden" name="_modal" value="stopModal">
                <input type="hidden" name="_record" id="stop_record" value="">
                <div class="px-4 py-6 space-y-4 sm:px-6">
                    <div>
                        <h2 id="stopModalTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Stop Recurring Item</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><span id="stop_label" class="font-medium text-gray-900 dark:text-white"></span> will not be paid or deducted after the date you pick.</p>
                    </div>
                    <div>
                        <label for="stop_date" class="{{ $labelClass }}">Last day it applies</label>
                        <input type="date" id="stop_date" name="end_date" required class="{{ $inputClass }}" value="{{ $isOld ? old('end_date') : '' }}">
                        <p id="stop_lock" class="hidden mt-1 text-xs text-amber-700 dark:text-amber-300"></p>
                        @if($eb->any())<p class="{{ $errText }}">{{ $eb->first() }}</p>@endif
                    </div>
                    <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeModalById('stopModal')" class="w-full {{ $btn['neutral'] }} sm:w-auto">Cancel</button>
                        <button type="submit" class="w-full {{ $btn['warn'] }} sm:w-auto">Stop Item</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- RECURRING DELETE --}}
<div id="itemDeleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
        <div role="alertdialog" aria-modal="true" aria-labelledby="itemDeleteTitle" aria-describedby="itemDeleteDesc"
             class="w-full max-w-md p-6 bg-white shadow-xl rounded-2xl dark:bg-gray-800">
            <h2 id="itemDeleteTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Delete Recurring Item</h2>
            <p id="itemDeleteDesc" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                This permanently deletes <span id="idel_label" class="font-medium text-gray-900 dark:text-white"></span>. Use this only for an item entered by mistake; otherwise use “Stop”.
            </p>
            <div class="flex flex-col-reverse gap-2 mt-6 sm:flex-row sm:justify-end">
                <button type="button" onclick="closeModalById('itemDeleteModal')" class="w-full {{ $btn['neutral'] }} sm:w-auto">Keep Item</button>
                <form id="itemDeleteForm" method="POST" class="sm:w-auto">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full {{ $btn['remove'] }} sm:w-auto">Yes, Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

<script>
(function () {
    'use strict';

    // ── Profile forms: rate label + minimum-wage hint follow the pay basis ──
    function wireProfileForm(form) {
        const basis = form.querySelector('[data-basis]');
        const label = form.querySelector('[data-rate-label]');
        const hint  = form.querySelector('[data-rate-hint]');
        function sync() {
            if (!basis) return;
            const monthly = basis.value === 'monthly';
            if (label) label.textContent = monthly ? 'Monthly rate (₱)' : 'Daily rate (₱)';
            if (hint) hint.textContent = monthly ? hint.dataset.monthly : hint.dataset.daily;
        }
        if (basis) basis.addEventListener('change', sync);
        form._syncBasis = sync;
        sync();
    }
    document.querySelectorAll('[data-profile-form]').forEach(wireProfileForm);

@if($canEdit)
    // ── Recurring form: component list and the Art. 113 field follow the type ──
    const CODES = @json($recurringCodes);
    const CODE_LABELS = @json($componentLabels);
    const kindSel = document.getElementById('ri_kind');
    const codeSel = document.getElementById('ri_code');
    const authWrap = document.getElementById('ri_auth_wrap');
    const authInput = document.getElementById('ri_auth');
    function syncKind(selected) {
        const kind = kindSel.value;
        codeSel.innerHTML = '';
        (CODES[kind] || []).forEach(c => {
            const o = document.createElement('option');
            o.value = c; o.textContent = CODE_LABELS[c] || c;
            codeSel.appendChild(o);
        });
        if (selected && (CODES[kind] || []).includes(selected)) codeSel.value = selected;
        const isDeduction = kind === 'deduction';
        authWrap.classList.toggle('hidden', !isDeduction);
        authInput.required = isDeduction;
        authInput.disabled = !isDeduction;
    }
    if (kindSel) {
        kindSel.addEventListener('change', () => syncKind(''));
        syncKind(codeSel.dataset.old);
    }

    // ── Statutory IDs: masked by default. Reveal asks the server (POST, logged, no-store);
    //    the numbers are dropped from the DOM again on Hide.
    const ROUTE_REVEAL = @json(route('payroll.staff.ids.reveal', $staff));
    const CSRF = @json(csrf_token());
    async function toggleReveal() {
        const b = document.getElementById('revealIds');
        const err = document.getElementById('revealError');
        const show = b.getAttribute('aria-pressed') !== 'true';
        const plains = document.querySelectorAll('#ids [data-plain]');
        err.classList.add('hidden');
        if (show) {
            b.disabled = true;
            try {
                const res = await fetch(ROUTE_REVEAL, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF } });
                if (!res.ok) throw new Error(res.status === 403 ? 'You do not have permission to view these numbers.' : 'Could not load the numbers — try again.');
                const data = await res.json();
                plains.forEach(el => { el.textContent = data.ids[el.dataset.plain] || ''; });
            } catch (e) {
                err.textContent = e.message || 'Could not load the numbers — try again.';
                err.classList.remove('hidden');
                b.disabled = false;
                return;
            }
            b.disabled = false;
        } else {
            plains.forEach(el => { el.textContent = ''; });
        }
        b.setAttribute('aria-pressed', show ? 'true' : 'false');
        b.querySelector('span').textContent = show ? 'Hide' : 'Reveal';
        b.querySelector('i').className = 'text-xs fa-solid ' + (show ? 'fa-eye-slash' : 'fa-eye');
        plains.forEach(el => el.classList.toggle('hidden', !show));
        document.querySelectorAll('#ids [data-masked]').forEach(el => el.classList.toggle('hidden', show));
    }
    window.toggleReveal = toggleReveal;

    // ════════════════════════════════════════════════════════════════
    // SHARED MODAL BEHAVIOUR (same as staff/index.blade.php)
    // ════════════════════════════════════════════════════════════════
    const MODAL_IDS = ['profileEditModal', 'profileDeleteModal', 'idsModal', 'stopModal', 'itemDeleteModal'];
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
    ['profileDeleteModal', 'itemDeleteModal'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('click', e => { if (e.target === el) closeModal(id); });
    });

    const ROUTES = {
        profileUpdate:  @json(route('payroll.staff.profiles.update', [$staff, '__ID__'])),
        profileDestroy: @json(route('payroll.staff.profiles.destroy', [$staff, '__ID__'])),
        stop:           @json(route('payroll.staff.recurring.stop', [$staff, '__ID__'])),
        itemDestroy:    @json(route('payroll.staff.recurring.destroy', [$staff, '__ID__'])),
    };
    const routeFor = (t, id) => t.replace('__ID__', encodeURIComponent(id));
    const data = (btn, attr) => JSON.parse(btn.getAttribute(attr) || '{}');
    function fmtDate(ymd) {
        return new Date(ymd + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
    }
    let restoring = false;

    function openProfileEditModal(btn) {
        const d = data(btn, 'data-profile');
        const form = document.getElementById('profileEditForm');
        form.action = routeFor(ROUTES.profileUpdate, d.id);
        document.getElementById('pe_record').value = d.id;
        const basis = document.getElementById('pe_basis');
        const comm  = document.getElementById('pe_comm');
        const restBox = document.getElementById('pe_rest');
        const oldRest = JSON.parse(restBox.dataset.old || 'null');
        if (restoring) {
            basis.value = basis.dataset.old || d.pay_basis;
            comm.checked = comm.dataset.old === '1';
        } else {
            basis.value = d.pay_basis;
            comm.checked = !!d.commission_enabled;
            document.getElementById('pe_from').value = d.effective_from;
            document.getElementById('pe_to').value = d.effective_to || '';
            document.getElementById('pe_rate').value = d.base_rate;
        }
        const rest = restoring && Array.isArray(oldRest) ? oldRest : (d.rest_days || []);
        restBox.querySelectorAll('input[type="checkbox"]').forEach(cb => { cb.checked = rest.includes(cb.value); });
        form._syncBasis();
        openModal('profileEditModal', '#pe_from');
    }
    function openProfileDeleteModal(btn) {
        const d = data(btn, 'data-profile');
        document.getElementById('profileDeleteForm').action = routeFor(ROUTES.profileDestroy, d.id);
        document.getElementById('pdel_range').textContent = fmtDate(d.effective_from) + ' – ' + (d.effective_to ? fmtDate(d.effective_to) : 'open');
        openModal('profileDeleteModal', 'button[type="button"]');
    }
    function openIdsModal() { openModal('idsModal', '#id_tin'); }
    function openStopModal(btn) {
        const d = data(btn, 'data-item');
        document.getElementById('stopForm').action = routeFor(ROUTES.stop, d.id);
        document.getElementById('stop_record').value = d.id;
        document.getElementById('stop_label').textContent = d.label;
        const today = @json($today);
        const floor = [d.start_date, d.locked_through].filter(Boolean).sort().pop();
        if (!restoring) document.getElementById('stop_date').value = today > floor ? today : floor;
        const lock = document.getElementById('stop_lock');
        if (d.locked_through) {
            lock.textContent = 'Paid or deducted by a finalized run through ' + fmtDate(d.locked_through) + ' — pick that date or later.';
            lock.classList.remove('hidden');
        } else {
            lock.classList.add('hidden');
        }
        openModal('stopModal', '#stop_date');
    }
    function openItemDeleteModal(btn) {
        const d = data(btn, 'data-item');
        document.getElementById('itemDeleteForm').action = routeFor(ROUTES.itemDestroy, d.id);
        document.getElementById('idel_label').textContent = '“' + d.label + '”';
        openModal('itemDeleteModal', 'button[type="button"]');
    }

    window.openProfileEditModal   = openProfileEditModal;
    window.openProfileDeleteModal = openProfileDeleteModal;
    window.openIdsModal           = openIdsModal;
    window.openStopModal          = openStopModal;
    window.openItemDeleteModal    = openItemDeleteModal;
    window.closeModalById         = closeModal;

    // Re-open the modal a validation error belongs to.
    const OLD_MODAL  = @json($oldModal);
    const OLD_RECORD = @json($oldRecord);
    const OPENERS = { profileEditModal: [openProfileEditModal, 'data-profile'], stopModal: [openStopModal, 'data-item'] };
    if (OLD_MODAL === 'idsModal') {
        openIdsModal();
    } else if (OLD_MODAL && OPENERS[OLD_MODAL] && OLD_RECORD) {
        const [fn, attr] = OPENERS[OLD_MODAL];
        const src = Array.from(document.querySelectorAll('[' + attr + ']')).find(b => String(data(b, attr).id) === String(OLD_RECORD));
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