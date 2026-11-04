<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\Bookings\ArrivalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * POST /appointments/{booking}/arrival          assigned therapist enters the code
 * POST /appointments/{booking}/arrival/manual   branch confirms without the code (edit appointments)
 *
 * Forms live in partials/home-visits.blade.php (dashboard and Appointments).
 * Rules: App\Services\Bookings\ArrivalService.
 */
class ArrivalController extends Controller
{
    public function __construct(private ArrivalService $arrivals)
    {
    }

    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validateWithBag('arrival', [
            'arrival_code' => ['required', 'string', 'max:10'],
        ], [
            'arrival_code.required' => 'Enter the 4-digit code the customer tells you.',
        ]);

        abort_unless((int) $booking->spa_id === (int) $request->user()->spa_id, 404);

        try {
            $this->arrivals->confirmWithCode($booking, $request->user(), $validated['arrival_code']);
        } catch (ValidationException $e) {
            throw $e->errorBag('arrival')->redirectTo(url()->previous().'#home-visits');
        }

        return redirect()->to(url()->previous().'#home-visits')
            ->with('success', 'Arrival confirmed for '.($booking->customer_name ?: 'the customer').'.');
    }

    public function manual(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validateWithBag('arrival', [
            'arrival_reason' => ['required', 'string', 'max:500'],
        ], [
            'arrival_reason.required' => 'Write why the code could not be used.',
        ]);

        $user = $request->user();

        // Same spa, and the user's branch unless they work across branches.
        abort_unless(
            (int) $booking->spa_id === (int) $user->spa_id
                && ($user->isSpaWide() || (int) $booking->branch_id === (int) $user->currentBranchId()),
            404
        );

        try {
            $this->arrivals->confirmManually($booking, $user, $validated['arrival_reason']);
        } catch (ValidationException $e) {
            throw $e->errorBag('arrival')->redirectTo(url()->previous().'#home-visits');
        }

        return redirect()->to(url()->previous().'#home-visits')
            ->with('success', 'Arrival confirmed by hand. The reason was recorded.');
    }
}
