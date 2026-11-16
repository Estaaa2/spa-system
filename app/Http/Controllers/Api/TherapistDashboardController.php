<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController;
use App\Models\Branch;
use App\Models\StaffBranchDeployment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The therapist side of the app's Dashboard tab (therapist mobile dashboard,
 * Nov 2026): the web dashboard's personal parts.
 *
 *  - my_today and pending_deployment come straight from the web's
 *    DashboardController::liveData() (the JSON its page polls every 30 s).
 *  - transfer is the web page's "Want to work at another branch?" card:
 *    the open request (if any) and the branches a request can name, with
 *    the same rules as DashboardController::index().
 *
 * Clock in / out stays on /api/therapist/attendance (the app already reads it).
 */
class TherapistDashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $live = app(DashboardController::class)->liveData();

        if ($live->getStatusCode() !== 200) {
            return $live;
        }

        $data = $live->getData(true);
        $user = $request->user();
        $staff = $user->staff;

        $transfer = null;

        // HR Head / Finance Head work across every branch, so no transfer card (web rule).
        if ($staff && ! $user->isHead()) {
            $open = StaffBranchDeployment::with('toBranch')
                ->where('staff_id', $staff->id)
                ->whereIn('status', ['pending', 'approved', 'active'])
                ->latest()
                ->first();

            $transfer = [
                'open_request' => $open ? [
                    'id'             => $open->id,
                    'status'         => $open->status,
                    'to_branch'      => $open->toBranch->name ?? '—',
                    'start_date_fmt' => Carbon::parse($open->start_date)->format('M d, Y'),
                    'can_cancel'     => $open->status === 'pending',
                ] : null,
                'branches' => Branch::where('spa_id', $user->spa_id)
                    ->where('id', '!=', $staff->branch_id)
                    ->orderBy('name')
                    ->get(['id', 'name', 'location'])
                    ->map(fn (Branch $b) => [
                        'id'       => $b->id,
                        'name'     => $b->name,
                        'location' => $b->location,
                    ])
                    ->values(),
            ];
        }

        return response()->json([
            'server_time'        => $data['server_time'] ?? now()->toIso8601String(),
            'my_today'           => $data['my_today'] ?? null,
            'pending_deployment' => $data['pending_deployment'] ?? null,
            'transfer'           => $transfer,
        ]);
    }
}
