<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\BookingCompletionService;
use App\Models\LeaveRequest;
use App\Models\Package;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Bookings\BookingStatusSync;
use App\Services\Bookings\ServicePriceResolver;
use App\Services\Bookings\TherapistAssigner;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function __construct(private TherapistAssigner $assigner)
    {
    }

    public function create()
    {
        $user = Auth::user();
        $branchId = session('current_branch_id') ?? $user->branch_id;
        $spaId = $user->spa_id;

        $this->syncAutomaticStatuses($spaId, $branchId);

        $therapists = $this->getBranchTherapists($spaId, $branchId);

        $treatments = Treatment::where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->get();

        $packages = Package::where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->get();

        // Shown on the page and used to disable the form.
        $bookingBlocker = $this->walkInBlocker($spaId, $branchId);

        return view('booking', compact('therapists', 'treatments', 'packages', 'bookingBlocker'));
    }

    public function availableTherapists(Request $request)
    {
        $user = Auth::user();
        $spaId = $user->spa_id;
        $branchId = session('current_branch_id') ?? $user->branch_id;

        if (!$branchId) {
            return response()->json([
                'therapists' => [],
                'recommended_id' => null,
                'message' => 'No branch selected.',
            ], 422);
        }

        if ($blocker = $this->walkInBlocker($spaId, $branchId)) {
            return response()->json([
                'therapists' => [],
                'recommended_id' => null,
                'message' => $blocker,
            ], 422);
        }

        $this->syncAutomaticStatuses($spaId, $branchId);

        $validated = $request->validate([
            'appointment_date' => ['required', 'date'],
            'start_time' => ['required'],
            'treatment' => ['required', 'string'],
            // Reassignment review passes the booking: it is left out of the busy
            // check, and online home service then lists verified therapists only.
            'booking_id' => ['nullable', 'integer'],
        ]);

        $forBooking = ! empty($validated['booking_id'])
            ? Booking::where('spa_id', $spaId)->where('branch_id', $branchId)->find($validated['booking_id'])
            : null;
        $requireVerified = $forBooking
            && TherapistAssigner::requiresVerified($forBooking->booking_source, $forBooking->service_type);

        $durationMinutes = $this->resolveDurationMinutes($validated['treatment']);

        if ($durationMinutes <= 0) {
            return response()->json([
                'therapists' => [],
                'recommended_id' => null,
                'message' => 'Invalid treatment or package duration.',
            ], 422);
        }

        $endTime = Carbon::parse($validated['start_time'])
            ->addMinutes($durationMinutes)
            ->format('H:i');

        $available = $this->assigner->available(
            $spaId,
            $branchId,
            $validated['appointment_date'],
            Carbon::parse($validated['start_time'])->format('H:i'),
            $endTime,
            $requireVerified,
            $forBooking?->id
        );

        $recommended = $this->assigner->leastBusy($available, $spaId, $branchId, $validated['appointment_date'], $forBooking?->id);

        return response()->json([
            'therapists' => $available->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->first_name . ' ' . $t->last_name,
                'verified' => $t->isIdentityVerified(),
            ])->values(),
            'recommended_id' => $recommended?->id,
            'verified_only' => $requireVerified,
        ]);
    }

    public function store(Request $request)
    {
        \Log::info('HIT store (staff)', [
            'user_id' => auth()->id(),
            'role' => auth()->user()?->getRoleNames()
        ]);

        $user = Auth::user();
        $spaId = $user->spa_id;
        $branchId = $user->currentBranchId();

        if (!$branchId) {
            return back()->with('error', 'No branch selected.');
        }

        if ($blocker = $this->walkInBlocker($spaId, $branchId)) {
            return back()->with('error', $blocker)->withInput();
        }

        $this->syncAutomaticStatuses($spaId, $branchId);

        $validated = $request->validate([
            'service_type' => 'required|in:in_branch,in_home',
            'treatment' => 'required|string',
            'therapist_id' => [
                'required',
                Rule::exists('users', 'id')->where(function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId);
                }),
            ],
            'customer_phone' => 'required|string|max:255',
            'customer_name' => 'required|string|max:255',
            'customer_address' => $request->service_type === 'in_home'
                ? 'required|string|max:255'
                : 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'appointment_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required',
        ]);

        $startTime = $validated['start_time'];

        $durationMinutes = $this->resolveDurationMinutes($validated['treatment']);

        if ($durationMinutes <= 0) {
            return back()->withErrors([
                'treatment' => 'Invalid treatment or package duration.'
            ])->withInput();
        }

        $serviceRestriction = $this->resolveServiceTypeRestriction($validated['treatment']);

        if ($validated['service_type'] === 'in_home' && $serviceRestriction === 'in_branch_only') {
            return back()->withErrors([
                'treatment' => 'This treatment is only available for in-branch appointments.'
            ])->withInput();
        }

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

        if ($start->lt($opening) || $start->gt($closing)) {
            return back()->withErrors([
                'start_time' => "Please select a time within spa operating hours: {$hours->opening_time} - {$hours->closing_time}"
            ])->withInput();
        }

        $endTime = Carbon::parse($startTime)->addMinutes($durationMinutes)->format('H:i');
        $end = Carbon::parse($endTime);

        if ($end->gt($closing)) {
            return back()->withErrors([
                'start_time' => 'This treatment would end after closing hours. Please select an earlier start time.'
            ])->withInput();
        }

        // Staff-made bookings, home service included, may use any free therapist
        // (D2 as written, decision Q14).
        $availableTherapists = $this->getAvailableTherapists(
            $spaId,
            $branchId,
            $validated['appointment_date'],
            Carbon::parse($startTime)->format('H:i'),
            $endTime
        );

        if ($availableTherapists->isEmpty()) {
            return back()->withErrors([
                'start_time' => 'No therapist is available for the selected time.'
            ])->withInput();
        }

        if (!$availableTherapists->pluck('id')->contains((int) $validated['therapist_id'])) {
            return back()->withErrors([
                'therapist_id' => 'Selected therapist is not available for this time range.'
            ])->withInput();
        }

        Booking::create([
            ...$validated,
            'spa_id'             => $spaId,
            'branch_id'          => $branchId,
            'created_by_user_id' => $user->id,
            'end_time'           => $endTime,
            'status'             => 'reserved', // always starts reserved; automated from here
        ]);

        return redirect()
            ->route('appointments.index')
            ->with('success', 'Booking reserved successfully!');
    }

    public function adminIndex()
    {
        $user = Auth::user();
        $currentBranchId = $user->currentBranchId() ?? $user->branch_id;

        $this->syncAutomaticStatuses($user->spa_id, $currentBranchId);

        $baseQuery = Booking::with('therapist', 'branch');

        if ($user->hasRole('owner')) {
            $branchId = $user->currentBranchId();

            $baseQuery->where('spa_id', $user->spa_id);

            if ($branchId) {
                $baseQuery->where('branch_id', $branchId);
            }
        } elseif ($user->hasRole('manager') || $user->hasRole('receptionist')) {
            $branchId = $user->currentBranchId() ?? $user->branch_id;

            $baseQuery->where('spa_id', $user->spa_id)
                ->where('branch_id', $branchId);
        } elseif ($user->hasRole('therapist')) {
            $baseQuery->where('spa_id', $user->spa_id)
                ->where('branch_id', $user->branch_id)
                ->where('therapist_id', $user->id);
        }

        $today = now()->toDateString();

        $todayBase = (clone $baseQuery)
            ->whereDate('appointment_date', $today)
            ->whereIn('status', ['reserved', 'pending', 'ongoing']);

        $todayPending = (clone $todayBase)
            ->where('status', 'pending')
            ->orderBy('start_time', 'asc')
            ->get();

        $todayPending->transform(
            fn ($booking) => $this->decorateBooking($booking)
        );

        $todayAppointments = (clone $todayBase)
            ->orderBy('start_time', 'asc')
            ->get();

        $todayAppointments->transform(
            fn ($booking) => $this->decorateBooking($booking)
        );

        $upcomingBase = (clone $baseQuery)
            ->whereDate('appointment_date', '>', $today)
            ->whereIn('status', ['reserved', 'pending']);

        $upcomingAppointments = (clone $upcomingBase)
            ->with('latestReassignmentRequest')
            ->orderBy('appointment_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->limit(10)
            ->get();

        $upcomingAppointments->transform(function ($booking) {
            $booking = $this->decorateBooking($booking);
            $booking->has_pending_reassignment = $booking->latestReassignmentRequest?->isPending() ?? false;
            return $booking;
        });

        // Finished bookings only. A past booking nobody closed is still open and
        // is listed under "Needs checkout" instead (booking lifecycle fix, Phase 2).
        $historyAppointments = (clone $baseQuery)
            ->whereIn('status', Booking::FINISHED_STATUSES)
            ->orderBy('appointment_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate(5, ['*'], 'history_page');

        $historyAppointments->getCollection()->transform(
            fn ($booking) => $this->decorateBooking($booking)
        );

        $summary = [
            'today_total' => (clone $todayBase)->count(),
            'pending_today' => (clone $todayBase)->where('status', 'pending')->count(),
            'upcoming_total' => (clone $upcomingBase)->count(),
            // Paid toward today's appointments, any status: a checkout no longer makes
            // the money vanish from this card (lifecycle Phase 4, Q5-A).
            'collected_today' => (clone $baseQuery)->whereDate('appointment_date', $today)->sum('amount_paid'),
        ];

        // Edit modal: everyone who can be assigned (therapists and staff marked
        // "Performs treatments"), with their verified status for home service.
        $therapists = User::treatmentProviders()
            ->whereHas('staff', function ($q) use ($user, $currentBranchId) {
                $q->where('spa_id', $user->spa_id)->where('employment_status', 'active');

                if ($currentBranchId) {
                    $q->where('branch_id', $currentBranchId);
                }
            })
            ->orderBy('first_name')
            ->get();

        // Paid online bookings that nobody could be assigned to (decision Q17).
        // The receptionist/manager picks a therapist from the Edit button.
        $unassignedBookings = collect();
        $unassignedTotal = 0;

        if ($user->hasBranchPermission('edit appointments')) {
            $unassignedQuery = Booking::query()
                ->where('spa_id', $user->spa_id)
                ->when($currentBranchId, fn ($q) => $q->where('branch_id', $currentBranchId))
                ->whereNull('therapist_id')
                ->whereIn('status', ['reserved', 'pending'])
                ->whereDate('appointment_date', '>=', $today)
                ->notEnded();   // ended ones are under Needs checkout (Phase 2)

            // The list stays capped at 20, but the count is the true total, so it
            // matches the sidebar badge (sidebar & RBAC rework Phase 4, answer P13).
            $unassignedTotal = (clone $unassignedQuery)->count();

            $unassignedBookings = $unassignedQuery
                ->orderBy('appointment_date')
                ->orderBy('start_time')
                ->limit(20)
                ->get()
                ->map(fn ($booking) => $this->decorateBooking($booking));
        }

        // Needs checkout (booking lifecycle fix, Phase 2, D3/Q1-A): every booking
        // still open after its end time, any date, oldest first. Staff only.
        $needsCheckout = collect();
        $assignableTherapists = collect();

        if ($user->hasBranchPermission('edit appointments') && $currentBranchId) {
            $needsCheckout = Booking::query()
                ->where('spa_id', $user->spa_id)
                ->where('branch_id', $currentBranchId)
                ->overdue()
                ->with('therapist', 'arrivalConfirmedBy')
                ->orderBy('appointment_date')
                ->orderBy('start_time')
                ->limit(50)
                ->get()
                ->map(fn ($booking) => $this->decorateBooking($booking));

            if ($needsCheckout->contains(fn ($b) => $b->therapist_id === null)) {
                $assignableTherapists = $this->getBranchTherapists((int) $user->spa_id, (int) $currentBranchId);
            }
        }

        return view('appointments', compact(
            'needsCheckout',
            'assignableTherapists',
            'todayAppointments',
            'todayPending',
            'upcomingAppointments',
            'historyAppointments',
            'summary',
            'therapists',
            'unassignedBookings',
            'unassignedTotal'
        ));
    }

    public function edit(Booking $booking)
    {
        $this->ensureCurrentBranchBooking($booking);

        $user = Auth::user();

        $this->syncAutomaticStatuses($user->spa_id, $booking->branch_id);

        $therapists = User::treatmentProviders()
            ->whereHas('staff', function ($q) use ($user, $booking) {
                $q->where('spa_id', $user->spa_id)
                    ->where('branch_id', $booking->branch_id)
                    ->where('employment_status', 'active');
            })
            ->orderBy('first_name')
            ->get();

        $treatments = Treatment::where('spa_id', $user->spa_id)
            ->where('branch_id', $booking->branch_id)
            ->get();

        $packages = Package::where('spa_id', $user->spa_id)
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
        $this->ensureCurrentBranchBooking($booking);

        $user = Auth::user();
        $spaId = $user->spa_id;
        $branchId = $booking->branch_id;

        $this->syncAutomaticStatuses($spaId, $branchId);
        $booking->refresh();

        if (in_array($booking->status, Booking::FINISHED_STATUSES, true)) {
            return redirect()
                ->route('appointments.index')
                ->with('error', 'This appointment is already ' . strtolower(Booking::statusLabel($booking->status)) . ' and can no longer be edited.');
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => ['nullable', 'string', 'regex:/^09\d{9}$/'],
            'customer_address' => 'nullable|string|max:255',
            'service_type' => 'required|string|in:in_branch,in_home',
            'treatment' => 'required|string',
            'therapist_id' => 'required|exists:users,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required',
        ]);

        $durationMinutes = $this->resolveDurationMinutes($validated['treatment']);

        if ($durationMinutes <= 0) {
            return back()->withErrors([
                'treatment' => 'Invalid treatment or package duration.'
            ])->withInput();
        }

        $serviceRestriction = $this->resolveServiceTypeRestriction($validated['treatment']);

        if ($validated['service_type'] === 'in_home' && $serviceRestriction === 'in_branch_only') {
            return back()->withErrors([
                'treatment' => 'This treatment is only available for in-branch appointments.'
            ])->withInput();
        }

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

        $end = (clone $start)->addMinutes($durationMinutes);

        if ($end->gt($closing)) {
            return back()->withErrors([
                'start_time' => 'This appointment would end after closing hours.'
            ])->withInput();
        }

        // Shared rule: in the pool, free at that time, and identity-verified
        // when this is an online home-service booking (D2).
        $problem = $this->assigner->rejectionFor(
            (int) $validated['therapist_id'],
            $booking,
            $validated['appointment_date'],
            $start->format('H:i'),
            $end->format('H:i'),
            $validated['service_type']
        );

        if ($problem) {
            return back()->withErrors(['therapist_id' => $problem])->withInput();
        }

        // Capture the pre-update schedule so we only notify the customer
        // when the actual date or time changed — not on every field edit
        // (e.g. re-assigning a therapist with the same slot).
        $oldDate      = $booking->appointment_date->format('Y-m-d');
        $oldStartTime = $booking->start_time;

        $booking->update([
            ...$validated,
            'end_time' => $end->format('H:i'),
        ]);

        $scheduleChanged = $oldDate !== $validated['appointment_date']
            || $oldStartTime !== $validated['start_time'];

        if ($scheduleChanged && $booking->customer_email) {
            try {
                \Illuminate\Support\Facades\Mail::to($booking->customer_email)
                    ->send(new \App\Mail\AppointmentUpdated($booking->fresh(), $oldDate, $oldStartTime));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send appointment update email: ' . $e->getMessage());
            }
        }

        return redirect()
            ->route('appointments.index')
            ->with('success', 'Appointment updated successfully.');
    }

    public function destroy($id)
    {
        $booking = Booking::findOrFail($id);
        $this->ensureCurrentBranchBooking($booking);

        if (in_array($booking->status, Booking::FINISHED_STATUSES, true)) {
            return redirect()->back()->with('error', 'This appointment is already ' . strtolower(Booking::statusLabel($booking->status)) . '.');
        }

        $booking->update(['status' => 'cancelled']);

        return redirect()->back()->with('success', 'Appointment cancelled successfully!');
    }

    public function history()
    {
        // Only this user's own bookings (F3: this used to sync every spa).
        app(BookingStatusSync::class)->sync(
            fn ($q) => $q->where('customer_user_id', Auth::id())
        );

        $bookings = Booking::where('customer_user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($bookings);
    }

    /**
     * Start, cancel, or record a no-show. Completing is a separate action
     * (checkout below), because only checkout collects the full balance and
     * deducts stock (booking lifecycle fix, Phase 1, D1).
     */
    public function updateStatus(Request $request, Booking $booking)
    {
        $this->ensureCurrentBranchBooking($booking);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['ongoing', 'cancelled', Booking::NO_SHOW])],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (in_array($booking->status, Booking::FINISHED_STATUSES, true)) {
            return back()->withErrors([
                'status' => 'This appointment is already ' . strtolower(Booking::statusLabel($booking->status)) . '.',
            ]);
        }

        // A no-show only after the start time, on a booking that's still open
        // (UI rework Phase 3, decision 1A).
        if ($validated['status'] === Booking::NO_SHOW && ! $booking->canBeMarkedNoShow()) {
            return back()->withErrors([
                'status' => 'A no-show can only be recorded after the appointment has started.',
            ])->withInput();
        }

        if ($validated['status'] === 'ongoing') {
            if ($booking->status === 'ongoing') {
                return back()->with('error', 'This appointment has already started.');
            }

            $appointmentDate = Carbon::parse($booking->appointment_date)->startOfDay();

            if ($appointmentDate->gt(now()->startOfDay())) {
                return back()->withErrors([
                    'status' => 'Cannot start an appointment that is scheduled for a future date.'
                ])->withInput();
            }
        }

        // Cancelled and no-show end the booking without the service: payments
        // stay exactly as they are (decision 2A — same handling as a cancellation).
        if (in_array($validated['status'], ['cancelled', Booking::NO_SHOW], true)) {
            $booking->update(['status' => $validated['status']]);

            return back()->with('success', $validated['status'] === Booking::NO_SHOW
                ? 'Marked as a no-show.'
                : 'Appointment cancelled.');
        }

        // Start: collecting money now is optional (Q4-A); checkout collects the rest.
        $payment = $this->paymentAfter($booking, (float) ($validated['amount_paid'] ?? 0));

        if ($payment['error']) {
            return back()->withErrors(['amount_paid' => $payment['error']])->withInput();
        }

        $booking->update(['status' => 'ongoing'] + $payment['fields']);

        return back()->with('success', 'Appointment started.');
    }

    /**
     * Staff checkout: collect the full remaining balance, deduct recipe stock,
     * and complete the booking (booking lifecycle fix, Phase 1).
     *
     *   D1    the only way a booking becomes completed
     *   Q2-A  the balance must be fully paid
     *   Q3-A  allowed straight from pending (start + finish in one step)
     *   D7    never blocked by stock; shortfalls are recorded and reported
     */
    public function checkout(Request $request, Booking $booking)
    {
        $this->ensureCurrentBranchBooking($booking);

        $validated = $request->validate([
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (in_array($booking->status, Booking::FINISHED_STATUSES, true)) {
            return back()->with('error', 'This appointment is already ' . strtolower(Booking::statusLabel($booking->status)) . '.');
        }

        if (! $booking->canBeCheckedOut()) {
            return back()->with('error', 'An appointment can only be checked out once it has started.');
        }

        // Q5-A / D4: a therapist must be credited, and an online home visit
        // needs its arrival confirmed first. The Needs checkout list shows the step.
        if ($blocker = $booking->checkoutBlocker()) {
            return back()->with('error', $blocker);
        }

        $payment = $this->paymentAfter($booking, (float) ($validated['amount_paid'] ?? 0));

        if ($payment['error']) {
            return back()->withErrors(['amount_paid' => $payment['error']])->withInput();
        }

        if ($payment['fields']['balance_amount'] > 0) {
            return back()->withErrors([
                'amount_paid' => 'Collect the full remaining balance of ₱'
                    . number_format($payment['remaining'], 2) . ' to check out.',
            ])->withInput();
        }

        $completed = DB::transaction(function () use ($booking, $payment) {
            // Nothing is owed after checkout, so a free (₱0) service is "paid" too.
            $booking->update(['payment_status' => 'paid'] + $payment['fields']);

            return app(BookingCompletionService::class)->complete($booking, Auth::user());
        });

        $consumption = $completed->consumption()->with('shortItems')->first();

        if ($consumption?->has_shortfall) {
            $missing = $consumption->shortItems
                ->map(fn ($item) => $item->product_name . ' (' . rtrim(rtrim(number_format((float) $item->short_quantity, 3, '.', ''), '0'), '.') . ' ' . $item->unit . ')')
                ->all();

            $detail = $missing
                ? 'Stock was short for: ' . implode(', ', $missing) . '.'
                : 'Some of its stock could not be deducted.';

            // Checkout succeeded, so it's a success toast; the shortage stays
            // listed on Inventory → Products for whoever restocks (Q8-A).
            return back()->with('success', 'Checked out. ' . $detail . ' It is listed on the Inventory Products page.');
        }

        return back()->with('success', 'Checked out. The appointment is completed.');
    }

    /**
     * Record who did an overdue session that had no therapist, so checkout can
     * credit them (booking lifecycle fix, Phase 2, Q5-A). The Edit modal can't
     * do this: it only accepts today or later. No availability check — the
     * session already happened — and no customer email (BookingNotifier skips
     * ended bookings).
     */
    public function assignTherapist(Request $request, Booking $booking)
    {
        $this->ensureCurrentBranchBooking($booking);

        $validated = $request->validate([
            'therapist_id' => ['required', 'integer'],
        ]);

        if (! $booking->isOverdue()) {
            return back()->with('error', 'Use Edit to assign a therapist to an appointment that has not ended yet.');
        }

        if ($booking->therapist_id !== null) {
            return back()->with('error', 'This appointment already has a therapist.');
        }

        $eligible = $this->getBranchTherapists((int) $booking->spa_id, (int) $booking->branch_id)
            ->pluck('id')->map(fn ($id) => (int) $id);

        if (! $eligible->contains((int) $validated['therapist_id'])) {
            return back()->withErrors(['therapist_id' => 'Pick a therapist who works at this branch.']);
        }

        $booking->update(['therapist_id' => (int) $validated['therapist_id']]);

        return back()->with('success', 'Therapist recorded. You can check this appointment out now.');
    }

    /**
     * New payment fields after collecting $additional now. Shared by Start and
     * Checkout so the arithmetic lives in one place.
     *
     * @return array{error: ?string, remaining: float, fields: array}
     */
    private function paymentAfter(Booking $booking, float $additional): array
    {
        $serviceTotal = (float) ($booking->total_amount > 0
            ? $booking->total_amount
            : $this->resolveServicePrice($booking->treatment));

        $currentPaid = (float) ($booking->amount_paid ?? 0);
        $remaining = round(max($serviceTotal - $currentPaid, 0), 2);

        if (round($additional, 2) > $remaining) {
            return [
                'error' => 'Entered amount is greater than the remaining balance.',
                'remaining' => $remaining,
                'fields' => [],
            ];
        }

        $newPaid = $currentPaid + $additional;
        $newBalance = round(max($serviceTotal - $newPaid, 0), 2);

        if ($serviceTotal > 0 && $newBalance <= 0) {
            $paymentStatus = 'paid';
        } elseif ($newPaid > 0) {
            $paymentStatus = 'partially_paid';
        } else {
            $paymentStatus = 'unpaid';
        }

        return [
            'error' => null,
            'remaining' => $remaining,
            'fields' => [
                'total_amount' => $serviceTotal,
                'amount_paid' => $newPaid,
                'balance_amount' => $newBalance,
                'payment_status' => $paymentStatus,
            ],
        ];
    }

    /**
     * Staff may act only on bookings of the branch they're working in. The
     * permission middleware checks the permission, not the booking, and
     * Booking has no spa scope, so without this any spa's booking id could be
     * processed (found during the booking lifecycle fix, Phase 1).
     */
    private function ensureCurrentBranchBooking(Booking $booking): void
    {
        $user = Auth::user();

        abort_unless(
            $user
            && (int) $booking->spa_id === (int) $user->spa_id
            && (int) $booking->branch_id === (int) $user->currentBranchId(),
            404
        );
    }

    /**
     * Why this branch cannot take a walk-in booking, or null when it can.
     * A branch needs an active therapist and at least one service first.
     */
    private function walkInBlocker(?int $spaId, ?int $branchId): ?string
    {
        $branch = \App\Models\Branch::query()
            ->where('spa_id', $spaId)
            ->find($branchId);

        if (!$branch) {
            return 'No branch selected.';
        }

        return $branch->bookingReadinessMessage();
    }

    private function resolveDurationMinutes(string $selection): int
    {
        if (str_starts_with($selection, 'treatment_')) {
            $id = (int) str_replace('treatment_', '', $selection);
            $treatment = Treatment::withoutGlobalScopes()->find($id);
            return $treatment?->duration ?? 0;
        }

        if (str_starts_with($selection, 'package_')) {
            $id = (int) str_replace('package_', '', $selection);
            $package = Package::withoutGlobalScopes()->find($id);
            return $package?->duration ?? $package?->total_duration ?? 0;
        }

        return 0;
    }

    private function resolveServiceTypeRestriction(string $selection): ?string
    {
        if (str_starts_with($selection, 'treatment_')) {
            $id = (int) str_replace('treatment_', '', $selection);
            $treatment = Treatment::withoutGlobalScopes()->find($id);

            return $treatment?->service_type === 'in_branch_only'
                ? 'in_branch_only'
                : null;
        }

        // Packages have no service_type column yet, so no restriction can be
        // enforced here. Add one if packages need the same rule as treatments.
        return null;
    }

    /** Everyone who can be assigned at this branch (shared rule, TherapistAssigner::pool). */
    private function getBranchTherapists(int $spaId, int $branchId): Collection
    {
        return $this->assigner->pool($spaId, $branchId);
    }

    /** Free at this time (shared rule). Not verified-only: staff-made bookings are exempt (Q14). */
    private function getAvailableTherapists(
        int $spaId,
        int $branchId,
        string $appointmentDate,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId = null
    ): Collection {
        return $this->assigner->available($spaId, $branchId, $appointmentDate, $startTime, $endTime, false, $excludeBookingId);
    }

    /**
     * Moves this spa/branch's open bookings along the clock before a page reads
     * them. Never completes a booking and never sets `ongoing` (booking
     * lifecycle fix, Phase 1, D1/D2/Q1-B); see BookingStatusSync.
     */
    private function syncAutomaticStatuses(?int $spaId, ?int $branchId = null): void
    {
        app(BookingStatusSync::class)->syncFor($spaId, $branchId);
    }

    private function decorateBooking(Booking $booking): Booking
    {
        $resolvedTotal = (float) ($booking->total_amount > 0
            ? $booking->total_amount
            : $this->resolveServicePrice($booking->treatment));

        $resolvedPaid = (float) ($booking->amount_paid ?? 0);

        $booking->resolved_total_amount = $resolvedTotal;
        $booking->resolved_amount_paid = $resolvedPaid;
        $booking->resolved_balance_amount = max($resolvedTotal - $resolvedPaid, 0);

        // Why Check out isn't offered yet (Phase 2: therapist / arrival), or null.
        $booking->checkout_block = $booking->checkoutBlocker();

        return $booking;
    }

    private function resolveServicePrice(string $selection): float
    {
        // Shared with payroll (commission base fallback). The float cast keeps this
        // controller's existing behavior unchanged.
        return (float) app(ServicePriceResolver::class)->listPrice($selection);
    }

    public function liveData()
    {
        $user            = Auth::user();
        $currentBranchId = $user->currentBranchId() ?? $user->branch_id;

        $this->syncAutomaticStatuses($user->spa_id, $currentBranchId);

        $baseQuery = Booking::with(['therapist', 'branch']);

        if ($user->hasRole('owner')) {
            $branchId = $user->currentBranchId();
            $baseQuery->where('spa_id', $user->spa_id);
            if ($branchId) {
                $baseQuery->where('branch_id', $branchId);
            }
        } elseif ($user->hasRole('manager') || $user->hasRole('receptionist')) {
            $branchId = $user->currentBranchId() ?? $user->branch_id;
            $baseQuery->where('spa_id', $user->spa_id)
                      ->where('branch_id', $branchId);
        } elseif ($user->hasRole('therapist')) {
            $baseQuery->where('spa_id', $user->spa_id)
                      ->where('branch_id', $user->branch_id)
                      ->where('therapist_id', $user->id);
        }

        $today = now()->toDateString();

        $todayBase = (clone $baseQuery)
            ->whereDate('appointment_date', $today)
            ->whereIn('status', ['reserved', 'pending', 'ongoing']);

        $upcomingBase = (clone $baseQuery)
            ->whereDate('appointment_date', '>', $today)
            ->whereIn('status', ['reserved', 'pending']);

        $pendingAppointments  = (clone $todayBase)
            ->where('status', 'pending')
            ->orderBy('start_time')
            ->get()
            ->map(fn ($b) => $this->formatForLive($b));

        $todayAppointments    = (clone $todayBase)
            ->orderBy('start_time')
            ->get()
            ->map(fn ($b) => $this->formatForLive($b));

        $upcomingAppointments = (clone $upcomingBase)
            ->with('latestReassignmentRequest')
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->limit(25)
            ->get()
            ->map(function ($b) {
                $b->has_pending_reassignment = $b->latestReassignmentRequest?->isPending() ?? false;
                return $this->formatForLive($b);
            });

        return response()->json([
            'summary' => [
                'today_total'     => (clone $todayBase)->count(),
                'pending_today'   => (clone $todayBase)->where('status', 'pending')->count(),
                'upcoming_total'  => (clone $upcomingBase)->count(),
                // Paid toward today's appointments, any status: a checkout no longer makes
                // the money vanish from this card (lifecycle Phase 4, Q5-A).
                'collected_today' => (clone $baseQuery)->whereDate('appointment_date', $today)->sum('amount_paid'),
            ],
            'pending_appointments'  => $pendingAppointments,
            'today_appointments'    => $todayAppointments,
            'upcoming_appointments' => $upcomingAppointments,
            'server_time'           => now()->toIso8601String(),
        ]);
    }

    private function formatForLive(Booking $b): array
    {
        $b = $this->decorateBooking($b);

        // Resolve a human-readable treatment name in case the model
        // does not expose a treatment_label accessor.
        $treatmentLabel = $b->treatment_label
            ?? (function () use ($b) {
                if (str_starts_with((string) $b->treatment, 'treatment_')) {
                    $id = (int) str_replace('treatment_', '', $b->treatment);
                    $t  = Treatment::withoutGlobalScopes()->find($id);
                    return $t ? $t->name : 'Unknown Treatment';
                }
                if (str_starts_with((string) $b->treatment, 'package_')) {
                    $id = (int) str_replace('package_', '', $b->treatment);
                    $p  = Package::withoutGlobalScopes()->find($id);
                    return $p ? $p->name . ' (Package)' : 'Unknown Package';
                }
                return $b->treatment ?? '—';
            })();

        $serviceLabel = $b->service_type_label
            ?? match ($b->service_type) {
                'in_branch' => 'In Branch',
                'in_home'   => 'In Home',
                default     => ucfirst($b->service_type ?? ''),
            };

        return [
            'id'                      => $b->id,
            'customer_name'           => $b->customer_name           ?? 'Walk-in Customer',
            'customer_email'          => $b->customer_email          ?? '',
            'customer_phone'          => $b->customer_phone          ?? '',
            'customer_address'        => $b->customer_address        ?? '',
            'treatment'               => $b->treatment               ?? '',   // raw: "treatment_5"
            'treatment_label'         => $treatmentLabel,
            'service_type'            => $b->service_type            ?? '',
            'service_type_label'      => $serviceLabel,
            'booking_source'          => $b->booking_source          ?? '',
            'start_time'              => $b->start_time,                      // raw HH:MM:SS
            'end_time'                => $b->end_time,
            'start_time_fmt'          => $b->start_time
                                            ? Carbon::parse($b->start_time)->format('h:i A')
                                            : '—',
            'end_time_fmt'            => $b->end_time
                                            ? Carbon::parse($b->end_time)->format('h:i A')
                                            : '—',
            'appointment_date'        => $b->appointment_date?->format('M d, Y'),
            'appointment_date_raw'    => $b->appointment_date?->format('Y-m-d'),
            'therapist_name'          => $b->therapist
                                            ? trim($b->therapist->first_name . ' ' . $b->therapist->last_name)
                                            : 'Not Assigned',
            'therapist_id'            => $b->therapist_id,
            'branch_id'               => $b->branch_id,
            'status'                  => $b->status,
            'resolved_total_amount'   => $b->resolved_total_amount   ?? 0,
            'resolved_amount_paid'    => $b->resolved_amount_paid    ?? 0,
            'resolved_balance_amount' => $b->resolved_balance_amount ?? 0,
            'checkout_block'          => $b->checkout_block,
            'has_pending_reassignment' => $b->has_pending_reassignment ?? false,
        ];
    }
}
