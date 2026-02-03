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
        $user = Auth::user();

        // Therapists (same branch as booking)
        $therapists = User::role('therapist')
            ->whereHas('staff', function ($q) use ($user, $booking) {
                $q->where('spa_id', $user->spa_id)
                ->where('branch_id', $booking->branch_id)
                ->where('employment_status', 'active');
            })
            ->orderBy('name')
            ->get();

        // Treatments for booking branch
        $treatments = \App\Models\Treatment::where('spa_id', $user->spa_id)
            ->where('branch_id', $booking->branch_id)
            ->get();

        // Packages for booking branch
        $packages = \App\Models\Package::where('spa_id', $user->spa_id)
            ->where('branch_id', $booking->branch_id)
            ->get();

        return view('appointments.edit', compact(
            'booking',
            'therapists',
            'treatments',
            'packages'
        ));
    }

    public function update(Request $request, Booking $booking)
    {
        $user = Auth::user();
        $spaId = $user->spa_id;
        $branchId = $booking->branch_id;

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email',
            'customer_phone' => 'nullable|string',
            'customer_address' => 'nullable|string|max:255',
            'service_type' => 'required|string',
            'treatment' => 'required|string',
            'therapist_id' => 'required|exists:users,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required',
            'status' => 'required|string|in:pending,reserved,confirmed,completed,cancelled',
        ]);

        /*
        |---------------------------------------------------------
        | 1. Resolve treatment / package duration
        |---------------------------------------------------------
        */
        $durationMinutes = 0;

        if (str_starts_with($validated['treatment'], 'treatment_')) {
            $id = (int) str_replace('treatment_', '', $validated['treatment']);
            $treatment = \App\Models\Treatment::find($id);
            $durationMinutes = $treatment?->duration ?? 0;
        } elseif (str_starts_with($validated['treatment'], 'package_')) {
            $id = (int) str_replace('package_', '', $validated['treatment']);
            $package = \App\Models\Package::find($id);
            $durationMinutes = $package?->duration ?? 0;
        }

        if ($durationMinutes <= 0) {
            return back()->withErrors([
                'treatment' => 'Invalid treatment or package duration.'
            ])->withInput();
        }

        /*
        |---------------------------------------------------------
        | 2. Validate operating hours
        |---------------------------------------------------------
        */
        $dayOfWeek = Carbon::parse($validated['appointment_date'])->format('l');

        $hours = \App\Models\OperatingHours::where('branch_id', $branchId)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (!$hours || $hours->is_closed) {
            return back()->withErrors([
                'start_time' => 'The spa is closed on this day.'
            ])->withInput();
        }

        $start = Carbon::parse($validated['start_time']);
        $opening = Carbon::parse($hours->opening_time);
        $closing = Carbon::parse($hours->closing_time);

        if ($start->lt($opening) || $start->gte($closing)) {
            return back()->withErrors([
                'start_time' => "Please select a time within spa hours ({$hours->opening_time} – {$hours->closing_time})."
            ])->withInput();
        }

        /*
        |---------------------------------------------------------
        | 3. Calculate end time
        |---------------------------------------------------------
        */
        $end = (clone $start)->addMinutes($durationMinutes);

        if ($end->gt($closing)) {
            return back()->withErrors([
                'start_time' => 'This appointment would end after closing hours.'
            ])->withInput();
        }

        /*
        |---------------------------------------------------------
        | 4. Therapist availability (exclude current booking)
        |---------------------------------------------------------
        */
        $conflict = Booking::where('appointment_date', $validated['appointment_date'])
            ->where('therapist_id', $validated['therapist_id'])
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->where('id', '!=', $booking->id)
            ->whereIn('status', ['reserved', 'confirmed'])
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_time', [$start, $end])
                ->orWhereBetween('end_time', [$start, $end])
                ->orWhere(function ($q2) use ($start, $end) {
                    $q2->where('start_time', '<=', $start)
                        ->where('end_time', '>=', $end);
                });
            })
            ->exists();

        if ($conflict) {
            return back()->withErrors([
                'start_time' => 'This therapist is already booked for this time range.'
            ])->withInput();
        }

        /*
        |---------------------------------------------------------
        | 5. Update booking (end_time ALWAYS recalculated)
        |---------------------------------------------------------
        */
        $booking->update([
            ...$validated,
            'end_time' => $end->format('H:i'),
        ]);

        return redirect()
            ->route('appointments.index')
            ->with('success', 'Appointment updated successfully.');
    }

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
