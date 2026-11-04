<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Moves sensitive files off the public disk (storage/app/public, reachable by
 * URL through the public/storage symlink) onto the private "local" disk
 * (storage/app/private).
 *
 *   php artisan storage:privatize --dry-run   # report only
 *   php artisan storage:privatize
 *
 * Folders:
 * - resumes/                     applicants.resume_path
 * - spa-verification-documents/  spa_verification_documents(+histories).file_path
 *
 * A file still pointed to by a row keeps the same relative path, so no database
 * row changes. A file no row points to is moved to orphaned/<same path> on the
 * private disk and counted, so nothing is lost and nothing stays public.
 *
 * Safe to run again: a file already on the private disk with the same size only
 * has its public copy removed. Works the same on Windows (XAMPP) and Linux; it
 * uses Laravel's Storage only, no shell commands.
 *
 * On the server, run it as the web server user so the new folders stay writable
 * by PHP, e.g.  sudo -u www-data php artisan storage:privatize
 */
class PrivatizeStoredFiles extends Command
{
    protected $signature = 'storage:privatize {--dry-run : Only report what would move}';

    protected $description = 'Move résumés and spa verification documents from the public disk to the private disk';

    /** folder => list of [table, column] that reference files in it */
    private const FOLDERS = [
        'resumes' => [
            ['applicants', 'resume_path'],
        ],
        'spa-verification-documents' => [
            ['spa_verification_documents', 'file_path'],
            ['spa_verification_document_histories', 'file_path'],
        ],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $public = Storage::disk('public');
        $private = Storage::disk('local');

        $rows = [];
        $hadErrors = false;

        foreach (self::FOLDERS as $folder => $sources) {
            $referenced = $this->referencedPaths($sources);
            $counts = ['moved' => 0, 'orphaned' => 0, 'already_private' => 0, 'conflict' => 0, 'failed' => 0];

            foreach ($public->allFiles($folder) as $path) {
                $isReferenced = isset($referenced[$path]);
                $target = $isReferenced ? $path : 'orphaned/'.$path;

                $outcome = $this->moveOne($public, $private, $path, $target, $dryRun);

                if ($outcome === 'moved') {
                    $counts[$isReferenced ? 'moved' : 'orphaned']++;
                } else {
                    $counts[$outcome]++;
                }
            }

            $missing = 0;
            foreach (array_keys($referenced) as $path) {
                if (! $private->exists($path) && ! $public->exists($path)) {
                    $missing++;
                }
            }

            $hadErrors = $hadErrors || $counts['conflict'] > 0 || $counts['failed'] > 0;

            $rows[] = [
                $folder,
                $counts['moved'],
                $counts['orphaned'],
                $counts['already_private'],
                $counts['conflict'],
                $counts['failed'],
                $dryRun ? '—' : $missing,
            ];
        }

        $this->table(
            ['Folder', 'Moved (in use)', 'Moved to orphaned/', 'Already private', 'Conflict', 'Failed', 'Rows with no file'],
            $rows
        );

        if ($dryRun) {
            $this->warn('Dry run: nothing was moved. Run without --dry-run to move the files.');
        } else {
            $this->info('Done. Unused files are in storage/app/private/orphaned/ — review and delete them when you are sure.');
        }

        if ($hadErrors) {
            $this->error('Some files were not moved (see Conflict/Failed). Their public copies were left in place.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @return array<string, true> */
    private function referencedPaths(array $sources): array
    {
        $paths = [];

        foreach ($sources as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            // Query builder on purpose: soft-deleted rows still count as references.
            foreach (DB::table($table)->whereNotNull($column)->pluck($column) as $path) {
                $paths[(string) $path] = true;
            }
        }

        return $paths;
    }

    /** @return string moved | already_private | conflict | failed */
    private function moveOne(Filesystem $from, Filesystem $to, string $path, string $target, bool $dryRun): string
    {
        $size = $from->size($path);

        if ($to->exists($target)) {
            if ($to->size($target) !== $size) {
                return 'conflict';
            }

            if (! $dryRun) {
                $from->delete($path);
            }

            return 'already_private';
        }

        if ($dryRun) {
            return 'moved';
        }

        $stream = $from->readStream($path);

        if (! $stream) {
            return 'failed';
        }

        try {
            $written = $to->writeStream($target, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        // Delete the public copy only after the private copy is confirmed.
        if (! $written || ! $to->exists($target) || $to->size($target) !== $size) {
            return 'failed';
        }

        $from->delete($path);

        return 'moved';
    }
}
