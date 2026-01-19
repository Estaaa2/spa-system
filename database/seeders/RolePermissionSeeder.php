<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Permissions
        Permission::firstOrCreate(['name' => 'view posts']);
        Permission::firstOrCreate(['name' => 'create posts']);
        Permission::firstOrCreate(['name' => 'edit posts']);
        Permission::firstOrCreate(['name' => 'delete posts']);
        Permission::firstOrCreate(['name' => 'reserve bookings']);

        // Roles
        $admin  = Role::firstOrCreate(['name' => 'admin']);
        $editor = Role::firstOrCreate(['name' => 'editor']);
        $viewer = Role::firstOrCreate(['name' => 'viewer']);
        $user   = Role::firstOrCreate(['name' => 'user']);

        // Assign permissions
        $admin->givePermissionTo(Permission::all());
        $editor->givePermissionTo(['view posts', 'create posts', 'edit posts']);
        $viewer->givePermissionTo(['view posts']);
    }
}
