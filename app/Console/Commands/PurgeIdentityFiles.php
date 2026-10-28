<?php

namespace App\Console\Commands;

use App\Models\IdentityVerification;
use App\Services\Identity\IdentityVerificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Safety net for D9 ("ID and selfie files are deleted after the decision").
 * Scheduled daily in routes/console.php.
 *
 * 1. Rows that are no longer pending but still hold file paths: files deleted,
 *    paths cleared (covers a crash between the decision and the delete).
 * 2. Files in the verification folder that no pending row points to.
 * 3. Files in the profile-photo folder that no user points to.
 *
 * Files younger than config('identity.orphan_grace_minutes') are skipped so an
 * upload that is still being saved is never removed.
 *
 *   php artisan identity:purge --dry-run
 */
class PurgeIdentityFiles extends Command
{
    protected $signature = 'identity:purge {--dry-run : Only report what would be deleted}';

    protected $description = 'Delete identity-verification files that should no longer exist';

    public function handle(IdentityVerificationService $identity): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = $identity->disk();
        $cutoff = now()->subMinutes((int) config('identity.orphan_grace_minutes', 60))->getTimestamp();

        // 1. Decided rows that still have files.
        $stale = IdentityVerification::query()
            ->where('status', '!=', IdentityVerification::STATUS_PENDING)
            ->where(fn ($q) => $q->whereNotNull('id_image_path')->orWhereNotNull('selfie_path'))
            ->get();

        if (! $dryRun) {
            foreach ($stale as $row) {
                DB::transaction(fn () => $identity->purgeFiles($row));
            }
        }

        // 2. Unreferenced verification files.
        $keep = IdentityVerification::query()
            ->pending()
            ->get(['id_image_path', 'selfie_path'])
            ->flatMap(fn ($row) => [$row->id_image_path, $row->selfie_path])
            ->filter()
            ->flip();

        $orphanVerificationFiles = $this->orphans(
            $disk,
            config('identity.directories.verifications', 'identity-verifications'),
            $keep->all(),
            $cutoff
        );

        // 3. Unreferenced profile photos.
        $photos = DB::table('users')->whereNotNull('profile_photo_path')->pluck('profile_photo_path')->flip();

        $orphanPhotos = $this->orphans(
            $disk,
            config('identity.directories.profile_photos', 'profile-photos'),
            $photos->all(),
            $cutoff
        );

        if (! $dryRun) {
            $disk->delete([...$orphanVerificationFiles, ...$orphanPhotos]);
        }

        $this->table(['Check', $dryRun ? 'Would delete' : 'Deleted'], [
            ['Decided requests that still had files', $stale->count()],
            ['Unreferenced ID/selfie files', count($orphanVerificationFiles)],
            ['Unreferenced profile photos', count($orphanPhotos)],
        ]);

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function orphans($disk, string $directory, array $keep, int $cutoff): array
    {
        $orphans = [];

        foreach ($disk->allFiles($directory) as $path) {
            if (isset($keep[$path])) {
                continue;
            }

            if ($disk->lastModified($path) > $cutoff) {
                continue;
            }

            $orphans[] = $path;
        }

        return $orphans;
    }
}
