<?php

namespace Database\Factories;

use App\Models\PayrollRun;
use App\Models\Spa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Default: draft regular cutoff-1 run for the current month, using the locked
 * schema defaults (first cutoff day 15, pay-day offset 5).
 *
 * Runs can only be created as draft. approved()/finalized()/released() walk the
 * real transitions after creation — so create payslips/lines BEFORE applying
 * those states (writes to non-draft runs throw, by design).
 *
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    protected $model = PayrollRun::class;

    public function definition(): array
    {
        $start = Carbon::now()->startOfMonth();
        $end   = $start->copy()->day(15);

        return [
            'spa_id'         => Spa::factory(),
            'run_type'       => PayrollRun::TYPE_REGULAR,
            'period_start'   => $start->toDateString(),
            'period_end'     => $end->toDateString(),
            'cutoff_no'      => 1,
            'pay_date'       => $end->copy()->addDays(5)->toDateString(),
            'status'         => PayrollRun::STATUS_DRAFT,
            'config_version' => 'test',
            'generated_by'   => User::factory(),
        ];
    }

    public function forMonth(int $year, int $month, int $cutoff = 1): static
    {
        return $this->state(function () use ($year, $month, $cutoff) {
            $monthStart = Carbon::create($year, $month, 1);
            $start = $cutoff === 1 ? $monthStart : $monthStart->copy()->day(16);
            $end   = $cutoff === 1 ? $monthStart->copy()->day(15) : $monthStart->copy()->endOfMonth();

            return [
                'run_type'     => PayrollRun::TYPE_REGULAR,
                'cutoff_no'    => $cutoff,
                'period_start' => $start->toDateString(),
                'period_end'   => $end->toDateString(),
                'pay_date'     => $end->copy()->addDays(5)->toDateString(),
            ];
        });
    }

    public function thirteenthMonth(int $year): static
    {
        return $this->state(fn () => [
            'run_type'     => PayrollRun::TYPE_THIRTEENTH_MONTH,
            'cutoff_no'    => null,
            'period_start' => Carbon::create($year, 1, 1)->toDateString(),
            'period_end'   => Carbon::create($year, 12, 31)->toDateString(),
            'pay_date'     => Carbon::create($year, 12, 24)->toDateString(),
        ]);
    }

    public function approved(): static
    {
        return $this->afterCreating(fn (PayrollRun $run) => self::walk($run, [PayrollRun::STATUS_APPROVED]));
    }

    public function finalized(): static
    {
        return $this->afterCreating(fn (PayrollRun $run) => self::walk($run, [
            PayrollRun::STATUS_APPROVED,
            PayrollRun::STATUS_FINALIZED,
        ]));
    }

    public function released(): static
    {
        return $this->afterCreating(fn (PayrollRun $run) => self::walk($run, [
            PayrollRun::STATUS_APPROVED,
            PayrollRun::STATUS_FINALIZED,
            PayrollRun::STATUS_RELEASED,
        ]));
    }

    /** Apply each transition as a separate save, with its actor + timestamp. */
    protected static function walk(PayrollRun $run, array $statuses): void
    {
        $actor = User::factory()->create()->getKey();

        foreach ($statuses as $status) {
            $prefix = match ($status) {
                PayrollRun::STATUS_APPROVED  => 'approved',
                PayrollRun::STATUS_FINALIZED => 'finalized',
                PayrollRun::STATUS_RELEASED  => 'released',
            };

            $run->forceFill([
                'status'          => $status,
                "{$prefix}_by"    => $actor,
                "{$prefix}_at"    => now(),
            ])->save();
        }
    }
}
