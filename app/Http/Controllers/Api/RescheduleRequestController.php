<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RescheduleRequest;
use App\Models\Booking;
use App\Models\OperatingHours;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class RescheduleRequestController extends Controller
{
    // GET /api/reschedule-requests/booking/{bookingId}
    public function show($bookingId)
    {
        try {
            $booking = Booking::findOrFail($bookingId);

            // Check if booking belongs to the customer
            if ($booking->customer_user_id !== Auth::id()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // FIX: Only return pending or approved requests, EXCLUDE rejected ones
            $rescheduleRequest = RescheduleRequest::where('booking_id', $bookingId)
                ->where('requested_by', Auth::id())
                ->whereIn('status', ['pending', 'approved']) // ← KEY CHANGE: exclude rejected
                ->latest()
                ->first();

            if (!$rescheduleRequest) {
                return response()->json([
                    'success' => true,
                    'reschedule_request' => null
                ]);
            }

            return response()->json([
                'success' => true,
                'reschedule_request' => [
                    'id' => $rescheduleRequest->id,
                    'booking_id' => $rescheduleRequest->booking_id,
                    'new_date' => Carbon::parse($rescheduleRequest->requested_date)->toDateString(),
                    'new_time' => Carbon::parse($rescheduleRequest->requested_time)->format('H:i'),
                    'reason' => $rescheduleRequest->reason,
                    'status' => $rescheduleRequest->status,
                    'created_at' => $rescheduleRequest->created_at->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Reschedule show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch reschedule request'
            ], 500);
        }
    }

    // POST /api/reschedule-requests
    public function store(Request $request)
    {
        try {
            \Log::info('Reschedule request received', $request->all());

            $request->validate([
                'booking_id' => 'required|exists:bookings,id',
                'new_date'   => 'required|date|after:today',
                'new_time'   => 'required|string',
                'reason'     => 'required|string|min:10',
            ]);

            $booking = Booking::findOrFail($request->booking_id);

            // Check if booking belongs to the customer
            if ($booking->customer_user_id !== Auth::id()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            if (!in_array($booking->status, ['reserved', 'pending', 'confirmed'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'This booking can no longer be rescheduled.'
                ], 422);
            }

            // Check if there's already a pending request
            $existing = RescheduleRequest::where('booking_id', $booking->id)
                ->where('status', 'pending')
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'A reschedule request is already pending for this booking.'
                ], 422);
            }

            // Validate operating hours for the requested date
            $dayOfWeek = Carbon::parse($request->new_date)->format('l');
            $hours = OperatingHours::where('branch_id', $booking->branch_id)
                ->where('day_of_week', $dayOfWeek)
                ->first();

            if (!$hours || $hours->is_closed) {
                return response()->json([
                    'success' => false,
                    'message' => 'The spa is closed on the selected day. Please choose another date.'
                ], 422);
            }

            // Validate time is within operating hours
            $requestedTime = Carbon::parse($request->new_time);
            $openTime      = Carbon::parse($hours->opening_time);
            $closeTime     = Carbon::parse($hours->closing_time);

            if ($requestedTime->lt($openTime) || $requestedTime->gte($closeTime)) {
                return response()->json([
                    'success' => false,
                    'message' => "Please select a time within operating hours: {$hours->opening_time} - {$hours->closing_time}"
                ], 422);
            }

            $rescheduleRequest = RescheduleRequest::create([
                'booking_id'      => $request->booking_id,
                'requested_by'    => Auth::id(),
                'requested_date'  => $request->new_date,
                'requested_time'  => $request->new_time,
                'reason'          => $request->reason,
                'status'          => 'pending',
            ]);

            \Log::info('Reschedule request created', ['id' => $rescheduleRequest->id]);

            return response()->json([
                'success' => true,
                'message' => 'Reschedule request submitted successfully',
                'reschedule_request' => [
                    'id'         => $rescheduleRequest->id,
                    'booking_id' => $rescheduleRequest->booking_id,
                    'new_date'   => Carbon::parse($rescheduleRequest->requested_date)->toDateString(),
                    'new_time'   => Carbon::parse($rescheduleRequest->requested_time)->format('H:i'),
                    'reason'     => $rescheduleRequest->reason,
                    'status'     => $rescheduleRequest->status,
                    'created_at' => $rescheduleRequest->created_at->toISOString(),
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Reschedule store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit reschedule request: ' . $e->getMessage()
            ], 500);
        }
    }
}
