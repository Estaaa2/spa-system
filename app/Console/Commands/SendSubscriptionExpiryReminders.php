<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionExpiringSoon;
use App\Models\Spa;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionExpiryReminders extends Command
{
    protected $signature = 'subscriptions:send-expiry-reminders';

    protected $description = 'Email spa owners whose Professional subscription expires in 3 days';

    public function handle(): int
    {
        $targetDate = now()->addDays(3)->toDateString();

        $subscriptions = Subscription::where('payment_status', 'paid')
            ->whereNotNull('expires_at')
            ->whereNull('expiry_notice_sent_at')
            ->whereDate('expires_at', $targetDate)
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No subscriptions expiring in 3 days.');
            return self::SUCCESS;
        }

        foreach ($subscriptions as $subscription) {
            $spa = Spa::with('owner')->find($subscription->spa_id);

            if (! $spa || ! $spa->owner || ! $spa->owner->email) {
                Log::warning("Skipped expiry reminder for subscription #{$subscription->id}: missing spa or owner email");
                continue;
            }

            // Only remind if the spa is still on professional at this point
            // (e.g. skip if it was already cancelled manually)
            if ($spa->business_tier !== 'professional') {
                continue;
            }

            try {
                Mail::to($spa->owner->email)
                    ->send(new SubscriptionExpiringSoon($spa, $subscription));

                $subscription->update(['expiry_notice_sent_at' => now()]);

                $this->info("Reminder sent to {$spa->owner->email} for spa {$spa->id}");
                Log::info("Subscription expiry reminder sent for spa {$spa->id}, subscription #{$subscription->id}");
            } catch (\Exception $e) {
                Log::error("Failed to send expiry reminder for subscription #{$subscription->id}: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
