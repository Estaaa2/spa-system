{{--
    Payslip — employee copy (Unit 6). Reused by the HR payslip page and My Payslips.

    Expects $slip from App\Services\Payroll\Display\PayslipDocument::build(): every value
    is a display-ready string, so nothing is computed here. Employer shares are not part
    of the employee copy.

    Optional $receipt (bool): adds a "received by" block for the employer's copy. The
    Implementing Rules (Book III, Rule VIII, Sec. 6) ask every employee to sign the
    payroll; signing their OWN payslip meets that without showing co-workers' pay on a
    shared sheet (Data Privacy Act). Only useful when pay is handed over in cash.

    Callers wrap one or more payslips in <div class="payslip-print-area">; printing
    shows only that area.

    NEW VISUAL PATTERN (flagged): no existing Levictas page is a printable document.
    Screen styling reuses the standard card tokens (rounded-2xl, gray-200 borders,
    uppercase xs labels, billing-style right-aligned money). The print stylesheet below
    prints only this card, in black on white, whatever the OS colour scheme.
--}}
@php
    $cell  = 'px-4 py-2 text-sm text-gray-700 dark:text-gray-300';
    $money = 'px-4 py-2 text-sm text-right text-gray-900 whitespace-nowrap dark:text-white tabular-nums';
    $label = 'text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400';
@endphp

<article class="payslip-doc overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700"
         aria-labelledby="payslip-title-{{ $slip['id'] }}">

    @unless($slip['is_final'])
        <div class="payslip-watermark px-4 py-2 text-xs font-semibold tracking-wide text-center uppercase sm:px-6 bg-amber-50 text-amber-800 dark:bg-amber-900/20 dark:text-amber-300" role="note">
            {{ $slip['status_label'] }} — not final. Amounts can still change.
        </div>
    @endunless

    {{-- Header --}}
    <header class="flex flex-col gap-3 px-4 py-5 border-b border-gray-200 sm:flex-row sm:items-start sm:justify-between sm:px-6 dark:border-gray-700">
        <div class="min-w-0">
            <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $slip['spa'] }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $slip['branch'] }}@if($slip['location']) · {{ $slip['location'] }}@endif</p>
        </div>
        <div class="sm:text-right">
            <h2 id="payslip-title-{{ $slip['id'] }}" class="text-base font-semibold tracking-wide text-gray-900 uppercase dark:text-white">
                Payslip{{ $slip['is_thirteenth'] ? ' — 13th Month Pay' : '' }}
            </h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">No. {{ $slip['id'] }}</p>
        </div>
    </header>

    {{-- Details --}}
    <dl class="grid grid-cols-2 px-4 py-4 border-b border-gray-200 gap-x-6 gap-y-3 sm:grid-cols-3 sm:px-6 dark:border-gray-700">
        <div class="col-span-2 sm:col-span-1">
            <dt class="{{ $label }}">Employee</dt>
            <dd class="mt-0.5 text-sm font-medium text-gray-900 dark:text-white">{{ $slip['employee'] }}</dd>
            @if($slip['position'])<dd class="text-xs text-gray-500 dark:text-gray-400">{{ $slip['position'] }}</dd>@endif
        </div>
        <div>
            <dt class="{{ $label }}">Pay Period</dt>
            <dd class="mt-0.5 text-sm text-gray-900 dark:text-white">{{ $slip['period'] }}@if($slip['cutoff']) <span class="text-gray-500 dark:text-gray-400">({{ $slip['cutoff'] }})</span>@endif</dd>
        </div>
        <div>
            <dt class="{{ $label }}">Pay Date</dt>
            <dd class="mt-0.5 text-sm text-gray-900 dark:text-white">{{ $slip['pay_date'] }}</dd>
        </div>
        @if($slip['days_worked'] !== null)
            <div>
                <dt class="{{ $label }}">Days Worked</dt>
                <dd class="mt-0.5 text-sm text-gray-900 dark:text-white">{{ $slip['days_worked'] }}</dd>
            </div>
        @endif
    </dl>

    {{-- Earnings / deductions --}}
    <div class="grid grid-cols-1 md:grid-cols-2 md:divide-x divide-gray-200 dark:divide-gray-700 payslip-cols">
        @foreach([['Earnings', $slip['earnings'], 'Gross Pay', $slip['gross'], 'earn'], ['Deductions', $slip['deductions'], 'Total Deductions', $slip['deductions_total'], 'ded']] as [$title, $rows, $totalLabel, $total, $key])
            <section aria-labelledby="payslip-{{ $key }}-{{ $slip['id'] }}" class="{{ $key === 'ded' ? 'border-t border-gray-200 md:border-t-0 dark:border-gray-700' : '' }}">
                <h3 id="payslip-{{ $key }}-{{ $slip['id'] }}" class="px-4 pt-4 pb-2 sm:px-6 {{ $label }}">{{ $title }}</h3>
                <table class="w-full">
                    <thead class="sr-only">
                        <tr><th scope="col">Item</th><th scope="col">Amount</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                        @forelse($rows as $r)
                            <tr>
                                <td class="{{ $cell }} sm:pl-6">
                                    <span class="text-gray-900 dark:text-white">{{ $r['label'] }}</span>
                                    @if($r['detail'] !== '')
                                        <span class="block text-xs {{ $r['short'] ? 'text-amber-700 dark:text-amber-300' : 'text-gray-500 dark:text-gray-400' }}">{{ $r['detail'] }}</span>
                                    @endif
                                    @if($r['note'])
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $r['note'] }}</span>
                                    @endif
                                </td>
                                <td class="{{ $money }} sm:pr-6 align-top">{{ $r['amount'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="{{ $cell }} sm:pl-6 text-gray-500 dark:text-gray-400">None</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-gray-200 bg-gray-50 dark:bg-gray-900 dark:border-gray-700">
                            <th scope="row" class="px-4 py-2 text-xs font-semibold text-left text-gray-600 uppercase sm:pl-6 dark:text-gray-300">{{ $totalLabel }}</th>
                            <td class="{{ $money }} sm:pr-6 font-semibold">{{ $total }}</td>
                        </tr>
                    </tfoot>
                </table>
            </section>
        @endforeach
    </div>

    {{-- Net pay --}}
    <div class="flex items-center justify-between gap-4 px-4 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
        <p class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">Net Pay</p>
        <p class="text-2xl font-bold text-gray-900 tabular-nums dark:text-white">{{ $slip['net'] }}</p>
    </div>

    @if($slip['has_short'] || $slip['is_mwe'])
        <div class="px-4 pb-4 space-y-1 text-xs text-gray-500 sm:px-6 dark:text-gray-400">
            @if($slip['has_short'])
                <p>Some deductions were only partly taken because net pay cannot go below ₱0.</p>
            @endif
            @if($slip['is_mwe'])
                <p>Minimum wage earner — pay that is exempt for minimum wage earners is left out of withholding tax.</p>
            @endif
        </div>
    @endif

    @if($receipt ?? false)
        <div class="grid grid-cols-1 gap-6 px-4 pt-6 pb-5 border-t border-gray-200 sm:grid-cols-3 sm:px-6 dark:border-gray-700">
            <p class="text-xs text-gray-600 sm:col-span-3 dark:text-gray-300">I received the net pay shown above in full.</p>
            @foreach(['Signature over printed name', 'Date received', 'Released by'] as $f)
                <div>
                    <div class="h-8 border-b border-gray-400"></div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $f }}</p>
                </div>
            @endforeach
        </div>
    @endif
</article>

@once
<style>
.tabular-nums { font-variant-numeric: tabular-nums; }

@media print {
    @page { size: A4 portrait; margin: 12mm; }
    body * { visibility: hidden !important; }
    .payslip-print-area, .payslip-print-area * { visibility: visible !important; }
    .payslip-print-area { position: absolute; left: 0; top: 0; width: 100%; }
    .payslip-print-area .no-print { display: none !important; }
    .payslip-doc {
        border: 1px solid #9ca3af !important; border-radius: 0 !important; box-shadow: none !important;
        break-inside: avoid; page-break-inside: avoid;
    }
    .payslip-doc + .payslip-doc { break-before: page; page-break-before: always; }
    .payslip-doc, .payslip-doc * {
        color: #111827 !important; background: transparent !important; border-color: #d1d5db !important;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    .payslip-doc .payslip-watermark { border-bottom: 2px solid #111827 !important; }
    /* Keep the two columns side by side on paper. */
    .payslip-doc .payslip-cols { display: grid !important; grid-template-columns: 1fr 1fr !important; }
    .payslip-doc .payslip-cols > section { border-top: 0 !important; }
    .payslip-doc .payslip-cols > section + section { border-left: 1px solid #d1d5db !important; }
    .payslip-doc tr { break-inside: avoid; }
    .no-print { display: none !important; }
}
</style>
@endonce
