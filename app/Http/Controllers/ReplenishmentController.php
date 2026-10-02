<?php

namespace App\Http\Controllers;

use App\Services\ReplenishmentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReplenishmentController extends Controller
{
    public function __construct(
        protected ReplenishmentService $replenishmentService
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $branchId = $user->currentBranchId();

        abort_unless($branchId, 404);

        $replenishment = $this->replenishmentService
            ->forBranch($user->spa_id,$branchId);

        $summary = [
            'total_products' => $replenishment->count(),

            'needs_reorder' => $replenishment
                ->where('needs_reorder', true)
                ->count(),

            'critical' => $replenishment
                ->whereIn('status', [
                    'critical',
                    'out_of_stock',
                ])
                ->count(),

            'incoming_products' => $replenishment
                ->filter(fn($item) =>
                    (float) $item['incoming_quantity'] > 0
                )
                ->count(),
        ];

        return view('inventory.replenishment.index', compact(
            'replenishment',
            'summary'
        ));
    }
}
