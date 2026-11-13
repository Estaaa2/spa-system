<?php

namespace App\Console\Commands;

use App\Models\Spa;
use App\Services\DecisionSupport\RecommendationEngine;
use Illuminate\Console\Command;
use Throwable;

/**
 * DSS revamp (Q5 B): finds this morning's recommendations for every spa whose
 * plan includes Decision Support (plan feature `insights`). Scheduled daily in
 * routes/console.php; the "Refresh now" button runs the same sync for one spa.
 */
class GenerateDssRecommendations extends Command
{
    protected $signature = 'dss:generate {--spa= : Only this spa id}';

    protected $description = 'Compute Decision Support recommendations (staffing, hiring, promos, expansion) and store them';

    public function handle(RecommendationEngine $engine): int
    {
        $query = Spa::query()->orderBy('id');
        if ($id = $this->option('spa')) {
            $query->whereKey($id);
        }

        $done = 0;
        foreach ($query->cursor() as $spa) {
            if (! $spa->hasFeature('insights')) {
                continue;
            }
            try {
                $open = $engine->sync($spa);
                $this->line("Spa #{$spa->id}: {$open} open");
                $done++;
            } catch (Throwable $e) {
                // One broken spa must not stop the others.
                report($e);
                $this->error("Spa #{$spa->id}: ".$e->getMessage());
            }
        }

        $this->info("Decision Support updated for {$done} spa(s).");

        return self::SUCCESS;
    }
}
