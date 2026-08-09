<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentUpdated extends Mailable
{
    use Queueable, SerializesModels;

    public Booking $booking;
    public string $oldDate;
    public string $oldStartTime;

    public function __construct(Booking $booking, string $oldDate, string $oldStartTime)
    {
        $this->booking      = $booking;
        $this->oldDate      = $oldDate;
        $this->oldStartTime = $oldStartTime;
    }

    public function build()
    {
        return $this->subject('Your Appointment Schedule Has Been Updated')
            ->view('emails.appointment-updated');
    }
}
