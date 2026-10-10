<?php

namespace App\Mail;

use App\Models\Branch;
use App\Models\Spa;
use App\Models\SpaVerificationDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentExpiringSoon extends Mailable
{
    use Queueable, SerializesModels;

    public Spa $spa;

    public SpaVerificationDocument $document;

    public ?Branch $branch;

    public string $documentName;

    public function __construct(
        Spa $spa,
        SpaVerificationDocument $document,
        ?Branch $branch = null
    ) {
        $this->spa = $spa;
        $this->document = $document;
        $this->branch = $branch;

        $this->documentName = match ($document->document_type) {
            'government_id' => 'Government ID',
            'dti_sec' => 'DTI / SEC Certificate',
            'bir_certificate' => 'BIR Certificate of Registration',
            'business_permit' => 'Business Permit',
            default => 'verification document',
        };
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your {$this->documentName} on Levictas is expiring soon",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document-expiring',
            with: [
                'spaName' => $this->spa->name ?? 'your spa',
                'branchName' => $this->branch?->name,
                'documentName' => $this->documentName,
                'expiresAt' => $this->document->expiry_date,
                // Only the Business Permit locks a branch when it lapses.
                'locksBranch' => $this->document->document_type === 'business_permit',
                'graceDays' => Branch::DOCUMENT_GRACE_DAYS,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}