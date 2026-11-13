<?php

namespace Database\Seeders\Demo;

use Illuminate\Console\Command;

/**
 * The checks every demo seeder runs first.
 *
 * Unlike the *Test* seeders, the demo seeders MAY run in production — but only
 * when DEMO_SEED_ALLOW_PRODUCTION=true is set, so nobody runs them there by
 * accident. Seeding also needs DEMO_SEED_PASSWORD, because the repository is
 * public and a password written in this code would be readable by anyone.
 */
final class DemoGuard
{
    public static function environmentAllowed(?Command $command, string $seeder): bool
    {
        if (app()->environment('production') && ! config('demo.allow_production')) {
            $command?->error("{$seeder}: this is the production server. Set DEMO_SEED_ALLOW_PRODUCTION=true in .env, run `php artisan config:clear`, then try again.");

            return false;
        }

        return true;
    }

    /** The shared demo password, or null (with an error shown) when it is missing or too weak. */
    public static function password(?Command $command): ?string
    {
        $password = (string) config('demo.password');

        if (mb_strlen($password) < 10) {
            $command?->error('Set DEMO_SEED_PASSWORD in .env (at least 10 characters, the password your team agreed on), run `php artisan config:clear`, then try again.');

            return null;
        }

        return $password;
    }
}
