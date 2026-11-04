<?php

namespace App\Console\Commands;

use App\Mail\DocumentExpiringSoon;
use App\Models\Branch;
use App\Models\Spa;
use App\Models\SpaVerificationDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendDocumentExpiryReminders extends Command
{
    /** Same window in which the owner is allowed to renew. */
    private const REMINDER_DAYS = 30;

    protected $signature = 'documents:send-expiry-reminders';

    protected $description = 'Email spa owners whose verification documents expire within 30 days';

    public function handle(): int
    {
        $documents = SpaVerificationDocument::query()
            ->whereNotNull('expiry_date')
            // A renewal that is already waiting for the administrator
            // has expiry_verified_at empty, so it is not reminded again.
            ->whereNotNull('expiry_verified_at')
            ->whereDate('expiry_date', '>=', today())
            ->whereDate('expiry_date', '<=', today()->addDays(self::REMINDER_DAYS))
            ->whereIn(
                'spa_id',
                Spa::query()
                    ->where('verification_status', 'verified')
                    ->select('id')
            )
            ->get()
            // One email per expiration date: skip when a notice was
            // already sent inside this document's current 30-day window.
            // After a renewal the date moves, so the next one sends again.
            ->filter(function ($document) {
                if (! $document->expiry_notice_sent_at) {
                    return true;
                }

                $windowOpens = $document->expiry_date
                    ->copy()
                    ->subDays(self::REMINDER_DAYS)
                    ->startOfDay();

                return $windowOpens->gt($document->expiry_notice_sent_at);
            });

        if ($documents->isEmpty()) {
            $this->info('No documents expiring within 30 days.');

            return self::SUCCESS;
        }

        foreach ($documents as $document) {
            $spa = Spa::with('owner')->find($document->spa_id);

            if (!$spa || !$spa->owner || !$spa->owner->email) {
                Log::warning(
                    "Skipped document expiry reminder for document #{$document->id}: missing spa or owner email"
                );

                continue;
            }

            $branch = $document->branch_id
                ? Branch::find($document->branch_id)
                : null;

            // The branch was removed, so there is nothing to renew.
            if ($document->branch_id && !$branch) {
                continue;
            }

            try {
                Mail::to($spa->owner->email)
                    ->send(new DocumentExpiringSoon($spa, $document, $branch));

                $document->forceFill([
                    'expiry_notice_sent_at' => now(),
                ])->save();

                $this->info(
                    "Reminder sent to {$spa->owner->email} for document #{$document->id}"
                );

                Log::info(
                    "Document expiry reminder sent for spa {$spa->id}, " .
                    "document #{$document->id}, type {$document->document_type}"
                );
            } catch (\Throwable $e) {
                Log::error(
                    "Failed to send document expiry reminder for document #{$document->id}: " .
                    $e->getMessage()
                );
            }
        }

        return self::SUCCESS;
    }
}