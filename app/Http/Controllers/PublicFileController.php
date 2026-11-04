<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unauthenticated file routes for images that are public by design
 * (branch photos, treatment and package images, review photos).
 *
 * Replaces the closures in routes/web.php and routes/api.php that served the
 * whole public disk with a blocklist. This is an allowlist: a file is served
 * only when its first folder is listed below. Everything sensitive (IDs,
 * selfies, profile photos, résumés, spa documents) lives on the private disk
 * and is never reachable here.
 *
 * Note: on a server where public/storage is a symlink, nginx/Apache serve
 * /storage/* directly and these routes only see requests for files that do not
 * exist there. The allowlist matters for /api/image/* and for the
 * /storage/branch_profiles/* fallback path.
 */
class PublicFileController extends Controller
{
    /** Top-level folders on the public disk that anyone may read. */
    public const PUBLIC_DIRECTORIES = [
        'branch_profiles',
        'treatments',
        'packages',
        'rating_photos',
    ];

    /** GET /api/image/{path}  (Flutter) */
    public function image(string $path): Response
    {
        $resolved = $this->resolve($path);

        abort_unless($resolved, 404);

        return $this->send($resolved);
    }

    /**
     * GET /storage/branch_profiles/{filename}
     *
     * Kept from the old closure: tries branch_profiles/{filename} first, then
     * {filename} as a full path (some stored paths already include the folder).
     * Both must pass the allowlist.
     */
    public function branchProfile(string $filename): Response
    {
        $resolved = $this->resolve('branch_profiles/'.$filename) ?? $this->resolve($filename);

        abort_unless($resolved, 404);

        return $this->send($resolved);
    }

    /** Absolute path of an allowed, existing file, or null. */
    private function resolve(string $path): ?string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            return null;
        }

        $firstFolder = explode('/', $path, 2)[0];

        if (! in_array($firstFolder, self::PUBLIC_DIRECTORIES, true) || ! str_contains($path, '/')) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $root = realpath($disk->path(''));
        $full = realpath($disk->path($path));

        // Symlinks inside the disk must not lead outside it.
        if ($root === false || $full === false || ! str_starts_with($full, $root.DIRECTORY_SEPARATOR) || ! is_file($full)) {
            return null;
        }

        return $full;
    }

    private function send(string $fullPath): Response
    {
        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';

        // Only images are public by design; anything else in these folders is refused.
        abort_unless(str_starts_with($mime, 'image/'), 404);

        return response()->file($fullPath, [
            'Content-Type'                 => $mime,
            'Cache-Control'                => 'public, max-age=3600',
            'X-Content-Type-Options'       => 'nosniff',
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Origin, Content-Type, Accept, Authorization',
        ]);
    }
}
