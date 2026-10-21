<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

/**
 * Marks abandoned plan checkouts as expired so the admin "Unpaid" list only
 * shows real, recent attempts. A late payment for an expired row is still
 * activated by the PayMongo webhook.
 */
class ExpirePendingSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire-pending';

    protected $description = 'Mark unpaid subscription checkouts older than the configured hours as expired';

    public function handle(): int
    {
        $hours = (int) config('subscription.pending_expiry_hours', 24);

        $count = Subscription::query()
            ->where('payment_status', 'pending')
            ->where('created_at', '<', now()->subHours($hours))
            ->update([
                'payment_status' => 'expired',
                'status' => 'expired',
            ]);

        $this->info("Expired {$count} abandoned checkout(s).");

        return self::SUCCESS;
    }
}
