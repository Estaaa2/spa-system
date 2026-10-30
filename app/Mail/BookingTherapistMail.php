<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Booking confirmation, therapist assigned, or therapist changed (one template).
 *
 * The therapist photo is embedded in the email itself (decision Q5): the photo
 * route needs a signed-in session, so a plain <img src> would not load in a mail
 * app. The photo bytes are read here, not in the view, so the view stays simple.
 */
class BookingTherapistMail extends Mailable
{
    use Queueable, SerializesModels;

    public const KIND_CONFIRMED = 'confirmed';
    public const KIND_ASSIGNED  = 'assigned';
    public const KIND_CHANGED   = 'changed';

    public function __construct(public Booking $booking, public string $kind = self::KIND_CONFIRMED)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->kind) {
            self::KIND_ASSIGNED => 'Levictas: Your therapist has been assigned',
            self::KIND_CHANGED  => 'Levictas: Your therapist has changed',
            default             => 'Levictas: Your booking is confirmed',
        });
    }

    public function content(): Content
    {
        $booking = $this->booking;
        $therapist = $booking->therapist;
        $photo = null;

        if ($therapist?->profile_photo_path) {
            $disk = Storage::disk(config('identity.disk', 'local'));

            if ($disk->exists($therapist->profile_photo_path)) {
                $photo = $disk->get($therapist->profile_photo_path);
            }
        }

        $isHome = $booking->requiresArrivalCode();

        return new Content(
            view: 'emails.booking-therapist',
            with: [
                'kind'          => $this->kind,
                'customerName'  => $booking->customer_name ?: 'there',
                'spaName'       => $booking->spa?->name ?? 'your spa',
                'branchName'    => $booking->branch?->name,
                'branchPhone'   => $booking->branch?->profile?->phone,
                'treatment'     => $booking->treatment_label,
                'date'          => $booking->appointment_date?->format('l, F j, Y'),
                'time'          => \Carbon\Carbon::parse($booking->start_time)->format('g:i A'),
                'isHome'        => $isHome,
                'address'       => $booking->customer_address,
                'therapistName' => $therapist ? trim($therapist->first_name.' '.$therapist->last_name) : null,
                'initials'      => $therapist?->initials(),
                'verified'      => (bool) $therapist?->isIdentityVerified(),
                'photoBytes'    => $photo,
                'arrivalCode'   => $isHome && ! $booking->isArrivalConfirmed() ? $booking->arrival_code : null,
                'url'           => url('/'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
