<?php

namespace App\Console\Commands;

use App\Models\Spa;
use App\Services\Subscription\PlanLimitEnforcer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Applies each spa's staff limit. Plans change at set times (trial end,
 * a queued Basic period starting after Premium), so this runs hourly; it
 * also runs right after a payment and after the owner saves the Plan
 * limits page.
 */
class EnforcePlanLimits extends Command
{
    protected $signature = 'subscriptions:enforce-limits {--spa= : Only this spa id}';

    protected $description = 'Pause staff above the plan limit and restore them when there is room again';

    public function handle(PlanLimitEnforcer $enforcer): int
    {
        $query = Spa::query()->where('verification_status', 'verified');

        if ($this->option('spa')) {
            $query->whereKey((int) $this->option('spa'));
        }

        $changed = 0;

        $query->with('subscriptions')->chunkById(100, function ($spas) use ($enforcer, &$changed) {
            foreach ($spas as $spa) {
                try {
                    $result = $enforcer->sync($spa);

                    if ($result['newly_paused'] > 0 || $result['restored'] > 0) {
                        $changed++;

                        Log::info('Plan limits applied', ['spa_id' => $spa->id] + $result);
                    }
                } catch (\Throwable $e) {
                    Log::error("Plan limit enforcement failed for spa {$spa->id}: " . $e->getMessage());
                }
            }
        });

        $this->info("Plan limits applied; {$changed} spa(s) changed.");

        return self::SUCCESS;
    }
}
