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
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        /**
         * PERMISSIONS (module-based)
         * Keep names consistent so route middleware is easy to read.
         */
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

            // Staff (employees)
            'view staff',
            'manage staff',
            'create staff',
            'edit staff',
            'delete staff',

            // Services / Treatments / Packages
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

            // System administration
            'manage users',
            'manage roles',
            'manage settings',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        /**
         * ROLES
         */
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $owner = Role::firstOrCreate(['name' => 'owner']);
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $therapist = Role::firstOrCreate(['name' => 'therapist']);
        $receptionist = Role::firstOrCreate(['name' => 'receptionist']);

        /**
         * ASSIGN PERMISSIONS TO ROLES
         */

        // ADMIN: System-level, no spa/branch assumptions, can manage access
        $admin->syncPermissions([
            'view admin dashboard',
            'manage users',
            'manage roles',
            'manage settings',
            // optional: allow admin to view insights (if you want)
            'view reports',
            'view decision support',
        ]);

        // OWNER: full business control (typical)
        $owner->syncPermissions([
            'view owner dashboard',

            'create booking',
            'view appointments',
            'edit appointments',
            'delete appointments',

            'view schedule',
            'manage schedule',

            'view staff',
            'create staff',
            'edit staff',
            'delete staff',

            'view branches',
            'create branches',
            'edit branches',
            'delete branches',

            'view services',
            'create treatments',
            'edit treatments',
            'delete treatments',
            'create packages',
            'edit packages',
            'delete packages',

            'view reports',
            'view decision support',
        ]);


        // MANAGER: operations + management, but no delete maybe (adjust if you want)
        $manager->syncPermissions([
            'view owner dashboard',

            'create booking',
            'view appointments',
            'edit appointments',
            // 'delete appointments', // optional (usually owner-only)

            'view schedule',
            'manage schedule',

            'view staff availability',
            'manage staff availability',

            'view branches',
            // 'manage branches', // optional

            'view staff',
            'manage staff',

            'view services',
            'manage services',

            'view reports',
            'view decision support',
        ]);

        // THERAPIST: mostly schedule + appointments viewing
        $therapist->syncPermissions([
            'view owner dashboard',      // or remove if you want therapist dashboard later
            'view schedule',
            'view appointments',
            // optional if therapist can update status of own appointments:
            // 'edit appointments',
        ]);

        // RECEPTIONIST: booking + schedule + appointments (but no management)
        $receptionist->syncPermissions([
            'view owner dashboard',

            'create booking',
            'view appointments',
            'edit appointments',
            // 'delete appointments', // optional

            'view schedule',

            'view branches', // optional: if they need branch list
            'view staff',    // optional: if they need staff list for booking
        ]);
    }
}
