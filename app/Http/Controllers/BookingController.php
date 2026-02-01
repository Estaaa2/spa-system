<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Staff;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function create()
    {
        $user = Auth::user();
        $branchId = $user->branch_id;
        $spaId = $user->spa_id;

        // Therapists
        $therapists = User::role('therapist')
            ->whereHas('staff', function($q) use ($spaId, $branchId) {
                $q->where('spa_id', $spaId)
                ->where('branch_id', $branchId)
                ->where('employment_status', 'active');
            })
            ->orderBy('name')
            ->get();

        // Treatments & Packages for this spa & branch
        $treatments = \App\Models\Treatment::where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->get();

        $packages = \App\Models\Package::where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->get();

        return view('booking', compact('therapists', 'treatments', 'packages'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        // Determine spa_id and branch_id
        $spaId = $user->spa_id; // same as before
        $branchId = $user->currentBranchId();

        if (!$branchId) {
            return back()->with('error', 'No branch selected.');
        }

        $validated = $request->validate([
            'service_type' => 'required',
            'treatment'    => 'required',
            'therapist_id' => 'required|exists:users,id',
            'customer_phone'   => 'required',
            'customer_name'    => 'required',
            'customer_address' => $request->service_type === 'in_home' ? 'required|string|max:255' : 'nullable|string|max:255',
            'customer_email'   => 'required|email',
            'appointment_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required',
            'status' => 'required|string|in:pending,reserved,confirmed,completed,cancelled',
        ]);

        // Calculate end_time.
        $startTime = $validated['start_time'];
        $durationMinutes = 0;

        if (str_starts_with($validated['treatment'], 'treatment_')) {
            $id = intval(str_replace('treatment_', '', $validated['treatment']));
            $treatment = \App\Models\Treatment::find($id);
            $durationMinutes = $treatment ? $treatment->duration : 0;
        } elseif (str_starts_with($validated['treatment'], 'package_')) {
            $id = intval(str_replace('package_', '', $validated['treatment']));
            $package = \App\Models\Package::find($id);
            $durationMinutes = $package ? $package->duration : 0;
        }

        // Check spa operating hours for the selected date.
        $dayOfWeek = Carbon::parse($validated['appointment_date'])->format('l'); // Monday, Tuesday, etc.
        $hours = \App\Models\OperatingHours::where('branch_id', $branchId)
                    ->where('day_of_week', $dayOfWeek)
                    ->first();

        if (!$hours || $hours->is_closed) {
            return back()->withErrors(['start_time' => 'The spa is closed on this day.'])->withInput();
        }

        $start = Carbon::parse($validated['start_time']);
        $opening = Carbon::parse($hours->opening_time);
        $closing = Carbon::parse($hours->closing_time);

        // Check if start time is within operating hours.
        if ($start->lt($opening) || $start->gt($closing)) {
            return back()->withErrors(['start_time' => "Please select a time within spa operating hours: {$hours->opening_time} - {$hours->closing_time}"])->withInput();
        }
        
        // Calculate end time.
        $endTime = Carbon::parse($startTime)->addMinutes($durationMinutes)->format('H:i');

        $end = Carbon::parse($endTime);
        if ($end->gt($closing)) {
            return back()->withErrors(['start_time' => "This treatment would end after closing hours. Please select an earlier start time."])->withInput();
        }

        // Check therapist availability within the same spa & branch
        $exists = Booking::where('appointment_date', $validated['appointment_date'])
            ->where('therapist_id', $validated['therapist_id'])
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->whereIn('status', ['reserved', 'confirmed'])
            ->where(function($q) use ($startTime, $endTime) {
                $q->whereBetween('start_time', [$startTime, $endTime])
                ->orWhereBetween('end_time', [$startTime, $endTime])
                ->orWhere(function($q2) use ($startTime, $endTime) {
                    $q2->where('start_time', '<=', $startTime)
                        ->where('end_time', '>=', $endTime);
                });
            })
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'start_time' => 'This therapist is already booked for this time range.'
            ])->withInput();
        }

        Booking::create([
            ...$validated,
            'spa_id' => $spaId,
            'branch_id' => $branchId,
            'created_by_user_id' => $user->id,
            'end_time' => $endTime,
        ]);

        return redirect()
            ->route('booking')
            ->with('success', 'Booking reserved successfully!');
    }

    // ADMIN VIEW WITH PAGINATION
    public function adminIndex()
    {
        $user = Auth::user();

        $query = Booking::with('therapist', 'branch');

        if ($user->hasRole('owner')) {
            $branchId = session('current_branch_id') ?? null;
            $query->where('spa_id', $user->spa_id);
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
        } elseif ($user->hasRole('manager')) {
            $query->where('spa_id', $user->spa_id)
                ->where('branch_id', $user->branch_id);
        } elseif ($user->hasRole('therapist')) {
            $query->where('spa_id', $user->spa_id)
                ->where('branch_id', $user->branch_id)
                ->where('therapist_id', $user->id);
        }

        $bookings = $query->orderBy('appointment_date', 'asc')
                        ->orderBy('start_time', 'asc')
                        ->paginate(10);

        $therapists = User::role('therapist')
                        ->whereHas('staff', function($q) use ($user) {
                            $q->where('spa_id', $user->spa_id);
                        })
                        ->orderBy('name')
                        ->get();

        return view('appointments', compact('bookings', 'therapists'));
    }

    public function edit(Booking $booking)
    {
        // Get therapists from Staff model - KEEP THIS ONE
        $user = Auth::user();

        $therapistsQuery = User::role('therapist')->where('status', 'active');

        if ($user->hasRole('owner')) {
            // Owner: all therapists in their spa
            $therapistsQuery->where('spa_id', $user->spa_id);
        } else {
            // Manager/Receptionist/Therapist: only their branch
            $therapistsQuery->where('branch_id', $user->branch_id)
                            ->where('spa_id', $user->spa_id);
        }

        $therapists = $therapistsQuery->orderBy('name')->get();

        return view('appointments.edit', compact('booking', 'therapists'));
    }

    public function update(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email',
            'service_type' => 'required|string',
            'treatment' => 'required|string',
            'therapist_id' => 'required|exists:users,id',
            'appointment_date' => 'required|date',
            'start_time' => 'required',
            'status' => 'required|string|in:pending,reserved,confirmed,completed,cancelled',
        ]);

        $booking->update($validated);

        return redirect()->route('appointments.index')
            ->with('success', 'Appointment updated successfully.');
    }

    // REMOVE THIS DUPLICATE EDIT METHOD - DELETE THESE LINES:
    /*
    public function edit(Booking $booking)
    {
        // Get therapists for dropdown
        $therapists = User::role('therapist')->get();

        return view('appointments.edit', compact('booking', 'therapists'));
    }
    */

    // Keep your other methods (destroy, history, reserve, etc.) here
    public function destroy($id)
    {
        $booking = Booking::findOrFail($id);
        $booking->delete();

        return redirect()->back()->with('success', 'Appointment deleted successfully!');
    }

    public function history()
    {
        $bookings = Booking::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($bookings);
    }

    public function reserve(Booking $booking)
    {
        if ($booking->status !== 'pending') {
            return back()->withErrors('Booking already processed.');
        }

        $booking->update([
            'status' => 'reserved',
            'therapist' => request('therapist') ?? $booking->therapist,
        ]);

        return back()->with('success', 'Booking reserved successfully.');
    }
}
