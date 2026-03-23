<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Dashboard
            'view owner dashboard',
            'view admin dashboard',

            // Bookings / Appointments / Schedule
            'create booking',
            'view appointments',
            'edit appointments',
            'delete appointments',
            'view schedule',
            'manage schedule',

            // Staff availability
            'view staff availability',
            'manage staff availability',

            // Branches
            'view branches',
            'manage branches',
            'create branches',
            'edit branches',
            'delete branches',

            // Staff
            'view staff',
            'manage staff',
            'create staff',
            'edit staff',
            'delete staff',

            // Services
            'view services',
            'manage services',
            'create treatments',
            'edit treatments',
            'delete treatments',
            'create packages',
            'edit packages',
            'delete packages',

            // Reports / Decision support
            'view reports',
            'view decision support',

            // Inventory
            'view inventory',
            'view inventory logs',
            'manage inventory',

            // System administration
            'manage spas',
            'manage users',
            'manage roles',
            'manage settings',

            //  HR permissions
            'view hr dashboard',
            'manage hiring',
            'view hiring',
            'manage applications',
            'view applications',
            'manage interviews',
            'view interviews',
            'manage attendance',
            'view attendance',
            'manage payroll',
            'view payroll',

            //  Finance permissions
            'view finance dashboard',
            'view revenue',
            'manage revenue',
            'view billing',
            'manage billing',
            'view finance inventory',
            'manage finance inventory',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Roles
        $admin        = Role::firstOrCreate(['name' => 'admin']);
        $owner        = Role::firstOrCreate(['name' => 'owner']);
        $manager      = Role::firstOrCreate(['name' => 'manager']);
        $therapist    = Role::firstOrCreate(['name' => 'therapist']);
        $receptionist = Role::firstOrCreate(['name' => 'receptionist']);
        $customer     = Role::firstOrCreate(['name' => 'customer']);
        $hr           = Role::firstOrCreate(['name' => 'hr']);           // ✅ NEW
        $finance      = Role::firstOrCreate(['name' => 'finance']);      // ✅ NEW

        // Admin
        $admin->syncPermissions([
            'view admin dashboard',
            'manage spas', 'manage users', 'manage roles', 'manage settings',
            'view reports', 'view decision support',
            'manage inventory',
        ]);

        // Owner
        $owner->syncPermissions([
            'view owner dashboard',
            'create booking', 'view appointments', 'edit appointments', 'delete appointments',
            'view schedule', 'manage schedule',
            'view inventory', 'view inventory logs', 'manage inventory',
            'view staff availability', 'manage staff availability',
            'view staff', 'manage staff', 'create staff', 'edit staff', 'delete staff',
            'view branches', 'manage branches', 'create branches', 'edit branches', 'delete branches',
            'view services', 'manage services',
            'create treatments', 'edit treatments', 'delete treatments',
            'create packages', 'edit packages', 'delete packages',
            'view reports', 'view decision support',

            // HR & Finance visibility for owner
            'view hr dashboard', 'view finance dashboard',
            'view hiring', 'view applications', 'view interviews',
            'view attendance', 'view payroll',
            'view revenue', 'view billing', 'view finance inventory',
        ]);

        // Manager
        $manager->syncPermissions([
            'create booking', 'view appointments', 'edit appointments',
            'view schedule', 'manage schedule',
            'view staff availability', 'manage staff availability',
            'view branches',
            'view inventory', 'view inventory logs', 'manage inventory',
            'view staff', 'manage staff',
            'view services', 'manage services',
            'view reports', 'view decision support',
        ]);

        // Therapist
        $therapist->syncPermissions([
            'view schedule', 'view appointments',
        ]);

        // Receptionist
        $receptionist->syncPermissions([
            'create booking', 'view appointments', 'edit appointments',
            'view schedule', 'view branches', 'view staff',
        ]);

        // ✅ HR Role
        $hr->syncPermissions([
            'view hr dashboard',
            'view hiring',    'manage hiring',
            'view applications', 'manage applications',
            'view interviews',   'manage interviews',
            'view staff',        'manage staff',
            'view attendance',   'manage attendance',
            'view payroll',      'manage payroll',
            'view schedule',
        ]);

        // ✅ Finance Role
        $finance->syncPermissions([
            'view finance dashboard',
            'view revenue',          'manage revenue',
            'view billing',          'manage billing',
            'view finance inventory','manage finance inventory',
            'view reports',
            'view decision support',
        ]);

        $customer->syncPermissions([]);
    }
}
