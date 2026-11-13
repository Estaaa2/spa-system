<?php

namespace Database\Seeders\Demo;

use App\Models\Applicant;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\Interview;
use App\Models\JobPosting;
use App\Models\PayrollRun;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductLog;
use App\Models\Promo;
use App\Models\PurchaseOrder;
use App\Models\Spa;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Treatment;
use App\Models\User;
use App\Models\VendorBill;
use App\Services\BookingConsumptionService;
use App\Services\GoodsReceiptService;
use App\Services\Payroll\PayrollRunService;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseRequestService;
use App\Services\ThreeWayMatchService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The Premium-only parts of the showcase spa, made through the platform's own
 * services where the numbers must add up (stock, procurement, payroll), with
 * the clock moved back (Carbon::setTestNow) so every row carries the date it
 * would really have happened on.
 */
final class DemoOperations
{
    /**
     * key => [name, category, type, usage unit, purchase unit, conversion, cost per purchase unit, retail price]
     */
    private const PRODUCTS = [
        'oil'      => ['Massage Oil (Unscented)', 'Oils', 'backbar', 'ml', 'bottle (1 L)', 1000, 380, null],
        'coconut'  => ['Virgin Coconut Oil', 'Oils', 'backbar', 'ml', 'bottle (1 L)', 1000, 320, null],
        'lavender' => ['Lavender Essential Oil', 'Essential oils', 'backbar', 'ml', 'bottle (30 ml)', 30, 450, null],
        'scrub'    => ['Sea Salt Body Scrub', 'Scrubs', 'backbar', 'g', 'tub (1 kg)', 1000, 520, null],
        'cream'    => ['Foot Massage Cream', 'Creams', 'backbar', 'g', 'tub (500 g)', 500, 300, null],
        'alcohol'  => ['Isopropyl Alcohol 70%', 'Sanitation', 'backbar', 'ml', 'bottle (500 ml)', 500, 90, null],
        'covers'   => ['Disposable Face Cradle Covers', 'Disposables', 'backbar', 'pcs', 'pack (100 pcs)', 100, 250, null],
        'liniment' => ['Hilot Herbal Liniment 60 ml', 'Retail', 'retail', 'pcs', 'box (12 pcs)', 12, 1200, 180],
    ];

    /** product key => [SKU, brand] */
    private const SKUS = [
        'oil' => ['MO-1L-01', 'Healing Touch'], 'coconut' => ['VCO-1L-02', 'CocoPure'], 'lavender' => ['EO-LAV-30', 'Aroma Botanica'],
        'scrub' => ['SCR-SALT-1K', 'Glow Naturals'], 'cream' => ['CRM-FT-500', 'Healing Touch'], 'alcohol' => ['ALC-70-500', 'Green Cross'],
        'covers' => ['DSP-FC-100', 'SpaCare'], 'liniment' => ['RT-HLT-60', 'Lola Remedios'],
    ];

    /** treatment key => [product key => quantity per service] */
    private const RECIPES = [
        'swedish'  => ['oil' => 30, 'covers' => 1],
        'hilot'    => ['coconut' => 35, 'covers' => 1],
        'shiatsu'  => ['covers' => 1],
        'hotstone' => ['oil' => 40, 'covers' => 1],
        'ventosa'  => ['oil' => 30, 'alcohol' => 10],
        'foot'     => ['cream' => 20],
        'aroma'    => ['oil' => 35, 'lavender' => 1.5, 'covers' => 1],
        'scrub'    => ['scrub' => 120, 'coconut' => 20],
        'back'     => ['oil' => 15],
        'prenatal' => ['coconut' => 30, 'covers' => 1],
    ];

    /** @var array<string, Product> */
    private array $products = [];

    public function __construct(private readonly DemoBuilder $b)
    {
        mt_srand(5150);
    }

    // ── Inventory + procurement + stock used by bookings ───────────────────

    /**
     * @param list<array<string, mixed>> $plans branch plans (DemoHistory format)
     * @param array{owner: User, finance: User, managers: array<int, User>, receptions: array<int, User>} $people
     */
    public function inventory(Spa $spa, array $plans, array $bookings, Carbon $from, array $people): void
    {
        $owner = $people['owner'];

        $suppliers = [
            $this->supplier($spa, 'Cavite Spa Supplies Trading', 'Liza Manalo', 'Imus, Cavite'),
            $this->supplier($spa, 'Luzon Wellness Distributors', 'Ramon Aquino', 'Dasmariñas, Cavite'),
            $this->supplier($spa, 'Batangas Coco Products', 'Gina Villanueva', 'Lipa, Batangas'),
        ];

        $historyDays = max(1, (int) $from->diffInDays(Carbon::today()));
        $nearExpiry = Carbon::today()->addDays(20);   // before the clock is moved back below

        // Monthly restock: each branch receives a delivery every 30 days (branches a day apart).
        $receipts = [];
        foreach ($plans as $p => $plan) {
            for ($at = $from->copy()->addDays(30 + $p)->setTime(10, 30); $at->lt(Carbon::today()->subDays(3)); $at->addDays(30)) {
                $receipts[$plan['branch']->id][] = $at->copy();
            }
        }
        $usage = $this->plannedUsage($plans, $bookings, $receipts);

        Carbon::setTestNow($from->copy()->subDay()->setTime(9, 0));
        foreach (self::PRODUCTS as $key => [$name, $category, $type, $unit, $buyUnit, $factor, $cost, $retail]) {
            $this->products[$key] = Product::create([
                'spa_id' => $spa->id, 'sku' => self::SKUS[$key][0], 'name' => $name, 'brand' => self::SKUS[$key][1],
                'category' => $category, 'inventory_type' => $type, 'stock_quantity' => 0, 'unit_value' => 0,
                'unit' => $unit, 'purchase_unit' => $buyUnit, 'usage_unit' => $unit, 'conversion_factor' => $factor,
                'retail_price' => $retail, 'acquisition_cost' => $cost, 'is_active' => true,
            ]);
            foreach ($suppliers as $i => $supplier) {
                if ($i < 2 || $key === 'coconut') {
                    DB::table('supplier_products')->insert([
                        'supplier_id' => $supplier->id, 'product_id' => $this->products[$key]->id,
                        'unit_cost' => $cost * ($i === 1 ? 1.06 : 1.0), 'lead_time_days' => 3 + $i * 2,
                        'minimum_order_quantity' => 1, 'is_preferred' => $i !== 1, 'is_active' => true,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }

        // Recipes: which products each service uses.
        foreach ($plans as $plan) {
            foreach ($plan['services']['treatments'] as $key => $treatment) {
                foreach (self::RECIPES[$key] ?? [] as $productKey => $qty) {
                    DB::table('treatment_recipe_items')->insert([
                        'treatment_id' => $treatment->id, 'product_id' => $this->products[$productKey]->id,
                        'quantity' => $qty, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }

        // Opening stock: enough for the first month plus a cushion.
        foreach ($plans as $p => $plan) {
            $branch = $plan['branch'];
            foreach ($this->products as $key => $product) {
                $periods = $usage[$branch->id][$key] ?? [0];
                $daily = max(array_sum($periods) / $historyDays, max($periods) / 30);   // the busiest month sets the reorder level
                $opening = $key === 'liniment' ? 36 : min(19000, $this->roundUp(($periods[0] ?? 0) * 1.12 + $daily * 6, $product));
                $reorder = $key === 'liniment' ? 12 : $this->roundUp($daily * 10, $product);
                $expiry = $key === 'lavender' && $p === 2 ? $nearExpiry : null;   // near-expiry alert

                $this->openingStock($spa, $branch, $product, $opening, $reorder, $expiry, $owner);
            }
        }
        Carbon::setTestNow();

        // Each delivery covers the month after it, through the full procurement flow.
        $deliveries = [];
        foreach ($plans as $p => $plan) {
            $branch = $plan['branch'];
            $last = count($receipts[$branch->id] ?? []) - 1;
            foreach ($receipts[$branch->id] ?? [] as $k => $at) {
                $items = [];
                foreach ($this->products as $key => $product) {
                    if ($key === 'liniment') {
                        continue;
                    }
                    $need = $usage[$branch->id][$key][$k + 1] ?? 0;
                    // Left off the latest order, so they are below the reorder level today:
                    // oil and covers at busy Bacoor (a request for them is waiting), lavender
                    // at Imus (an order for it is on the way).
                    $skipped = $k === $last && (($p === 1 && in_array($key, ['oil', 'covers'], true)) || ($p === 0 && $key === 'lavender'));
                    $factor = 1.05;
                    if ($need > 0 && ! $skipped) {
                        $items[$key] = max(1, (int) ceil($need * $factor / (float) $product->conversion_factor));
                    }
                }
                $deliveries[] = [$plan, $items, $at, $suppliers[($p + $k) % 2]];
            }
        }

        $this->consumeTimeline($bookings, $deliveries, $people, $plans);

        // Still open today: an issued order on its way, a request waiting, one turned down.
        $this->openProcurement($spa, $plans, $suppliers, $people);
    }

    /** Usage per branch, product and period (period k = after the k-th delivery). */
    private function plannedUsage(array $plans, array $bookings, array $receipts): array
    {
        $byTreatment = [];
        $packages = [];
        foreach ($plans as $plan) {
            foreach ($plan['services']['treatments'] as $key => $t) {
                $byTreatment[$t->id] = self::RECIPES[$key] ?? [];
            }
            foreach ($plan['services']['packages'] as $pkg) {
                $packages[$pkg->id] = DB::table('package_treatment')->where('package_id', $pkg->id)->pluck('treatment_id')->all();
            }
        }

        $usage = [];
        foreach ($bookings as $booking) {
            if ($booking['status'] !== 'completed') {
                continue;
            }
            [$type, $id] = explode('_', $booking['treatment'], 2);
            $treatmentIds = $type === 'treatment' ? [(int) $id] : ($packages[(int) $id] ?? []);
            $end = Carbon::parse($booking['end']);
            $half = count(array_filter($receipts[$booking['branch_id']] ?? [], fn (Carbon $r) => $r->lte($end)));
            foreach ($treatmentIds as $tid) {
                foreach ($byTreatment[$tid] ?? [] as $productKey => $qty) {
                    $usage[$booking['branch_id']][$productKey][$half] = ($usage[$booking['branch_id']][$productKey][$half] ?? 0) + $qty;
                }
            }
        }

        return $usage;
    }

    private function roundUp(float $qty, Product $product): float
    {
        $step = in_array($product->usage_unit, ['pcs'], true) ? 10 : 100;

        return max($step, ceil($qty / $step) * $step);
    }

    private function openingStock(Spa $spa, Branch $branch, Product $product, float $qty, float $reorder, ?Carbon $expiry, User $by): void
    {
        $stock = BranchProductStock::create([
            'spa_id' => $spa->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'on_hand_quantity' => $qty, 'reorder_level' => $reorder,
            'minimum_stock' => round($reorder / 2, 3), 'maximum_stock' => null,
        ]);
        $batch = ProductBatch::create([
            'spa_id' => $spa->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'batch_number' => 'OPENING-'.$branch->id.'-'.$product->id, 'received_quantity' => $qty,
            'remaining_quantity' => $qty, 'unit_cost' => $product->acquisition_cost,
            'expiration_date' => $expiry?->toDateString(), 'received_at' => now(),
        ]);
        StockMovement::create([
            'spa_id' => $spa->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'product_batch_id' => $batch->id, 'user_id' => $by->id, 'movement_type' => 'opening_balance',
            'direction' => 'in', 'quantity' => $qty, 'unit' => $product->usage_unit,
            'balance_before' => 0, 'balance_after' => $stock->on_hand_quantity,
            'reference_type' => 'product_creation', 'reference_id' => $product->id,
            'notes' => 'Opening inventory balance.', 'occurred_at' => now(),
        ]);
        ProductLog::create([
            'spa_id' => $spa->id, 'product_id' => $product->id, 'user_id' => $by->id,
            'description' => "{$product->name} was added to the {$branch->name} inventory", 'logged_at' => now(),
        ]);
    }

    /** Deliveries and service usage in the order they happened, each at its own time. */
    private function consumeTimeline(array $bookings, array $deliveries, array $people, array $plans): void
    {
        $events = [];
        foreach ($bookings as $booking) {
            if ($booking['status'] === 'completed') {
                $events[] = [$booking['end'], 'use', $booking];
            }
        }
        foreach ($deliveries as $delivery) {
            $events[] = [$delivery[2]->toDateTimeString(), 'delivery', $delivery];
        }
        usort($events, fn ($a, $b) => strcmp($a[0], $b[0]));

        $consumer = app(BookingConsumptionService::class);
        foreach ($events as [$at, $kind, $payload]) {
            Carbon::setTestNow(Carbon::parse($at));
            if ($kind === 'use') {
                $by = $people['receptions'][$payload['branch_id']] ?? $people['owner'];
                $consumer->consume(Booking::query()->findOrFail($payload['id']), $by);
            } else {
                $this->procure($payload, $people);
            }
        }
        Carbon::setTestNow();
    }

    /** Request → approve → order → issue → receive → bill → 3-way match (→ approve → pay). */
    private function procure(array $delivery, array $people): void
    {
        [$plan, $items, $receivedAt, $supplier] = $delivery;
        if ($items === []) {
            return;
        }
        $branch = $plan['branch'];
        $manager = $people['managers'][$branch->id];
        $owner = $people['owner'];
        $finance = $people['finance'];

        Carbon::setTestNow($receivedAt->copy()->subDays(4)->setTime(14, 10));
        $request = app(PurchaseRequestService::class)->create($manager, $branch->id,
            collect($items)->map(fn ($qty, $key) => ['product_id' => $this->products[$key]->id, 'quantity' => $qty])->values()->all(),
            'Monthly restock: oils and disposables are running low.');

        Carbon::setTestNow($receivedAt->copy()->subDays(3)->setTime(9, 20));
        app(PurchaseRequestService::class)->approve($request, $owner, 'Approved. Order from our usual supplier.');
        $order = app(PurchaseOrderService::class)->createFromPurchaseRequest($request->fresh(), $supplier, $owner,
            'Deliver to the branch front desk.', $receivedAt->toDateString());
        app(PurchaseOrderService::class)->issue($order, $owner);

        Carbon::setTestNow($receivedAt);
        $order = $order->fresh('items');
        $receipt = app(GoodsReceiptService::class)->receive($order, $manager, $order->items->map(fn ($item, $i) => [
            'purchase_order_item_id' => $item->id,
            'received_quantity' => (float) $item->quantity, 'accepted_quantity' => (float) $item->quantity, 'rejected_quantity' => 0,
            'batch_number' => 'B'.$receivedAt->format('ymd').'-'.$branch->id.'-'.($i + 1),
            'manufactured_at' => $receivedAt->copy()->subMonths(2)->toDateString(),
            'expiration_date' => $receivedAt->copy()->addYears(2)->toDateString(),
        ])->values()->all(), 'DR-'.$receivedAt->format('ymd').'-'.$branch->id, $receivedAt->toDateTimeString());

        Carbon::setTestNow($receivedAt->copy()->addDay()->setTime(11, 0));
        $subtotal = 0;
        $billItems = [];
        foreach ($order->items as $item) {
            $line = round((float) $item->quantity * (float) $item->unit_cost, 2);
            $subtotal += $line;
            $billItems[] = [
                'purchase_order_item_id' => $item->id, 'product_id' => $item->product_id,
                'billed_quantity' => $item->quantity, 'unit' => $item->unit, 'unit_cost' => $item->unit_cost, 'line_total' => $line,
            ];
        }
        $bill = VendorBill::create([
            'spa_id' => $order->spa_id, 'branch_id' => $order->branch_id, 'supplier_id' => $order->supplier_id,
            'purchase_order_id' => $order->id, 'created_by' => $finance->id,
            'invoice_number' => 'SI-'.$receivedAt->format('ymd').'-'.str_pad((string) $order->id, 4, '0', STR_PAD_LEFT),
            'invoice_date' => $receivedAt->toDateString(), 'due_date' => $receivedAt->copy()->addDays(30)->toDateString(),
            'subtotal' => round($subtotal, 2), 'total_amount' => round($subtotal, 2),
            'status' => VendorBill::STATUS_PENDING_MATCH, 'notes' => null,
        ]);
        $bill->items()->createMany($billItems);
        session(['current_branch_id' => $branch->id]);   // the matcher works on the user's current branch
        $bill = app(ThreeWayMatchService::class)->match($bill, $finance);
        session()->forget('current_branch_id');

        // Main branch: approved and paid. Others: matched, waiting for approval.
        if ($branch->is_main && $bill->status === VendorBill::STATUS_MATCHED) {
            Carbon::setTestNow($receivedAt->copy()->addDays(3)->setTime(15, 0));
            $bill->forceFill(['status' => VendorBill::STATUS_APPROVED, 'approved_by' => $owner->id, 'approved_at' => now()])->save();
            Carbon::setTestNow($receivedAt->copy()->addDays(12)->setTime(10, 0));
            $bill->forceFill(['status' => VendorBill::STATUS_PAID, 'paid_at' => now()])->save();
        }
    }

    private function openProcurement(Spa $spa, array $plans, array $suppliers, array $people): void
    {
        Carbon::setTestNow();
        $today = Carbon::today();
        $main = $plans[0]['branch'];

        // Issued 2 days ago, arriving in 3 days → shows as incoming stock.
        Carbon::setTestNow($today->copy()->subDays(3)->setTime(16, 0));
        $request = app(PurchaseRequestService::class)->create($people['managers'][$main->id], $main->id, [
            ['product_id' => $this->products['lavender']->id, 'quantity' => 6],
            ['product_id' => $this->products['liniment']->id, 'quantity' => 2],
        ], 'Lavender for aromatherapy and liniment for the retail shelf.');
        Carbon::setTestNow($today->copy()->subDays(2)->setTime(9, 30));
        app(PurchaseRequestService::class)->approve($request, $people['owner']);
        $order = app(PurchaseOrderService::class)->createFromPurchaseRequest($request->fresh(), $suppliers[0], $people['owner'],
            null, $today->copy()->addDays(3)->toDateString());
        app(PurchaseOrderService::class)->issue($order, $people['owner']);

        // Waiting for the owner's review.
        if (isset($plans[1])) {
            $bacoor = $plans[1]['branch'];
            Carbon::setTestNow($today->copy()->subDay()->setTime(17, 45));
            app(PurchaseRequestService::class)->create($people['managers'][$bacoor->id], $bacoor->id, [
                ['product_id' => $this->products['oil']->id, 'quantity' => 12],
                ['product_id' => $this->products['covers']->id, 'quantity' => 8],
            ], 'Below reorder level. We ran out of oil twice this week.');
        }

        // Turned down.
        if (isset($plans[2])) {
            $dasma = $plans[2]['branch'];
            Carbon::setTestNow($today->copy()->subDays(20)->setTime(13, 0));
            $rejected = app(PurchaseRequestService::class)->create($people['managers'][$dasma->id], $dasma->id, [
                ['product_id' => $this->products['scrub']->id, 'quantity' => 10],
            ], 'Stock up on body scrub for the holidays.');
            Carbon::setTestNow($today->copy()->subDays(19)->setTime(10, 0));
            app(PurchaseRequestService::class)->reject($rejected, $people['owner'], 'We still have enough scrub for a month. Request again in December.');
        }

        Carbon::setTestNow();
    }

    private function supplier(Spa $spa, string $name, string $contact, string $address): Supplier
    {
        return Supplier::create([
            'spa_id' => $spa->id, 'name' => $name, 'contact_person' => $contact,
            'email' => 'sales@'.strtolower(preg_replace('/[^A-Za-z]/', '', strtok($name, ' '))).'.test', 'phone' => '0917'.mt_rand(1000000, 9999999),
            'address' => $address, 'status' => 'active',
        ]);
    }

    // ── Payroll ────────────────────────────────────────────────────────────

    /**
     * Every finished cutoff since $from: older ones released, the second-latest
     * finalized (approved, waiting to be released), the latest left as a draft
     * so the review → approve → finalize steps can be shown live.
     *
     * @return list<string> messages for the console
     */
    public function payroll(Spa $spa, Carbon $from, User $preparer, User $approver, User $owner): array
    {
        Carbon::setTestNow();
        $service = app(PayrollRunService::class);
        $cutoffs = [];
        for ($month = $from->copy()->startOfMonth(); $month->lte(Carbon::today()); $month->addMonthNoOverflow()) {
            foreach ([1, 2] as $no) {
                $period = $service->buildPeriod($spa, $month->year, $month->month, $no);
                // Cutoff 2 needs cutoff 1 of the same month, so start on a month's first cutoff.
                $startsInHistory = Carbon::parse($period->start)->gte($from) && ($no === 1 || $cutoffs !== []);
                if ($startsInHistory && Carbon::parse($period->end)->lt(Carbon::today())) {
                    $cutoffs[] = [$month->year, $month->month, $no, $period];
                }
            }
        }

        $messages = [];
        $last = count($cutoffs) - 1;
        foreach ($cutoffs as $i => [$year, $month, $no, $period]) {
            $end = Carbon::parse($period->end);
            $payDate = Carbon::parse($period->payDate);

            Carbon::setTestNow($end->copy()->addDay()->setTime(10, 0));
            $result = $service->generateRegular($spa, $year, $month, $no, $preparer);
            $run = $result->run;
            $codes = array_count_values(array_map(fn ($w) => $w->code, $result->warnings));
            $messages[] = sprintf('Payroll %s cutoff %d warnings: %s', $end->format('M Y'), $no, json_encode($codes));

            if ($i < $last) {
                Carbon::setTestNow($end->copy()->addDays(2)->setTime(15, 0));
                $run = $service->approve($run, $approver);
                Carbon::setTestNow($payDate->copy()->subDay()->setTime(11, 0));
                $run = $service->finalize($run, $owner);
            }
            if ($i < $last - 1) {
                Carbon::setTestNow($payDate->copy()->setTime(9, 0));
                $service->release($run, $owner);
            }
        }
        Carbon::setTestNow();

        return $messages;
    }

    // ── Hiring ─────────────────────────────────────────────────────────────

    /** @param array<int, User> $hiredStaffUsers staff user who was hired through an old posting */
    public function hiring(Spa $spa, array $branches, User $creator, User $interviewer, ?User $hiredStaff): void
    {
        Carbon::setTestNow();
        $today = Carbon::today();
        [$main, $second] = [$branches[0], $branches[1] ?? $branches[0]];
        $schedule = function (array $rest, array $custom = []): array {
            $out = [];
            foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $d) {
                $out[$d] = in_array($d, $rest, true) ? ['mode' => 'rest']
                    : (isset($custom[$d]) ? ['mode' => 'custom', 'start' => $custom[$d][0], 'end' => $custom[$d][1]] : ['mode' => 'branch']);
            }

            return $out;
        };
        $base = ['spa_id' => $spa->id, 'created_by' => $creator->id];

        Carbon::setTestNow($today->copy()->subDays(12)->setTime(10, 0));
        $open = JobPosting::create($base + [
            'branch_id' => $second->id, 'title' => 'Licensed Massage Therapist', 'role' => 'therapist', 'openings' => 2,
            'employment_type' => 'full_time', 'status' => 'open', 'deadline' => $today->copy()->addDays(18)->toDateString(),
            'schedule' => $schedule(['Tuesday']), 'pay_basis' => 'daily', 'pay_min' => 600, 'pay_max' => 750, 'show_pay' => true,
            'description' => 'Swedish, hilot and ventosa for walk-in and home-service guests. Commission on every service.',
            'requirements' => 'TESDA Massage Therapy NC II. At least 1 year of spa experience is a plus.',
        ]);
        Carbon::setTestNow($today->copy()->subDays(5)->setTime(9, 0));
        JobPosting::create($base + [
            'branch_id' => $main->id, 'title' => 'Front Desk Associate', 'role' => 'receptionist', 'openings' => 1,
            'employment_type' => 'part_time', 'status' => 'open',
            'schedule' => $schedule(['Monday', 'Tuesday'], ['Saturday' => ['12:00', '21:00']]),
            'pay_basis' => 'monthly', 'pay_min' => 14000, 'pay_max' => 16000, 'show_pay' => false,
            'description' => 'Greets guests, takes bookings and handles payments.',
        ]);
        JobPosting::create($base + [
            'branch_id' => $main->id, 'title' => 'Assistant Branch Manager', 'role' => 'manager', 'openings' => 1,
            'employment_type' => 'full_time', 'status' => 'draft', 'schedule' => $schedule(['Sunday']),
        ]);
        Carbon::setTestNow($today->copy()->subDays(70)->setTime(10, 0));
        $filled = JobPosting::create($base + [
            'branch_id' => $main->id, 'title' => 'Massage Therapist', 'role' => 'therapist', 'openings' => 1,
            'employment_type' => 'full_time', 'status' => 'closed', 'closed_reason' => JobPosting::CLOSED_FILLED,
            'closed_at' => $today->copy()->subDays(55), 'schedule' => $schedule(['Wednesday']),
            'pay_basis' => 'daily', 'pay_min' => 600, 'pay_max' => 650, 'show_pay' => true,
            'description' => 'Full-time therapist for the main branch.',
        ]);

        $applicants = [
            [$open, 'Joy Navarro', Applicant::STATUS_PENDING, 2, null],
            [$open, 'Mark Ilagan', Applicant::STATUS_PENDING, 1, null],
            [$open, 'Cielo Ramos', Applicant::STATUS_INTERVIEW, 6, ['pending', 2]],
            [$open, 'Arnel Bautista', Applicant::STATUS_APPROVED, 9, ['approved', -3]],
            [$open, 'Kat Ocampo', Applicant::STATUS_REJECTED, 10, ['rejected', -5]],
        ];
        foreach ($applicants as $i => [$posting, $name, $status, $daysAgo, $interview]) {
            Carbon::setTestNow($today->copy()->subDays($daysAgo)->setTime(20, 15));
            $applicant = Applicant::create([
                'spa_id' => $spa->id, 'branch_id' => $posting->branch_id, 'job_posting_id' => $posting->id,
                'position_applied' => $posting->role, 'full_name' => $name,
                'email' => DemoCatalog::email(...explode(' ', $name, 2), domain: DemoCatalog::CUSTOMER_DOMAIN), 'phone' => '0918000000'.($i + 1),
                'address' => 'Bacoor, Cavite', 'education' => 'vocational', 'schedule_fit' => $i !== 1,
                'schedule_fit_note' => $i === 1 ? 'Can only work weekends.' : null,
                'work_experience' => '2 years as a massage therapist in a Cavite spa.',
                'source' => 'website', 'status' => $status,
                'privacy_consent_version' => '2026-11-06', 'privacy_consent_at' => now(),
            ]);
            if ($interview) {
                [$iStatus, $offset] = $interview;
                Interview::create([
                    'applicant_id' => $applicant->id, 'spa_id' => $spa->id, 'branch_id' => $posting->branch_id,
                    'interviewed_by' => $interviewer->id,
                    'interview_date' => $today->copy()->addDays($offset)->toDateString(), 'interview_time' => '14:00:00',
                    'status' => $iStatus,
                    'remarks' => $iStatus === 'approved' ? 'Strong hilot technique, good with guests.' : null,
                    'rejection_reason' => $iStatus === 'rejected' ? 'Not available on weekends, our busiest days.' : null,
                    'applicant_message' => $iStatus === 'pending' ? 'Please bring your TESDA certificate and one valid ID.' : null,
                ]);
            }
        }

        if ($hiredStaff) {
            Carbon::setTestNow($today->copy()->subDays(66)->setTime(19, 0));
            $hired = Applicant::create([
                'spa_id' => $spa->id, 'branch_id' => $main->id, 'job_posting_id' => $filled->id,
                'position_applied' => 'therapist', 'full_name' => $hiredStaff->first_name.' '.$hiredStaff->last_name,
                'email' => $hiredStaff->email, 'phone' => $hiredStaff->phone, 'address' => 'Imus, Cavite',
                'education' => 'vocational', 'schedule_fit' => true, 'source' => 'website', 'status' => Applicant::STATUS_HIRED,
            ]);
            Interview::create([
                'applicant_id' => $hired->id, 'spa_id' => $spa->id, 'branch_id' => $main->id, 'interviewed_by' => $interviewer->id,
                'interview_date' => $today->copy()->subDays(60)->toDateString(), 'interview_time' => '10:00:00',
                'status' => 'approved', 'remarks' => 'Hired.', 'staff_account_created' => true,
            ]);
            DB::table('staff')->where('user_id', $hiredStaff->id)->update(['applicant_id' => $hired->id]);
        }

        DB::table('branch_profiles')->where('branch_id', $second->id)->update(['is_hiring' => true, 'hiring_note' => 'Hiring licensed massage therapists!']);
        Carbon::setTestNow();
    }

    // ── Expenses & promos ──────────────────────────────────────────────────

    public function expenses(Spa $spa, array $branches, array $requesters, User $reviewer): void
    {
        Carbon::setTestNow();
        $today = Carbon::today();
        $rows = [
            ['Electricity bill (Meralco)', 18450.75, 'accepted', 40], ['Water bill', 2310.00, 'accepted', 38],
            ['Laundry service for towels', 6800.00, 'accepted', 25], ['Aircon cleaning', 3500.00, 'accepted', 18],
            ['Replacement massage bed cover', 2450.00, 'rejected', 12], ['Electricity bill (Meralco)', 19880.40, 'on_review', 6],
            ['Plumbing repair, shower room', 1800.00, 'pending', 2], ['Christmas décor', 4200.00, 'pending', 1],
        ];
        foreach ($rows as $i => [$title, $amount, $status, $daysAgo]) {
            $branch = $branches[$i % count($branches)];
            $at = $today->copy()->subDays($daysAgo)->setTime(11, 0);
            DB::table('expenses')->insert([
                'spa_id' => $spa->id, 'branch_id' => $branch->id, 'requested_by' => $requesters[$branch->id]->id,
                'reviewed_by' => in_array($status, ['accepted', 'rejected', 'on_review'], true) ? $reviewer->id : null,
                'title' => $title, 'description' => 'Demo expense for '.$branch->name.'.', 'amount' => $amount, 'status' => $status,
                'review_notes' => $status === 'rejected' ? 'Use the spare covers in the stockroom first.' : null,
                'reviewed_at' => in_array($status, ['accepted', 'rejected'], true) ? $at->copy()->addDay() : null,
                'created_at' => $at, 'updated_at' => $at,
            ]);
        }
    }

    /** @param array<string, Treatment> $treatments */
    public function promos(Spa $spa, Branch $branch, array $treatments): void
    {
        Carbon::setTestNow();
        $today = Carbon::today();
        $promos = [
            ['Payday Treat — 15% off Swedish & Hilot', 'percent', 15, $today->copy()->subDays(3), $today->copy()->addDays(12), ['swedish', 'hilot']],
            ['Undas Weekend ₱100 off', 'fixed', 100, $today->copy()->subDays(16), $today->copy()->subDays(12), ['foot', 'back']],
        ];
        foreach ($promos as [$name, $type, $value, $start, $end, $keys]) {
            $promo = Promo::create([
                'spa_id' => $spa->id, 'branch_id' => $branch->id, 'name' => $name, 'discount_type' => $type,
                'discount_value' => $value, 'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(), 'is_active' => true,
            ]);
            foreach ($keys as $key) {
                if (isset($treatments[$key])) {
                    DB::table('promo_treatment')->insert(['promo_id' => $promo->id, 'treatment_id' => $treatments[$key]->id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }
}
