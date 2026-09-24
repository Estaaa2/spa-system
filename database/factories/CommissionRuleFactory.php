<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\CommissionRule;
use App\Models\Spa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: spa-wide default rule, percent method.
 *
 * @extends Factory<CommissionRule>
 */
class CommissionRuleFactory extends Factory
{
    protected $model = CommissionRule::class;

    public function definition(): array
    {
        return [
            'spa_id'         => Spa::factory(),
            'branch_id'      => null,
            'target_type'    => CommissionRule::TARGET_DEFAULT,
            'target_id'      => null,
            'method'         => CommissionRule::METHOD_PERCENT,
            'value'          => 10, // arbitrary test value
            'effective_from' => now()->startOfYear()->toDateString(),
            'effective_to'   => null,
            'created_by'     => User::factory(),
        ];
    }

    public function forBranch(Branch $branch): static
    {
        return $this->state(fn () => [
            'spa_id'    => $branch->spa_id,
            'branch_id' => $branch->getKey(),
        ]);
    }

    public function forTreatment(int $treatmentId): static
    {
        return $this->state(fn () => [
            'target_type' => CommissionRule::TARGET_TREATMENT,
            'target_id'   => $treatmentId,
        ]);
    }

    public function forPackage(int $packageId): static
    {
        return $this->state(fn () => [
            'target_type' => CommissionRule::TARGET_PACKAGE,
            'target_id'   => $packageId,
        ]);
    }

    public function flat(float $amount): static
    {
        return $this->state(fn () => [
            'method' => CommissionRule::METHOD_FLAT,
            'value'  => $amount,
        ]);
    }

    public function between($from, $to = null): static
    {
        return $this->state(fn () => [
            'effective_from' => $from,
            'effective_to'   => $to,
        ]);
    }
}
