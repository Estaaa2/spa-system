@extends('layouts.app')

@section('title', 'Refunds')

@section('content')

@php
    // Same tokens as admin/identity-verifications/index.blade.php.
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
    $labelClass = 'block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300';

    $tabBase = 'flex items-center justify-center flex-1 gap-2 min-h-[44px] px-3 text-sm font-medium whitespace-nowrap transition rounded-xl';
    $tabActive = $tabBase . ' text-white shadow-sm bg-gradient-to-r from-[#7A6348] to-[#6F5430]';
    $tabInactive = $tabBase . ' text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700';

    $thClass = 'px-6 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400';
    $badgeClass = 'px-2.5 py-1 text-xs font-medium rounded-full whitespace-nowrap';

    $tabs = ['pending' => 'To send', 'sent' => 'Sent'];

    $kinds = [
        \App\Models\BookingRefund::KIND_FREE => ['Free cancellation', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
        \App\Models\BookingRefund::KIND_LATE => ['Late · fee kept', 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'],
        \App\Models\BookingRefund::KIND_SPA => ['Spa cancelled', 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300'],
        \App\Models\BookingRefund::KIND_NO_SHOW => ['No-show · fee kept', 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300'],
    ];

    $isPendingTab = $tab === 'pending';

    // Reopen the form after a validation error.
    $reopenId = $errors->refund->any() ? (int) old('refund_id') : null;
@endphp

<div class="p-4 mx-auto space-y-6 sm:p-6 max-w-7xl"
    x-data="{
        open: {{ $reopenId ? 'true' : 'false' }},
        refund: { id: {{ $reopenId ?: 'null' }}, label: '', amount: '', action: '{{ $reopenId ? route('admin.refunds.sent', $reopenId) : '' }}' },
        show(r) { this.refund = r; this.open = true; this.$nextTick(() => this.$refs.method.focus()); },
        close() { this.open = false; },
    }"
    @keydown.escape.window="close()">

    <x-page-header
        title="Refunds"
        subtitle="Money owed back to customers who cancelled. Send it, then record how and the reference number."
    />

    <nav aria-label="Refund status"
        class="flex gap-1 p-1.5 overflow-x-auto bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        @foreach ($tabs as $value => $label)
            @php $isCurrent = $value === $tab; @endphp
            <a href="{{ route('admin.refunds.index', array_filter(['tab' => $value, 'q' => $q, 'spa' => $spaId])) }}"
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
            <div class="space-y-1 text-sm text-blue-800 dark:text-blue-300">
                <p><span class="font-semibold">₱{{ number_format($pendingTotal, 2) }}</span> to send. Oldest first.</p>
                <p>
                    Full GCash refunds can go through the PayMongo dashboard (search the payment ID). PayMongo can't refund QR Ph
                    payments, or part of a Maya payment, so send those as a transfer to the customer.
                </p>
            </div>
        </div>
    @endif

    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="flex flex-col gap-3 px-4 py-4 border-b border-gray-200 lg:flex-row lg:items-center lg:justify-between sm:px-6 dark:border-gray-700">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ $isPendingTab ? 'Refunds to send' : 'Sent refunds' }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $isPendingTab ? 'The customer sees "Refund being processed" until you mark it sent, then gets an email.' : 'Recorded refunds, newest first.' }}
                </p>
            </div>

            <form method="GET" action="{{ route('admin.refunds.index') }}" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <input type="hidden" name="tab" value="{{ $tab }}">

                <label for="spaFilter" class="sr-only">Filter by spa</label>
                <select id="spaFilter" name="spa" class="{{ $inputClass }} sm:w-56">
                    <option value="">All spas</option>
                    @foreach ($spas as $spaOption)
                        <option value="{{ $spaOption->id }}" @selected($spaId === $spaOption->id)>{{ $spaOption->name }}</option>
                    @endforeach
                </select>

                <input type="search" name="q" value="{{ $q }}"
                    aria-label="Search customer, spa or reference"
                    placeholder="Search customer or spa"
                    class="{{ $inputClass }} sm:w-56">

                <button type="submit" class="{{ $btn['primary'] }}">Search</button>

                @if ($q !== '' || $spaId)
                    <a href="{{ route('admin.refunds.index', ['tab' => $tab]) }}" class="{{ $btn['edit'] }}">Clear</a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="{{ $thClass }}">Customer</th>
                        <th class="{{ $thClass }}">Spa / Booking</th>
                        <th class="{{ $thClass }}">Payment</th>
                        <th class="{{ $thClass }}">Refund</th>
                        <th class="{{ $thClass }}">{{ $isPendingTab ? 'Cancelled' : 'Sent' }}</th>
                        @if ($isPendingTab)
                            <th class="{{ $thClass }}"><span class="sr-only">Actions</span></th>
                        @endif
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    @forelse ($refunds as $row)
                        @php
                            $booking = $row->booking;
                            $res = $row->reservation;
                            [$kindLabel, $kindColors] = $kinds[$row->kind] ?? [ucfirst($row->kind), $kinds[\App\Models\BookingRefund::KIND_LATE][1]];
                            $paidWith = \App\Http\Controllers\Admin\RefundController::paidWith($row);
                            $customer = $booking?->customer_name ?? 'Customer';
                        @endphp
                        <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $customer }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $booking?->customer_email }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $booking?->customer_phone }}</p>
                            </td>

                            <td class="px-6 py-4">
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $booking?->spa?->name ?? '—' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $booking?->branch?->name }}
                                    @if ($booking?->appointment_date)
                                        · {{ $booking->appointment_date->format('M d, Y') }}
                                    @endif
                                </p>
                                @if ($booking?->cancel_reason)
                                    <p class="mt-1 text-xs italic text-gray-500 dark:text-gray-400">"{{ \Illuminate\Support\Str::limit($booking->cancel_reason, 80) }}"</p>
                                @endif
                            </td>

                            <td class="px-6 py-4">
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    ₱{{ number_format((float) ($res?->amount_charged ?? $booking?->amount_paid ?? 0), 2) }}
                                    @if ($paidWith) <span class="text-xs text-gray-500 dark:text-gray-400">· {{ $paidWith }}</span> @endif
                                </p>
                                @if ($res?->paymongo_payment_id)
                                    <p class="text-xs text-gray-500 break-all dark:text-gray-400">{{ $res->paymongo_payment_id }}</p>
                                @endif
                                @if ($res?->payment_reference)
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Ref {{ $res->payment_reference }}</p>
                                @endif
                            </td>

                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">₱{{ number_format((float) $row->amount, 2) }}</p>
                                <span class="inline-block mt-1 {{ $badgeClass }} {{ $kindColors }}">{{ $kindLabel }}</span>
                            </td>

                            <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap dark:text-gray-400">
                                @if ($isPendingTab)
                                    {{ $row->created_at?->format('M d, Y') }}
                                    <span class="block text-xs">{{ $row->created_at?->format('h:i A') }}</span>
                                @else
                                    {{ $row->processed_at?->format('M d, Y') }}
                                    <span class="block text-xs">{{ $methods[$row->payment_method] ?? $row->payment_method }}</span>
                                    <span class="block text-xs break-all">{{ $row->reference }}</span>
                                    <span class="block text-xs">{{ $row->processor?->name ?? 'Admin' }}</span>
                                @endif
                            </td>

                            @if ($isPendingTab)
                                <td class="px-6 py-4 text-right">
                                    <button type="button" class="{{ $btn['primary'] }}"
                                        @click="show({ id: {{ $row->id }}, label: @js($customer), amount: @js('₱'.number_format((float) $row->amount, 2)), action: @js(route('admin.refunds.sent', $row)) })">
                                        <i class="text-xs fa-solid fa-check" aria-hidden="true"></i>
                                        Mark as sent
                                        <span class="sr-only">for {{ $customer }}</span>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isPendingTab ? 6 : 5 }}" class="px-6 py-12 text-sm text-center text-gray-500 dark:text-gray-400">
                                {{ ($q !== '' || $spaId) ? 'No refunds match your search.' : ($isPendingTab ? 'No refunds to send.' : 'No refunds sent yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($refunds->hasPages())
            <div class="px-4 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
                {{ $refunds->links() }}
            </div>
        @endif
    </div>

    {{-- Mark as sent (design language §8 modal contract). --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto overscroll-contain bg-black/50"
        role="dialog" aria-modal="true" aria-labelledby="refundSentTitle">
        <div class="flex items-start justify-center min-h-full p-4 sm:items-center" @click.self="close()">
            <form method="POST" :action="refund.action"
                class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
                @csrf
                <input type="hidden" name="refund_id" :value="refund.id">

                <div class="flex items-center justify-between gap-2 px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
                    <div>
                        <h2 id="refundSentTitle" class="text-base font-semibold text-gray-900 dark:text-white">Mark refund as sent</h2>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400" x-show="refund.label">
                            <span x-text="refund.amount"></span> to <span x-text="refund.label"></span>
                        </p>
                    </div>
                    <button type="button" @click="close()" aria-label="Close"
                        class="flex items-center justify-center -mr-2 w-11 h-11 rounded-xl text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355]">
                        <i class="text-lg fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="p-4 space-y-4 sm:p-6">
                    <div>
                        <label for="refundMethod" class="{{ $labelClass }}">Sent by</label>
                        <select id="refundMethod" name="payment_method" x-ref="method" required class="{{ $inputClass }}">
                            @foreach ($methods as $value => $label)
                                <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('payment_method', 'refund') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="refundReference" class="{{ $labelClass }}">Reference number</label>
                        <input type="text" id="refundReference" name="reference" maxlength="100" required
                            value="{{ old('reference') }}" placeholder="PayMongo refund ID or transfer reference"
                            class="{{ $inputClass }}">
                        @error('reference', 'refund') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="refundNotes" class="{{ $labelClass }}">Notes <span class="font-normal text-gray-500">(optional)</span></label>
                        <textarea id="refundNotes" name="notes" rows="3" maxlength="1000"
                            class="{{ $inputClass }} resize-none">{{ old('notes') }}</textarea>
                        @error('notes', 'refund') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 px-4 py-4 border-t border-gray-200 sm:flex-row sm:justify-end sm:px-6 dark:border-gray-700">
                    <button type="button" @click="close()" class="w-full sm:w-auto {{ $btn['edit'] }}">Not yet</button>
                    <button type="submit" class="w-full sm:w-auto {{ $btn['primary'] }}">
                        <i class="text-xs fa-solid fa-check" aria-hidden="true"></i>
                        Mark as sent
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
