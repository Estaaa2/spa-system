<?php
// File path: app/Console/Commands/ProcessScheduledDeployments.php
//
// REGISTRATION (Laravel 11+):
// Add this line to routes/console.php:
//     Schedule::command('deployments:process')->dailyAt('00:05');
//
// Or in app/Console/Kernel.php (Laravel 10):
//     $schedule->command('deployments:process')->dailyAt('00:05');

namespace App\Console\Commands;

use App\Models\StaffBranchDeployment;
use Illuminate\Console\Command;

class ProcessScheduledDeployments extends Command
{
    protected $signature   = 'deployments:process';
    protected $description = 'Activate approved branch deployments and revert completed ones';

    public function handle(): int
    {
        $today     = now()->toDateString();
        $activated = 0;
        $reverted  = 0;

        $this->info('[Branch Deployments] Processing for ' . $today);

        // ── Step 1: Activate approved deployments whose start_date has arrived ──
        StaffBranchDeployment::with(['staff.user'])
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->each(function (StaffBranchDeployment $deployment) use (&$activated) {
                $staff = $deployment->staff;

                if (!$staff) {
                    $this->warn("  ⚠ Deployment #{$deployment->id}: staff record missing, skipping.");
                    return;
                }

                // Move staff to new branch in both tables
                $staff->update(['branch_id' => $deployment->to_branch_id]);

                if ($staff->user) {
                    $staff->user->update(['branch_id' => $deployment->to_branch_id]);
                }

                $deployment->update([
                    'status'      => 'active',
                    'deployed_at' => now(),
                ]);

                $activated++;
                $this->line("  ✓ Activated #{$deployment->id}: {$staff->user?->name} → {$deployment->toBranch?->name}");
            });

        // ── Step 2: Revert active non-permanent deployments past their end_date ──
        StaffBranchDeployment::with(['staff.user', 'fromBranch'])
            ->where('status', 'active')
            ->where('is_permanent', false)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', $today)   // strictly before today = fully elapsed
            ->each(function (StaffBranchDeployment $deployment) use (&$reverted) {
                $staff = $deployment->staff;

                if (!$staff) {
                    $this->warn("  ⚠ Deployment #{$deployment->id}: staff record missing, skipping.");
                    return;
                }

                // Return staff to their original branch
                $staff->update(['branch_id' => $deployment->from_branch_id]);

                if ($staff->user) {
                    $staff->user->update(['branch_id' => $deployment->from_branch_id]);
                }

                $deployment->update([
                    'status'      => 'completed',
                    'reverted_at' => now(),
                ]);

                $reverted++;
                $this->line("  ✓ Completed #{$deployment->id}: {$staff->user?->name} returned to {$deployment->fromBranch?->name}");
            });

        $this->info("[Branch Deployments] Done. Activated: {$activated} | Reverted: {$reverted}");

        return Command::SUCCESS;
    }
}
