<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $this->moveFiles('public', 'local');
    }

    public function down(): void
    {
        $this->moveFiles('local', 'public');
    }

    /**
     * Moves every verification document file (current and history)
     * from one disk to the other. Safe to run more than once.
     */
    private function moveFiles(string $from, string $to): void
    {
        $paths = DB::table('spa_verification_documents')
            ->pluck('file_path')
            ->merge(
                DB::table('spa_verification_document_histories')
                    ->pluck('file_path')
            )
            ->filter()
            ->unique();

        foreach ($paths as $path) {
            if (! Storage::disk($from)->exists($path)) {
                continue;
            }

            if (! Storage::disk($to)->exists($path)) {
                $stream = Storage::disk($from)->readStream($path);

                $copied = Storage::disk($to)->writeStream($path, $stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }

                // Keep the original if the copy did not succeed.
                if (! $copied) {
                    continue;
                }
            }

            Storage::disk($from)->delete($path);
        }
    }
};