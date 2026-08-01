<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Package;
use App\Models\ReassignmentRequest;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReassignmentRequestController extends Controller
{
    // =====================================================
    // THERAPIST: Submit reassignment request ("I can't make this")
    // =====================================================
    public function store(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        // Only the therapist currently assigned to this booking may flag it —
        // without this check, any therapist could request reassignment on
        // someone else's appointment.
        abort_unless($booking->therapist_id === Auth::id(), 403);

        if (!in_array($booking->status, ['reserved', 'pending'])) {
            return response()->json([
                'message' => 'This appointment can no longer be reassigned.',
            ], 422);
        }

        $existing = ReassignmentRequest::where('booking_id', $booking->id)
            ->where('status', 'pending')
            ->exists();

        if ($existing) {
            return response()->json([
                'message' => 'A reassignment request for this appointment is already pending.',
            ], 422);
        }

        $reassignment = ReassignmentRequest::create([
            'booking_id'       => $booking->id,
            'requested_by'     => Auth::id(),
            'old_therapist_id' => $booking->therapist_id,
            'reason'           => $validated['reason'],
            'status'           => 'pending',
        ]);

        return response()->json([
            'message' => 'Reassignment request submitted. The front desk has been notified.',
            'data'    => $reassignment,
        ]);
    }

    // =====================================================
    // OWNER / MANAGER / RECEPTIONIST: List pending requests for this branch
    // =====================================================
    public function index()
    {
        $user     = Auth::user();
        $branchId = $user->currentBranchId() ?? $user->branch_id;

        $requests = ReassignmentRequest::with(['booking.spa', 'booking.branch', 'requestedBy', 'oldTherapist'])
            ->whereHas('booking', function ($q) use ($user, $branchId) {
                $q->where('spa_id', $user->spa_id);
                if ($branchId) {
                    $q->where('branch_id', $branchId);
                }
            })
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($r) => $this->formatRequest($r));

        return response()->json($requests);
    }

    // =====================================================
    // OWNER / MANAGER / RECEPTIONIST: Approve
    // Accepts either the system-suggested therapist or a manual override —
    // both arrive the same way, as new_therapist_id in the request body.
    // =====================================================
    public function approve(Request $request, ReassignmentRequest $reassignmentRequest)
    {
        if (!$reassignmentRequest->isPending()) {
            return response()->json(['message' => 'This request has already been reviewed.'], 422);
        }

        $booking = $reassignmentRequest->booking;

        $validated = $request->validate([
            'new_therapist_id' => [
                'required',
                Rule::exists('users', 'id')->where(function ($q) use ($booking) {
                    $q->where('branch_id', $booking->branch_id);
                }),
            ],
        ]);

        $newTherapistId = (int) $validated['new_therapist_id'];

        // Re-check availability at approval time, not just at request time —
        // the slot may have filled in the gap between submission and review.
        $conflict = Booking::query()
            ->where('id', '!=', $booking->id)
            ->where('branch_id', $booking->branch_id)
            ->where('appointment_date', $booking->appointment_date)
            ->where('therapist_id', $newTherapistId)
            ->whereIn('status', ['reserved', 'pending', 'ongoing'])
            ->where(function ($q) use ($booking) {
                $q->where('start_time', '<', $booking->end_time)
                  ->where('end_time', '>', $booking->start_time);
            })
            ->exists();

        if ($conflict) {
            return response()->json([
                'message' => 'Selected therapist is no longer available for this time slot.',
            ], 422);
        }

        $booking->update(['therapist_id' => $newTherapistId]);

        $reassignmentRequest->update([
            'new_therapist_id' => $newTherapistId,
            'status'           => 'approved',
            'reviewed_by'      => Auth::id(),
            'reviewed_at'      => now(),
        ]);

        return response()->json(['message' => 'Appointment reassigned successfully.']);
    }

    // =====================================================
    // OWNER / MANAGER / RECEPTIONIST: Reject
    // =====================================================
    public function reject(Request $request, ReassignmentRequest $reassignmentRequest)
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if (!$reassignmentRequest->isPending()) {
            return response()->json(['message' => 'This request has already been reviewed.'], 422);
        }

        $reassignmentRequest->update([
            'status'           => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'reviewed_by'      => Auth::id(),
            'reviewed_at'      => now(),
        ]);

        return response()->json(['message' => 'Reassignment request rejected.']);
    }

    // =====================================================
    // Helper
    // =====================================================
    private function formatRequest(ReassignmentRequest $r): array
    {
        $booking = $r->booking;

        $treatmentName = 'Unknown';
        if (str_starts_with($booking->treatment, 'treatment_')) {
            $id = (int) str_replace('treatment_', '', $booking->treatment);
            $treatment = Treatment::withoutGlobalScopes()->find($id);
            $treatmentName = $treatment?->name ?? 'Unknown Treatment';
        } elseif (str_starts_with($booking->treatment, 'package_')) {
            $id = (int) str_replace('package_', '', $booking->treatment);
            $package = Package::withoutGlobalScopes()->find($id);
            $treatmentName = $package ? $package->name . ' (Package)' : 'Unknown Package';
        }

        return [
            'id'                    => $r->id,
            'booking_id'            => $booking->id,
            'customer_name'         => $booking->customer_name,
            'treatment'             => $treatmentName,
            'treatment_code'        => $booking->treatment, // raw "treatment_5" / "package_2" — needed by the availability endpoint
            'appointment_date'      => $booking->appointment_date->format('F j, Y'),
            'appointment_date_raw'  => $booking->appointment_date->format('Y-m-d'),
            'start_time'            => $booking->start_time,
            'start_time_fmt'        => Carbon::parse($booking->start_time)->format('g:i A'),
            'old_therapist_id'      => $r->old_therapist_id,
            'old_therapist'         => trim(($r->oldTherapist->first_name ?? '') . ' ' . ($r->oldTherapist->last_name ?? '')),
            'reason'                => $r->reason,
            'submitted_at'          => $r->created_at->format('F j, Y g:i A'),
        ];
    }
}