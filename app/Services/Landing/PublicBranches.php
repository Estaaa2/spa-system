<?php

namespace App\Services\Landing;

use App\Models\Branch;
use App\Models\Spa;

/**
 * Which branches the public may see (landing page, search, "Spas near you",
 * the chat's "View branch" button) and message (Nov 15, 2026). One copy of the
 * rule, so the landing page and the chat can't disagree about it.
 *
 *   Spa:    verified, has access (trial or paid), and its plan includes
 *           branch_public_listing.
 *   Branch: not removed, listed by the spa, and operational (verification
 *           cleared, documents valid, inside the plan's branch limit) and
 *           ready for bookings (a therapist and a service).
 */
class PublicBranches
{
    /** @var array<int, int> spa id → number of public branches, per request */
    private array $counts = [];

    public function spaIsListable(Spa $spa): bool
    {
        return $spa->verification_status === 'verified'
            && $spa->hasAccess()
            && $spa->hasFeature('branch_public_listing');
    }

    /** Approved, inside the plan's branch limit, permit valid, and bookable. */
    public function branchIsOperational(Spa $spa, Branch $branch): bool
    {
        $branch->setRelation('spa', $spa);

        $operational = $branch->isOperational() && $branch->isReadyForBookings();

        // Unset again so the spa is not nested inside each branch when these
        // models are passed to a view or turned into JSON.
        $branch->unsetRelation('spa');

        return $operational;
    }

    public function isPublic(?Spa $spa, ?Branch $branch): bool
    {
        return $spa !== null
            && $branch !== null
            && ! $branch->trashed()
            && (int) $branch->spa_id === (int) $spa->id
            && (bool) $branch->profile?->is_listed
            && $this->spaIsListable($spa)
            && $this->branchIsOperational($spa, $branch);
    }

    /** How many branches of the spa the public can see (for the card's branch tag). */
    public function count(Spa $spa): int
    {
        if (! array_key_exists($spa->id, $this->counts)) {
            $this->counts[$spa->id] = ! $this->spaIsListable($spa) ? 0 : Branch::query()
                ->where('spa_id', $spa->id)
                ->verificationCleared()
                ->whereHas('profile', fn ($q) => $q->where('is_listed', true))
                ->with('profile')
                ->get()
                ->filter(fn (Branch $b) => $this->branchIsOperational($spa, $b))
                ->count();
        }

        return $this->counts[$spa->id];
    }

    /**
     * The branch tag on a spa card: "Imus Branch", only when the spa has 2+
     * public branches (decision Q5a). Single-branch spas have nothing to tell
     * apart, and their branch name is often "Main" or the spa's own name.
     */
    public static function cardTag(?string $branchName, int $publicBranchCount): ?string
    {
        return $publicBranchCount > 1 ? self::label($branchName) : null;
    }

    /** "Imus" → "Imus Branch"; a name that already has the word "Branch" stays as it is. */
    public static function label(?string $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return preg_match('/\bbranch\b/i', $name) ? $name : $name.' Branch';
    }
}
