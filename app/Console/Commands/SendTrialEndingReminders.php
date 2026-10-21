<?php

namespace App\Console\Commands;

use App\Mail\TrialEndingSoon;
use App\Models\Spa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails owners whose free trial ends within the configured days and who
 * have not paid for a plan yet. Sent once per spa.
 */
class SendTrialEndingReminders extends Command
{
    protected $signature = 'subscriptions:send-trial-reminders';

    protected $description = 'Email spa owners whose free trial ends soon and who have not chosen a plan';

    public function handle(): int
    {
        $days = (int) config('subscription.trial_reminder_days', 3);

        $spas = Spa::query()
            ->with('owner')
            ->where('trial_used', true)
            ->whereNull('trial_reminder_sent_at')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->where('trial_ends_at', '<=', now()->addDays($days))
            ->get();

        $sent = 0;

        foreach ($spas as $spa) {
            // Already paid for the period after the trial: nothing to remind.
            if ($spa->hasEverPaid()) {
                $spa->update(['trial_reminder_sent_at' => now()]);

                continue;
            }

            $email = $spa->owner?->email;

            if (! $email) {
                Log::warning("Skipped trial reminder for spa {$spa->id}: no owner email");

                continue;
            }

            try {
                Mail::to($email)->send(new TrialEndingSoon($spa));

                $spa->update(['trial_reminder_sent_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                Log::error("Failed to send trial reminder for spa {$spa->id}: " . $e->getMessage());
            }
        }

        $this->info("Sent {$sent} trial reminder(s).");

        return self::SUCCESS;
    }
}
