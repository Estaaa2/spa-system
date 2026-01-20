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

        // System-level permissions
        $systemPermissions = [
            'manage spas',
            'manage all branches',
            'view system dashboard',
        ];

        // Spa-level permissions
        $spaPermissions = [
            'view spa dashboard',
            'manage spa',
            'manage branches',
            'manage staff',
            'manage bookings',
        ];

        // Customer permissions
        $customerPermissions = [
            'book services',
        ];

        foreach (array_merge($systemPermissions, $spaPermissions, $customerPermissions) as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Roles
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $owner = Role::firstOrCreate(['name' => 'owner']);
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $receptionist = Role::firstOrCreate(['name' => 'receptionist']);
        $customer = Role::firstOrCreate(['name' => 'customer']);

        // Assign permissions
        $admin->syncPermissions(Permission::all());

        $owner->syncPermissions([
            'view spa dashboard',
            'manage spa',
            'manage branches',
            'manage staff',
            'manage bookings',
        ]);

        $manager->syncPermissions([
            'view spa dashboard',
            'manage branches',
            'manage staff',
            'manage bookings',
        ]);

        $receptionist->syncPermissions([
            'view spa dashboard',
            'manage bookings',
        ]);

        $customer->syncPermissions([
            'book services',
        ]);
    }
}