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
        // Clear cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define permissions
        Permission::create(['name' => 'view posts']);
        Permission::create(['name' => 'create posts']);
        Permission::create(['name' => 'edit posts']);
        Permission::create(['name' => 'delete posts']);
        Permission::create(['name' => 'manage spa']);
        Permission::create(['name' => 'manage branches']);
        Permission::create(['name' => 'manage staff']);
        Permission::create(['name' => 'view dashboard']);
        Permission::create(['name' => 'manage bookings']);

        // Create roles
        $owner = Role::create(['name' => 'owner']);
        $manager = Role::create(['name' => 'manager']);
        $receptionist = Role::create(['name' => 'receptionist']);
        $admin = Role::create(['name' => 'admin']);
        $editor = Role::create(['name' => 'editor']);
        $viewer = Role::create(['name' => 'viewer']);

        // Assign all permissions to owner
        $owner->givePermissionTo(Permission::all());

        // Manager permissions
        $manager->givePermissionTo([
            'view dashboard',
            'manage branches',
            'manage staff',
            'manage bookings',
            'view posts',
        ]);

        // Receptionist permissions
        $receptionist->givePermissionTo([
            'view dashboard',
            'manage bookings',
            'view posts',
        ]);

        // Admin permissions
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

        // Editor permissions
        $editor->givePermissionTo(['view posts', 'create posts', 'edit posts']);

        // Viewer permissions
        $viewer->givePermissionTo(['view posts']);
    }
}
