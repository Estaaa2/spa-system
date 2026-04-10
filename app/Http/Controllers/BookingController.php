<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Package;
use App\Models\Treatment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
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

        return view('booking', compact('therapists', 'treatments', 'packages'));
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

        $this->syncAutomaticStatuses($spaId, $branchId);

        $validated = $request->validate([
            'appointment_date' => ['required', 'date'],
            'start_time' => ['required'],
            'treatment' => ['required', 'string'],
        ]);

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

        $available = $this->getAvailableTherapists(
            $spaId,
            $branchId,
            $validated['appointment_date'],
            $validated['start_time'],
            $endTime
        );

        $recommended = $this->pickRecommendedTherapist(
            $spaId,
            $branchId,
            $validated['appointment_date'],
            $validated['start_time'],
            $endTime
        );

        return response()->json([
            'therapists' => $available->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->first_name . ' ' . $t->last_name,
            ])->values(),
            'recommended_id' => $recommended?->id,
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
            'customer_email' => 'required|email|max:255',
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

        $recommendedTherapist = $this->pickRecommendedTherapist(
            $spaId,
            $branchId,
            $validated['appointment_date'],
            $startTime,
            $endTime
        );

        if (!$recommendedTherapist) {
            return back()->withErrors([
                'start_time' => 'No therapist is available for the selected time.'
            ])->withInput();
        }

        $availableTherapists = $this->getAvailableTherapists(
            $spaId,
            $branchId,
            $validated['appointment_date'],
            $startTime,
            $endTime
        );

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
            ->paginate(5, ['*'], 'pending_page');

        $todayPending->getCollection()->transform(
            fn ($booking) => $this->decorateBooking($booking)
        );

        $todayAppointments = (clone $todayBase)
            ->orderBy('start_time', 'asc')
            ->paginate(10, ['*'], 'today_page');

        $todayAppointments->getCollection()->transform(
            fn ($booking) => $this->decorateBooking($booking)
        );

        $upcomingBase = (clone $baseQuery)
            ->whereDate('appointment_date', '>', $today)
            ->whereIn('status', ['reserved', 'pending']);

        $upcomingAppointments = (clone $upcomingBase)
            ->orderBy('appointment_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate(5, ['*'], 'upcoming_page');

        $upcomingAppointments->getCollection()->transform(
            fn ($booking) => $this->decorateBooking($booking)
        );

        $historyAppointments = (clone $baseQuery)
            ->where(function ($query) use ($today) {
                $query->whereIn('status', ['completed', 'cancelled'])
                    ->orWhereDate('appointment_date', '<', $today);
            })
            ->orderBy('appointment_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate(10, ['*'], 'history_page');

        $historyAppointments->getCollection()->transform(
            fn ($booking) => $this->decorateBooking($booking)
        );

        $summary = [
            'today_total' => (clone $todayBase)->count(),
            'pending_today' => (clone $todayBase)->where('status', 'pending')->count(),
            'upcoming_total' => (clone $upcomingBase)->count(),
            'collected_today' => (clone $todayBase)->sum('amount_paid'),
        ];

        $therapists = User::role('therapist')
            ->whereHas('staff', function ($q) use ($user, $currentBranchId) {
                $q->where('spa_id', $user->spa_id);

                if ($currentBranchId) {
                    $q->where('branch_id', $currentBranchId);
                }
            })
            ->orderBy('first_name')
            ->get();

        return view('appointments', compact(
            'todayAppointments',
            'todayPending',
            'upcomingAppointments',
            'historyAppointments',
            'summary',
            'therapists'
        ));
    }

    public function edit(Booking $booking)
    {
        $user = Auth::user();

        $this->syncAutomaticStatuses($user->spa_id, $booking->branch_id);

        $therapists = User::role('therapist')
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

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:255',
            'customer_address' => 'nullable|string|max:255',
            'service_type' => 'required|string|in:in_branch,in_home',
            'treatment' => 'required|string',
            'therapist_id' => 'required|exists:users,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required',
        ]);

        // Block setting ongoing/completed for future appointments
        $today = now()->startOfDay();
        $appointmentDate = Carbon::parse($validated['appointment_date'])->startOfDay();

        if (in_array($booking->status, ['ongoing', 'completed']) && $appointmentDate->gt($today)) {
            return back()->withErrors([
                'status' => 'Cannot mark an appointment as "' . $validated['status'] . '" if the appointment date is in the future.'
            ])->withInput();
        }

        // Prevent completing appointments that haven't started yet (for today)
        if ($booking->status === 'completed' &&
            $booking->status !== 'ongoing' &&
            $appointmentDate->eq($today)) {
            return back()->withErrors([
                'status' => 'Appointment must be marked as "Ongoing" first before it can be completed.'
            ])->withInput();
        }

        $durationMinutes = $this->resolveDurationMinutes($validated['treatment']);

        if ($durationMinutes <= 0) {
            return back()->withErrors([
                'treatment' => 'Invalid treatment or package duration.'
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

        $availableTherapists = $this->getAvailableTherapists(
            $spaId,
            $branchId,
            $validated['appointment_date'],
            $validated['start_time'],
            $end->format('H:i'),
            $booking->id
        );

        if (!$availableTherapists->pluck('id')->contains((int) $validated['therapist_id'])) {
            return back()->withErrors([
                'therapist_id' => 'Selected therapist is not available for this time range.'
            ])->withInput();
        }

        $booking->update([
            ...$validated,
            'end_time' => $end->format('H:i'),
        ]);

        return redirect()
            ->route('appointments.index')
            ->with('success', 'Appointment updated successfully.');
    }

    public function destroy($id)
    {
        $booking = Booking::findOrFail($id);
        $booking->delete();

        return redirect()->back()->with('success', 'Appointment deleted successfully!');
    }

    public function history()
    {
        $this->syncAutomaticStatuses();

        $bookings = Booking::where('customer_user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($bookings);
    }

    public function reserve(Booking $booking)
    {
        if (in_array($booking->status, ['cancelled', 'completed', 'ongoing'])) {
            return back()->withErrors('Booking already processed.');
        }

        $therapistId = request('therapist_id') ?? request('therapist');

        $booking->update([
            'status' => 'reserved',
            'therapist_id' => $therapistId ?: $booking->therapist_id,
        ]);

        return back()->with('success', 'Booking reserved successfully.');
    }

    public function storeOnline(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'spa_id' => ['required', 'exists:spas,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'service_type' => ['required', 'string', 'in:in_branch,in_home'],
            'treatment' => ['required', 'string'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required'],
            'customer_phone' => ['nullable', 'string', 'max:255'],
            'customer_address' => [
                $request->service_type === 'in_home' ? 'required' : 'nullable',
                'string',
                'max:255'
            ],
        ]);

        $branchOk = \App\Models\Branch::where('id', $validated['branch_id'])
            ->where('spa_id', $validated['spa_id'])
            ->exists();

        abort_unless($branchOk, 422, 'Selected branch does not belong to this spa.');

        $this->syncAutomaticStatuses((int) $validated['spa_id'], (int) $validated['branch_id']);

        $durationMinutes = $this->resolveDurationMinutes($validated['treatment']);

        if ($durationMinutes <= 0) {
            $durationMinutes = 60;
        }

        $dayOfWeek = Carbon::parse($validated['appointment_date'])->format('l');
        $hours = \App\Models\OperatingHours::where('branch_id', $validated['branch_id'])
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
                'start_time' => "Please select a time within spa hours: {$hours->opening_time} - {$hours->closing_time}"
            ])->withInput();
        }

        $endTime = $start->copy()->addMinutes($durationMinutes)->format('H:i');

        if (Carbon::parse($endTime)->gt($closing)) {
            return back()->withErrors([
                'start_time' => 'Ends after closing hours. Choose an earlier time.'
            ])->withInput();
        }

        $recommendedTherapist = $this->pickRecommendedTherapist(
            (int) $validated['spa_id'],
            (int) $validated['branch_id'],
            $validated['appointment_date'],
            $validated['start_time'],
            $endTime
        );

        if (!$recommendedTherapist) {
            return back()->withErrors([
                'start_time' => 'No therapist is available for the selected schedule.'
            ])->withInput();
        }

        Booking::create([
            'spa_id' => $validated['spa_id'],
            'branch_id' => $validated['branch_id'],
            'customer_user_id' => auth()->id(),
            'created_by_user_id' => null,
            'booking_source' => 'online',
            'status' => 'reserved',
            'service_type' => $validated['service_type'],
            'treatment' => $validated['treatment'],
            'customer_name' => $user->first_name . ' ' . $user->last_name,
            'customer_email' => $user->email,
            'customer_phone' => $validated['customer_phone'] ?? null,
            'customer_address' => $validated['customer_address'] ?? null,
            'appointment_date' => $validated['appointment_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $endTime,
            'therapist_id' => $recommendedTherapist->id,
        ]);

        return back()->with('success', 'Booking reserved successfully!');
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

    private function getAllowedEditStatuses(Booking $booking): array
    {
        return match ($booking->status) {
            'pending'  => ['ongoing', 'cancelled'],
            'ongoing'  => ['cancelled'],
            'reserved' => ['reserved', 'cancelled'],
            default    => [$booking->status], // completed/cancelled are locked
        };
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

    private function getBranchTherapists(int $spaId, int $branchId): Collection
    {
        return User::role('therapist')
            ->whereHas('staff', function ($q) use ($spaId, $branchId) {
                $q->where('spa_id', $spaId)
                    ->where('branch_id', $branchId)
                    ->where('employment_status', 'active');
            })
            ->orderBy('first_name')
            ->get();
    }

    private function getAvailableTherapists(
        int $spaId,
        int $branchId,
        string $appointmentDate,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId = null
    ): Collection {
        $therapists = $this->getBranchTherapists($spaId, $branchId);

        if ($therapists->isEmpty()) {
            return collect();
        }

        $therapistIds = $therapists->pluck('id');

        $busyIds = Booking::query()
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->where('appointment_date', $appointmentDate)
            ->whereIn('therapist_id', $therapistIds)
            ->whereIn('status', ['reserved', 'pending', 'ongoing'])
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $startTime);
            })
            ->pluck('therapist_id')
            ->unique();

        return $therapists
            ->reject(fn ($therapist) => $busyIds->contains($therapist->id))
            ->values();
    }

    private function pickRecommendedTherapist(
        int $spaId,
        int $branchId,
        string $appointmentDate,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId = null
    ): ?User {
        $available = $this->getAvailableTherapists(
            $spaId,
            $branchId,
            $appointmentDate,
            $startTime,
            $endTime,
            $excludeBookingId
        );

        if ($available->isEmpty()) {
            return null;
        }

        $stats = Booking::query()
            ->select(
                'therapist_id',
                DB::raw('COUNT(*) as total_jobs'),
                DB::raw('MAX(end_time) as last_end_time')
            )
            ->where('spa_id', $spaId)
            ->where('branch_id', $branchId)
            ->where('appointment_date', $appointmentDate)
            ->whereIn('therapist_id', $available->pluck('id'))
            ->where('status', '!=', 'cancelled')
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))
            ->groupBy('therapist_id')
            ->get()
            ->keyBy('therapist_id');

        return $available->sort(function ($a, $b) use ($stats) {
            $aJobs = (int) ($stats[$a->id]->total_jobs ?? 0);
            $bJobs = (int) ($stats[$b->id]->total_jobs ?? 0);

            if ($aJobs !== $bJobs) {
                return $aJobs <=> $bJobs;
            }

            $aLastEnd = $stats[$a->id]->last_end_time ?? '00:00:00';
            $bLastEnd = $stats[$b->id]->last_end_time ?? '00:00:00';

            if ($aLastEnd !== $bLastEnd) {
                return strcmp($aLastEnd, $bLastEnd);
            }

            return strcmp(mb_strtolower($a->first_name), mb_strtolower($b->first_name));
        })->first();
    }

    private function syncAutomaticStatuses(?int $spaId = null, ?int $branchId = null): void
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
                    // Between start and end
                    if ($booking->status === 'ongoing') {
                        $newStatus = 'ongoing'; // keep manual ongoing
                    } elseif ($now->lt($pendingUntil)) {
                        $newStatus = 'pending';
                    } else {
                        $newStatus = 'ongoing';
                    }
                }
            }

            if ($booking->status !== $newStatus) {
                $booking->update([
                    'status' => $newStatus,
                ]);
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
        if (str_starts_with($selection, 'treatment_')) {
            $id = (int) str_replace('treatment_', '', $selection);
            $treatment = Treatment::withoutGlobalScopes()->find($id);
            return (float) ($treatment?->price ?? 0);
        }

        if (str_starts_with($selection, 'package_')) {
            $id = (int) str_replace('package_', '', $selection);
            $package = Package::withoutGlobalScopes()->find($id);
            return (float) ($package?->price ?? 0);
        }

        return 0;
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
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->limit(25)
            ->get()
            ->map(fn ($b) => $this->formatForLive($b));
 
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
                                            ? \Carbon\Carbon::parse($b->start_time)->format('h:i A')
                                            : '—',
            'end_time_fmt'            => $b->end_time
                                            ? \Carbon\Carbon::parse($b->end_time)->format('h:i A')
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
        ];
    }
}