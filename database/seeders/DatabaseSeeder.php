<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ALL SEEDERS USED WHEN REFRESHINGG (Seed roles and permissions first)
        $this->call(RolePermissionSeeder::class);
        $this->call(AdminSeeder::class);
    }
}
