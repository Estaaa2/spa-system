<?php

declare(strict_types=1);

namespace App\Services\Hiring;

use App\Models\JobPosting;
use App\Models\Spa;
use Illuminate\Support\Collection;

/**
 * Whether a branch is hiring, worked out from its open job postings
 * (staff scheduling & hiring Phase 5, Q7 A). Replaces the manual
 * "Job Posting" toggle on Edit Branch; branch_profiles.is_hiring and
 * hiring_note stay in the table but are no longer read.
 *
 * The landing page, the mobile API (is_hiring / hiring_note, unchanged
 * field names) and the Apply endpoint all ask this class. All open
 * postings load in one query per request.
 */
final class HiringStatus
{
    /** @var array<int, Collection<int, JobPosting>>|null branch id → open postings */
    private ?array $byBranch = null;

    /** @var array<int, bool> spa id → has the manpower feature */
    private array $manpower = [];

    /** Open postings for a branch, oldest first. Empty when the spa's plan has no manpower feature. */
    public function openings(int $branchId): Collection
    {
        $this->load();

        return $this->byBranch[$branchId] ?? collect();
    }

    public function isHiring(int $branchId): bool
    {
        return $this->openings($branchId)->isNotEmpty();
    }

    /** "Open positions: Massage Therapist (2), Receptionist" or null. */
    public function note(int $branchId): ?string
    {
        $openings = $this->openings($branchId);
        if ($openings->isEmpty()) {
            return null;
        }

        $parts = $openings->map(fn (JobPosting $p) => $p->title.($p->openings > 1 ? " ({$p->openings})" : ''));

        return 'Open positions: '.$parts->implode(', ');
    }

    public function flush(): void
    {
        $this->byBranch = null;
        $this->manpower = [];
    }

    private function load(): void
    {
        if ($this->byBranch !== null) {
            return;
        }

        $this->byBranch = [];
        JobPosting::query()->open()->orderBy('created_at')->orderBy('id')->get()
            ->filter(fn (JobPosting $p) => $this->spaHasManpower((int) $p->spa_id))
            ->groupBy('branch_id')
            ->each(function (Collection $postings, $branchId) {
                $this->byBranch[(int) $branchId] = $postings->values();
            });
    }

    private function spaHasManpower(int $spaId): bool
    {
        return $this->manpower[$spaId] ??= (bool) (Spa::withoutGlobalScopes()->find($spaId)?->hasFeature('manpower') ?? false);
    }
}
