<?php

use Database\Seeders\RolePermissionSeeder;

it('returns a successful response', function () {
    // The landing page reads roles (e.g. therapists to list), so they must exist.
    $this->seed(RolePermissionSeeder::class);

    $this->get('/')->assertStatus(200);
});
