<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\BookingConsumptionService;
use App\Services\BookingCompletionService;
use App\Models\LeaveRequest;
use App\Models\Package;
use App\Models\Treatment;
use App\Models\User;
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

        $historyAppointments = (clone $baseQuery)
            ->where(function ($query) use ($today) {
                $query->whereIn('status', ['completed', 'cancelled'])
                    ->orWhereDate('appointment_date', '<', $today);
            })
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
            'collected_today' => (clone $todayBase)->sum('amount_paid'),
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

        if ($user->hasBranchPermission('edit appointments')) {
            $unassignedBookings = Booking::query()
                ->where('spa_id', $user->spa_id)
                ->when($currentBranchId, fn ($q) => $q->where('branch_id', $currentBranchId))
                ->whereNull('therapist_id')
                ->whereIn('status', ['reserved', 'pending'])
                ->whereDate('appointment_date', '>=', $today)
                ->orderBy('appointment_date')
                ->orderBy('start_time')
                ->limit(20)
                ->get()
                ->map(fn ($booking) => $this->decorateBooking($booking));
        }

        return view('appointments', compact(
            'todayAppointments',
            'todayPending',
            'upcomingAppointments',
            'historyAppointments',
            'summary',
            'therapists',
            'unassignedBookings'
        ));
    }

    public function edit(Booking $booking)
    {
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
        $user = Auth::user();
        $spaId = $user->spa_id;
        $branchId = $booking->branch_id;

        $this->syncAutomaticStatuses($spaId, $branchId);
        $booking->refresh();

        if (in_array($booking->status, ['completed', 'cancelled'], true)) {
            return redirect()
                ->route('appointments.index')
                ->with('error', 'This appointment is already ' . $booking->status . ' and can no longer be edited.');
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

        if (in_array($booking->status, ['completed', 'cancelled'], true)) {
            return redirect()->back()->with('error', 'This appointment is already ' . $booking->status . '.');
        }

        $booking->update(['status' => 'cancelled']);

        return redirect()->back()->with('success', 'Appointment cancelled successfully!');
    }

    public function history()
    {
        $this->syncAutomaticStatuses();

        $bookings = Booking::where('customer_user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($bookings);
    }

    public function updateStatus(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['ongoing', 'cancelled'])],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Server-side validation for future appointments
        $today = now()->startOfDay();
        $appointmentDate = Carbon::parse($booking->appointment_date)->startOfDay();

        if ($validated['status'] === 'ongoing' && $appointmentDate->gt($today)) {
            return back()->withErrors([
                'status' => 'Cannot start an appointment that is scheduled for a future date.'
            ])->withInput();
        }

        $serviceTotal = (float) ($booking->total_amount > 0
            ? $booking->total_amount
            : $this->resolveServicePrice($booking->treatment));

        $currentPaid = (float) ($booking->amount_paid ?? 0);
        $remaining = max($serviceTotal - $currentPaid, 0);
        $additionalPayment = (float) ($validated['amount_paid'] ?? 0);

        if ($validated['status'] !== 'cancelled') {
            if ($additionalPayment > $remaining) {
                return back()->withErrors([
                    'amount_paid' => 'Entered amount is greater than the remaining balance.',
                ]);
            }

            if ($remaining > 0 && $additionalPayment <= 0) {
                return back()->withErrors([
                    'amount_paid' => 'Please enter the amount collected for this appointment.',
                ]);
            }
        }

        $newPaid = $validated['status'] === 'cancelled'
            ? $currentPaid
            : $currentPaid + $additionalPayment;

        $newBalance = max($serviceTotal - $newPaid, 0);

        $newPaymentStatus = $booking->payment_status;

        if ($validated['status'] !== 'cancelled') {
            if ($serviceTotal > 0 && $newPaid >= $serviceTotal) {
                $newPaymentStatus = 'paid';
            } elseif ($newPaid > 0) {
                $newPaymentStatus = 'partially_paid';
            } else {
                $newPaymentStatus = 'unpaid';
            }
        }

        $booking->update([
            'status' => $validated['status'],
            'total_amount' => $serviceTotal,
            'amount_paid' => $newPaid,
            'balance_amount' => $newBalance,
            'payment_status' => $newPaymentStatus,
        ]);

        return back()->with('success', 'Appointment status updated successfully.');
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

    private function syncAutomaticStatuses(?int $spaId = null,?int $branchId = null): void
    {
        $now = Carbon::now();

        $query = Booking::query()
            ->whereNotIn('status', ['completed', 'cancelled']);

        if ($spaId) {
            $query->where('spa_id', $spaId);
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $bookings = $query->get();

        foreach ($bookings as $booking) {
            if (!$booking->appointment_date || !$booking->start_time || !$booking->end_time) {
                continue;
            }

            $date = Carbon::parse($booking->appointment_date)->toDateString();

            $start = Carbon::parse($date . ' ' . $booking->start_time);
            $pendingUntil = (clone $start)->addMinutes(15);
            $end = Carbon::parse($date . ' ' . $booking->end_time);

            $newStatus = $booking->status;

            if (in_array($booking->status, ['reserved', 'pending', 'ongoing'])) {
                if ($now->lt($start)) {
                    $newStatus = 'reserved';
                } elseif ($now->gte($end)) {
                    $newStatus = 'completed';
                } else {
                    if ($booking->status === 'ongoing') {
                        $newStatus = 'ongoing';
                    } elseif ($now->lt($pendingUntil)) {
                        $newStatus = 'pending';
                    } else {
                        $newStatus = 'ongoing';
                    }
                }
            }

            if ($booking->status === $newStatus) {
                continue;
            }

            if ($newStatus !== 'completed') {
                $booking->update([
                    'status' => $newStatus,
                ]);

                continue;
            }

            try {
                app(BookingCompletionService::class)
                    ->complete($booking);
            } catch (ValidationException $e) {
                \Illuminate\Support\Facades\Log::warning(
                    'Booking completion inventory consumption failed.',
                    [
                        'booking_id' => $booking->id,
                        'spa_id' => $booking->spa_id,
                        'branch_id' => $booking->branch_id,
                        'errors' => $e->errors(),
                    ]
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error(
                    'Booking completion failed.',
                    [
                        'booking_id' => $booking->id,
                        'spa_id' => $booking->spa_id,
                        'branch_id' => $booking->branch_id,
                        'message' => $e->getMessage(),
                    ]
                );
            }
        }
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
                'collected_today' => (clone $todayBase)->sum('amount_paid'),
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
            'has_pending_reassignment' => $b->has_pending_reassignment ?? false,
        ];
    }
}
