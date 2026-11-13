<?php

/*
|--------------------------------------------------------------------------
| Demo data (DemoDataSeeder / DemoDataResetSeeder / DemoDataRemoveSeeder)
|--------------------------------------------------------------------------
|
| Read through config (not env() in the seeder) so it still works after
| `php artisan optimize` caches the config on the server. After changing
| these values in .env, run `php artisan config:clear` (or optimize again).
|
| DEMO_SEED_PASSWORD           Shared password of every demo account. Never
|                              commit it: the repository is public.
| DEMO_SEED_ALLOW_PRODUCTION   Must be "true" for the demo seeders to run when
|                              APP_ENV=production. Turn it off again after.
|
*/

return [
    'password' => env('DEMO_SEED_PASSWORD'),

    'allow_production' => (bool) env('DEMO_SEED_ALLOW_PRODUCTION', false),
];
