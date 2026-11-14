<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Insights\DecisionSupportController;
use App\Http\Controllers\Insights\ReportsController;
use App\Models\DssRecommendation;
use App\Services\DecisionSupport\DemandForecaster;
use Illuminate\Support\Carbon;
use App\Models\Booking;
use App\Models\BookingConsumption;
use App\Models\Branch;
use App\Models\OperatingHours;
use App\Models\Package;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Treatment;
use App\Services\InventoryStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Owner side of the mobile app, view only (owner mobile, Nov 2026). The
 * branch comes from OwnerMobileBranch (X-Branch-Id). Each method reads what
 * the web page reads, with the same scope; the web pages it mirrors:
 *
 *   branches        the sidebar branch switcher
 *   history         appointments.blade.php: History and Needs checkout
 *   services        services/index.blade.php
 *   products        inventory/products.blade.php
 *   logs            inventory/logs.blade.php
 *   reports         insights/reports/index.blade.php (ReportsController::reportData)
 *   decisionSupport insights/decision-support/index.blade.php (DecisionSupportController::pageData)
 *
 * Dashboard, today/upcoming appointments and the schedule use the web's own
 * JSON endpoints (DashboardController::liveData, BookingController::liveData,
 * ScheduleController::data); see routes/api.php.
 */
class OwnerAppController extends Controller
{
    private const PER_PAGE = 20;

    public function branches(Request $request): JsonResponse
    {
        /** @var Branch $current */
        $current = $request->attributes->get('owner_branch');

        $user = $request->user();
        $spa = $user->spa;

        return response()->json([
            'current_branch_id' => $current->id,
            // Which screens the app shows for this branch: the same permission
            // and plan checks as the endpoints (and the web sidebar).
            'access' => [
                'appointments' => $user->hasBranchPermission('view appointments'),
                'schedule'     => $user->hasBranchPermission('view schedule'),
                'services'     => $user->hasBranchPermission('view services'),
                'inventory'    => $user->hasBranchPermission('view inventory') && (bool) $spa?->hasFeature('inventory_basic'),
                'logs'         => $user->hasBranchPermission('view inventory logs') && (bool) $spa?->hasFeature('inventory_basic'),
                'reports'      => $user->hasBranchPermission('view reports') && (bool) $spa?->hasFeature('insights'),
                'decision_support' => $user->hasBranchPermission('view decision support') && (bool) $spa?->hasFeature('insights'),
                'chat'         => app(\App\Services\Chat\ChatService::class)->staffCanReply($user),
            ],
            'branches'          => $request->attributes->get('owner_branches')->map(fn (Branch $b) => [
                'id'       => $b->id,
                'name'     => $b->name,
                'location' => $b->location,
                'is_main'  => (bool) $b->is_main,
                'locked'   => ! $b->isOperational(),
            ])->values(),
        ]);
    }

    /** Finished bookings (paged) and, on page 1, bookings still open after their end time. */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->attributes->get('owner_branch')->id;

        $history = Booking::with('therapist')
            ->where('spa_id', $user->spa_id)
            ->where('branch_id', $branchId)
            ->whereIn('status', Booking::FINISHED_STATUSES)
            ->orderByDesc('appointment_date')
            ->orderByDesc('start_time')
            ->paginate(self::PER_PAGE);

        $payload = [
            'history'   => $history->getCollection()->map(fn (Booking $b) => $this->booking($b))->values(),
            'page'      => $history->currentPage(),
            'last_page' => $history->lastPage(),
            'total'     => $history->total(),
        ];

        if ($history->currentPage() === 1) {
            $open = Booking::with('therapist')
                ->where('spa_id', $user->spa_id)
                ->where('branch_id', $branchId)
                ->overdue();

            $payload['needs_checkout_total'] = (clone $open)->count();
            $payload['needs_checkout'] = $open->orderBy('appointment_date')->orderBy('start_time')
                ->limit(50)->get()->map(fn (Booking $b) => $this->booking($b))->values();
        }

        return response()->json($payload);
    }

    /**
     * One week (Monday first) of the branch's bookings, day by day, with the
     * branch hours: the web's Schedule calendar as a list (ScheduleController
     * index/data). Readable treatment and therapist names, unlike data().
     */
    public function schedule(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->attributes->get('owner_branch')->id;

        $start = $request->filled('week')
            ? \Illuminate\Support\Carbon::parse((string) $request->query('week'))->startOfWeek(\Illuminate\Support\Carbon::MONDAY)
            : now()->startOfWeek(\Illuminate\Support\Carbon::MONDAY);
        $end = $start->copy()->addDays(6);

        $bookings = Booking::with('therapist')
            ->where('spa_id', $user->spa_id)
            ->where('branch_id', $branchId)
            ->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn (Booking $b) => $b->appointment_date->toDateString());

        $hours = OperatingHours::where('branch_id', $branchId)->get()->keyBy('day_of_week');

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $h = $hours->get($d->format('l'));
            $open = $h && ! $h->is_closed && $h->opening_time && $h->closing_time;

            $days[] = [
                'date'     => $d->toDateString(),
                'weekday'  => $d->format('D'),
                'day'      => (int) $d->format('j'),
                'label'    => $d->format('l, F j'),
                'is_today' => $d->isToday(),
                'closed'   => ! $open,
                'hours'    => $open
                    ? \Illuminate\Support\Carbon::parse($h->opening_time)->format('g:i A').' – '.\Illuminate\Support\Carbon::parse($h->closing_time)->format('g:i A')
                    : null,
                'bookings' => ($bookings[$d->toDateString()] ?? collect())
                    ->map(fn (Booking $b) => $this->booking($b))->values(),
            ];
        }

        return response()->json([
            'week_start' => $start->toDateString(),
            'week_label' => $start->format('M j').' – '.$end->format($start->month === $end->month ? 'j, Y' : 'M j, Y'),
            'prev_week'  => $start->copy()->subWeek()->toDateString(),
            'next_week'  => $start->copy()->addWeek()->toDateString(),
            'days'       => $days,
        ]);
    }

    public function services(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->attributes->get('owner_branch')->id;

        $treatments = Treatment::withoutGlobalScopes()
            ->where('spa_id', $user->spa_id)->where('branch_id', $branchId)
            ->orderBy('name')->get();

        $packages = Package::withoutGlobalScopes()
            ->where('spa_id', $user->spa_id)->where('branch_id', $branchId)
            ->with(['treatments' => fn ($q) => $q->withoutGlobalScopes()])
            ->orderBy('name')->get();

        return response()->json([
            'treatments' => $treatments->map(fn (Treatment $t) => [
                'id'                 => $t->id,
                'name'               => $t->name,
                'description'        => $t->description,
                'duration'           => (int) $t->duration,
                'price'              => (float) $t->price,
                'service_type'       => $t->service_type,
                'service_type_label' => $t->service_type_label,
                'image_url'          => $t->image_url,
            ])->values(),
            'packages' => $packages->map(fn (Package $p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'description' => $p->description,
                'duration'    => (int) $p->duration,
                'price'       => (float) $p->price,
                'treatments'  => $p->treatments->pluck('name')->values(),
                'image_url'   => $p->image_url,
            ])->values(),
        ]);
    }

    public function products(Request $request, InventoryStockService $stock): JsonResponse
    {
        $user = $request->user();
        $spaId = (int) $user->spa_id;
        $branchId = (int) $request->attributes->get('owner_branch')->id;

        $products = Product::where('spa_id', $spaId)
            ->whereHas('branchStocks', fn ($q) => $q->where('branch_id', $branchId))
            ->with([
                'branchStocks' => fn ($q) => $q->where('branch_id', $branchId),
                'batches' => fn ($q) => $q->where('branch_id', $branchId)
                    ->where('remaining_quantity', '>', 0)
                    ->orderByRaw('expiration_date IS NULL')
                    ->orderBy('expiration_date')
                    ->orderBy('received_at'),
            ])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.addcslashes((string) $request->query('q'), '%_').'%')
                ->orWhere('sku', 'like', '%'.addcslashes((string) $request->query('q'), '%_').'%')))
            ->orderBy('name')
            ->paginate(self::PER_PAGE);

        $rows = $products->getCollection()->map(function (Product $p) use ($stock, $spaId, $branchId) {
            $branchStock = $p->branchStocks->first();
            $onHand = (float) ($branchStock?->on_hand_quantity ?? 0);
            $reorder = (float) ($branchStock?->reorder_level ?? 0);
            $rec = $stock->reconciliationStatus($p, $spaId, $branchId);
            $expires = $p->batches->first()?->expiration_date;

            return [
                'id'              => $p->id,
                'name'            => $p->name,
                'sku'             => $p->sku,
                'brand'           => $p->brand,
                'unit'            => $p->usage_unit ?? $p->unit ?? 'pcs',
                'purchase_unit'   => $p->purchase_unit,
                'conversion_factor' => (float) ($p->conversion_factor ?? 1),
                'on_hand'         => $onHand,
                'reorder_level'   => $reorder,
                'low_stock'       => $reorder > 0 && $onHand <= $reorder,
                'reconciled'      => (bool) $rec['is_reconciled'],
                'batch_quantity'  => (float) $rec['batch_quantity'],
                'difference'      => (float) $rec['difference'],
                'nearest_expiration' => $expires ? \Illuminate\Support\Carbon::parse($expires)->format('M d, Y') : null,
            ];
        });

        $payload = [
            'products'  => $rows->values(),
            'page'      => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'total'     => $products->total(),
        ];

        if ($products->currentPage() === 1) {
            // Same 30-day list as the web's amber card.
            $payload['stock_shortfalls'] = BookingConsumption::query()
                ->where('spa_id', $spaId)->where('branch_id', $branchId)
                ->where('has_shortfall', true)
                ->where('consumed_at', '>=', now()->subDays(30))
                ->with('booking:id,customer_name,appointment_date')
                ->latest('consumed_at')->limit(10)->get()
                ->map(fn ($c) => [
                    'customer' => $c->booking?->customer_name ?? 'Customer',
                    'date'     => $c->booking?->appointment_date?->format('M d, Y'),
                ])->values();
        }

        return response()->json($payload);
    }

    public function logs(Request $request): JsonResponse
    {
        $user = $request->user();
        $spaId = (int) $user->spa_id;
        $branchId = (int) $request->attributes->get('owner_branch')->id;

        $request->validate([
            'product_id'    => ['nullable', 'integer'],
            'movement_type' => ['nullable', 'string', 'max:50'],
            'date_from'     => ['nullable', 'date'],
            'date_to'       => ['nullable', 'date'],
        ]);

        $logs = StockMovement::query()
            ->where('spa_id', $spaId)->where('branch_id', $branchId)
            ->with(['product', 'user'])
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('movement_type'), fn ($q) => $q->where('movement_type', $request->query('movement_type')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('occurred_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('occurred_at', '<=', $request->query('date_to')))
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');

        $payload = [
            'logs' => $logs->getCollection()->map(fn (StockMovement $l) => [
                'id'             => $l->id,
                'when'           => ($l->occurred_at ?? $l->created_at)?->format('M d, Y h:i A'),
                'product'        => $l->product?->name ?? 'Deleted Product',
                'sku'            => $l->product?->sku,
                'movement'       => ucwords(str_replace('_', ' ', (string) $l->movement_type)),
                'direction'      => $l->direction,                       // in | out
                'quantity'       => ($l->direction === 'in' ? '+' : '-').$num($l->quantity),
                'balance_before' => $num($l->balance_before),
                'balance_after'  => $num($l->balance_after),
                'unit'           => $l->unit,
                'reason'         => $l->notes ?: null,
                'reference'      => $l->reference_type
                    ? trim(ucwords(str_replace('_', ' ', $l->reference_type)).($l->reference_id ? ' #'.$l->reference_id : ''))
                    : null,
                'by'             => $l->user?->name ?? 'System',
            ])->values(),
            'page'      => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'total'     => $logs->total(),
        ];

        if ($logs->currentPage() === 1) {
            // The web's filter options.
            $payload['filters'] = [
                'products' => Product::where('spa_id', $spaId)
                    ->whereHas('branchStocks', fn ($q) => $q->where('branch_id', $branchId))
                    ->orderBy('name')->get(['id', 'name']),
                'movement_types' => StockMovement::where('spa_id', $spaId)->where('branch_id', $branchId)
                    ->whereNotNull('movement_type')->distinct()->orderBy('movement_type')
                    ->pluck('movement_type')
                    ->map(fn ($t) => ['value' => $t, 'label' => ucwords(str_replace('_', ' ', $t))])
                    ->values(),
            ];
        }

        return response()->json($payload);
    }

    private function booking(Booking $b): array
    {
        $total = (float) $b->total_amount;
        $paid = (float) $b->amount_paid;

        return [
            'id'                 => $b->id,
            'customer_name'      => $b->customer_name ?? 'Walk-in Customer',
            'treatment_label'    => $b->treatment_label,
            'service_type'       => $b->service_type,
            'service_type_label' => $b->service_type_label,
            'booking_source'     => $b->booking_source,
            'start_time'         => $b->start_time,
            'appointment_date'   => $b->appointment_date?->format('M d, Y'),
            'appointment_date_raw' => $b->appointment_date?->format('Y-m-d'),
            'start_time_fmt'     => $b->start_time ? \Illuminate\Support\Carbon::parse($b->start_time)->format('h:i A') : '—',
            'end_time_fmt'       => $b->end_time ? \Illuminate\Support\Carbon::parse($b->end_time)->format('h:i A') : '—',
            'therapist_name'     => $b->therapist ? trim($b->therapist->first_name.' '.$b->therapist->last_name) : 'Not Assigned',
            'status'             => $b->status,
            'status_label'       => Booking::statusLabel($b->status),
            'total_amount'       => $total,
            'amount_paid'        => $paid,
            'balance'            => max($total - $paid, 0),
        ];
    }

    /** The web Reports page's numbers for the branch, ?from=&to= (Y-m-d) like the web filter. */
    public function reports(Request $request): JsonResponse
    {
        $r = app(ReportsController::class)->reportData($request);

        // Anything that isn't online or walk-in counts as Staff (reports blade rule).
        $sources = ['online' => 0, 'walk_in' => 0, 'staff' => 0];
        foreach ($r['sourceCounts'] as $src => $n) {
            $key = array_key_exists($src, $sources) ? $src : 'staff';
            $sources[$key] += (int) $n;
        }

        return response()->json([
            'filters'          => $r['filters'],
            'total_bookings'   => $r['totalBookings'],
            'status_summary'   => $r['statusSummary'],
            'gross_revenue'    => round($r['grossRevenue'], 2),
            'kept_deposits'    => $r['keptDeposits'],
            'owed_on_open'     => $r['owedOnOpen'],
            'treat_revenue'    => round($r['treatRevenue'], 2),
            'pkg_revenue'      => round($r['pkgRevenue'], 2),
            'sources'          => $sources,
            'services'         => $r['serviceRows'],
            'assigned_count'   => $r['assignedCount'],
            'unassigned_count' => $r['unassignedCount'],
            'therapists'       => $r['therapistRows'],
            'bookings_per_day' => $r['bookingsPerDay'],
            'revenue_per_day'  => $r['revenuePerDay'],
        ]);
    }

    /** The web Decision Support page, read only: summary, recommendations, 7-day forecast, history. */
    public function decisionSupport(Request $request): JsonResponse
    {
        $d = app(DecisionSupportController::class)->pageData($request);

        $rec = fn (DssRecommendation $r) => [
            'id'          => $r->id,
            'priority'    => $r->priority,
            'rule_key'    => $r->rule_key,
            'rule_label'  => $r->ruleLabel(),
            'scope'       => $r->scope,
            'branch_name' => $r->branch?->name,
            'title'       => $r->title,
            'message'     => $r->message,
            'why'         => array_values((array) ($r->details['why'] ?? [])),
            'first_seen'  => $r->first_seen_at?->diffForHumans() ?? 'just now',
            'last_seen'   => $r->last_seen_at?->diffForHumans() ?? 'just now',
        ];

        $forecast = $d['forecast'];

        return response()->json([
            'last_checked' => $d['lastSync'] ? Carbon::parse($d['lastSync'])->diffForHumans() : null,
            'thresholds'   => $d['thresholds'],
            'forecast'     => [
                'enough_data' => (bool) $forecast['enough_data'],
                'weeks'       => (int) $forecast['weeks'],
                'days'        => collect($forecast['days'])->map(fn (array $day) => [
                    'date'           => $day['date'],
                    'label'          => $day['label'],
                    'open'           => (bool) $day['open'],
                    'therapists'     => (int) $day['therapists'],
                    'booked_count'   => (int) $day['booked_count'],
                    'expected_count' => (float) $day['expected_count'],
                    'use'            => $day['use'],
                    'short_hours'    => $day['short_hours'] ? DemandForecaster::hourRanges($day['short_hours']) : null,
                ])->values(),
            ],
            'recommendations' => $d['recommendations']->map($rec)->values(),
            'history' => $d['history']->map(fn (DssRecommendation $h) => [
                'id'            => $h->id,
                'status'        => $h->status,
                'title'         => $h->title,
                'rule_label'    => $h->ruleLabel(),
                'decided_at'    => $h->decided_at?->format('M j, Y g:i A'),
                'decided_by'    => trim(($h->decider->first_name ?? '').' '.($h->decider->last_name ?? '')) ?: 'a former user',
                'dismiss_reason'=> $h->dismissReasonLabel(),
                'note'          => $h->note,
            ])->values(),
        ]);
    }
}
