<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingRefund;
use App\Models\Spa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Customer refunds queue (customer cancellation, Nov 2026).
 *
 * GET  /admin/refunds                 list (Pending / Sent, search, spa filter)
 * POST /admin/refunds/{refund}/sent   mark sent, with how and a reference number
 *
 * Online payments land in the Levictas PayMongo account, so Levictas sends the
 * money back by hand: a refund from the PayMongo dashboard where PayMongo
 * allows it (GCash full refunds), or a transfer otherwise (QR Ph, partial Maya
 * refunds). This page only records that it was done.
 */
class RefundController extends Controller
{
    public const TABS = [
        'pending' => BookingRefund::STATUS_PENDING,
        'sent'    => BookingRefund::STATUS_SENT,
    ];

    /** How the money went back. */
    public const METHODS = [
        'paymongo' => 'PayMongo refund (dashboard)',
        'gcash'    => 'GCash transfer',
        'maya'     => 'Maya transfer',
        'bank'     => 'Bank transfer',
        'other'    => 'Other',
    ];

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'pending';
        $q = trim((string) $request->query('q', ''));
        $spaId = $request->integer('spa') ?: null;

        $base = BookingRefund::query()
            ->when($spaId, fn ($query) => $query->whereHas('booking', fn ($b) => $b->withTrashed()->where('spa_id', $spaId)))
            ->when($q !== '', function ($query) use ($q) {
                $search = '%'.addcslashes($q, '%_').'%';

                $query->where(function ($match) use ($search) {
                    $match->whereHas('booking', fn ($b) => $b->withTrashed()->where(fn ($w) => $w
                            ->where('customer_name', 'like', $search)
                            ->orWhere('customer_email', 'like', $search)
                            ->orWhereHas('spa', fn ($s) => $s->where('name', 'like', $search))))
                        ->orWhere('reference', 'like', $search);
                });
            });

        $counts = collect(self::TABS)->map(fn ($status) => (clone $base)->where('status', $status)->count());
        $pendingTotal = (float) (clone $base)->pending()->sum('amount');

        $refunds = (clone $base)
            ->where('status', self::TABS[$tab])
            ->with(['booking' => fn ($b) => $b->withTrashed()->with(['spa', 'branch']), 'reservation', 'processor'])
            // Pending: oldest first, so nobody waits longest. Sent: newest first.
            ->orderBy($tab === 'pending' ? 'created_at' : 'processed_at', $tab === 'pending' ? 'asc' : 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('admin.refunds.index', [
            'tab'          => $tab,
            'q'            => $q,
            'spaId'        => $spaId,
            'spas'         => Spa::orderBy('name')->get(['id', 'name']),
            'counts'       => $counts,
            'pendingTotal' => $pendingTotal,
            'refunds'      => $refunds,
            'methods'      => self::METHODS,
        ]);
    }

    public function markSent(Request $request, BookingRefund $refund): RedirectResponse
    {
        $data = $request->validateWithBag('refund', [
            'payment_method' => ['required', Rule::in(array_keys(self::METHODS))],
            'reference'      => ['required', 'string', 'max:100'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ], [
            'reference.required' => 'Enter the PayMongo refund ID or the transfer reference number.',
        ]);

        $updated = DB::transaction(function () use ($refund, $data, $request) {
            $locked = BookingRefund::whereKey($refund->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isPending()) {
                return false;   // someone else marked it first
            }

            $locked->update($data + [
                'status'       => BookingRefund::STATUS_SENT,
                'processed_by' => $request->user()->id,
                'processed_at' => now(),
            ]);

            return true;
        });

        if ($updated) {
            app(\App\Services\Bookings\CancellationNotifier::class)->refundSent($refund->fresh());
        }

        return redirect()
            ->route('admin.refunds.index', ['tab' => 'pending'])
            ->with($updated ? 'success' : 'error', $updated
                ? 'Refund of ₱'.number_format((float) $refund->amount, 2).' marked as sent.'
                : 'This refund was already marked as sent.');
    }

    /** What the customer paid with, read from the PayMongo webhook payload. */
    public static function paidWith(BookingRefund $refund): ?string
    {
        $payload = $refund->reservation?->paymongo_payload;

        $type = data_get($payload, 'data.attributes.data.attributes.payment_method_used')
            ?? data_get($payload, 'data.attributes.data.attributes.payments.0.attributes.source.type');

        return $type ? match ($type) {
            'gcash'   => 'GCash',
            'paymaya' => 'Maya',
            'qrph'    => 'QR Ph',
            default   => ucfirst((string) $type),
        } : null;
    }
}
