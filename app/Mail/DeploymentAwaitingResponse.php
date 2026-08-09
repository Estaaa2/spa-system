<?php

namespace App\Mail;

use App\Models\StaffBranchDeployment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeploymentAwaitingResponse extends Mailable
{
    use Queueable, SerializesModels;

    public StaffBranchDeployment $deployment;

    public function __construct(StaffBranchDeployment $deployment)
    {
        $this->deployment = $deployment;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Action needed: Branch deployment request awaiting your response',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.deployment-awaiting-response',
            with: [
                'staffName'   => $this->deployment->staff?->user?->name ?? 'there',
                'fromBranch'  => $this->deployment->fromBranch->name,
                'toBranch'    => $this->deployment->toBranch->name,
                'startDate'   => $this->deployment->start_date?->format('F d, Y'),
                'isPermanent' => $this->deployment->is_permanent,
                'endDate'     => $this->deployment->end_date?->format('F d, Y'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
