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

            // Appointments
            'book appointments',
            'view appointments',
            'edit appointments',
            'delete appointments',

            // Schedule
            'view schedule',

            // Attendance & Leave
            'view attendance',
            'edit attendance',
            'view leave requests',
            'create leave requests',
            'edit leave requests',
            'delete leave requests',

            // Branches
            'view branches',
            'create branches',
            'edit branches',
            'delete branches',

            // Staff
            'view staff',
            'create staff',
            'edit staff',
            'delete staff',

            // Services
            'view services',
            'create treatments',
            'edit treatments',
            'delete treatments',
            'create packages',
            'edit packages',
            'delete packages',

            // Reports / Decision Support
            'view reports',
            'export reports',
            'view decision support',

            // Inventory
            'view inventory',
            'view inventory logs',
            'create inventory items',
            'edit inventory items',
            'delete inventory items',
            'view product inventory',
            'create product inventory',
            'edit product inventory',
            'delete product inventory',
            'view product logs',

            // Staff-side Settings
            'edit own profile',
            'view spa profile',
            'edit spa profile',

            // Admin-side System Management
            'view registered users',
            'edit registered users',
            'delete registered users',
            'view registered spas',
            'edit registered spas',
            'verify registered spas',
            'change spa subscriptions',
            'view system roles',
            'edit system roles',
            'edit admin profile',
            'manage system settings',

            // HR
            'view hr dashboard',
            'view hiring',
            'create hiring',
            'edit hiring',
            'delete hiring',
            'view applications',
            'edit applications',
            'delete applications',
            'view interviews',
            'create interviews',
            'edit interviews',
            'delete interviews',
            'view payroll',
            'edit payroll',
            'view deployments',
            'create deployments',
            'approve deployments',
            'delete deployments',

            // Finance
            'view finance dashboard',
            'view revenue',
            'view billing',
            'create billing',
            'edit billing',
            'delete billing',
            'view finance inventory',
            'edit finance inventory',
        ];

        /**
         * Optional:
         * Uncomment this only after you have already updated your Blade/controllers/routes
         * to the NEW permission names. Otherwise old pages using old permission names will break.
         */
        // Permission::whereNotIn('name', $permissions)->delete();

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
        $hr           = Role::firstOrCreate(['name' => 'hr']);
        $finance      = Role::firstOrCreate(['name' => 'finance']);

        // Admin
        $admin->syncPermissions([
            'view admin dashboard',

            'view registered users',
            'edit registered users',
            'delete registered users',

            'view registered spas',
            'edit registered spas',
            'verify registered spas',
            'change spa subscriptions',

            'view system roles',
            'edit system roles',

            'edit admin profile',
            'manage system settings',
        ]);

        // Owner
        $owner->syncPermissions([
            'view owner dashboard',

            'book appointments',
            'view appointments',
            'edit appointments',
            'delete appointments',

            'view schedule',

            'view attendance',
            'edit attendance',
            'view leave requests',
            'create leave requests',
            'edit leave requests',
            'delete leave requests',

            'view branches',
            'create branches',
            'edit branches',
            'delete branches',

            'view staff',
            'create staff',
            'edit staff',
            'delete staff',

            'view services',
            'create treatments',
            'edit treatments',
            'delete treatments',
            'create packages',
            'edit packages',
            'delete packages',

            'view reports',
            'export reports',
            'view decision support',

            'view inventory',
            'view inventory logs',
            'create inventory items',
            'edit inventory items',
            'delete inventory items',

            'edit own profile',
            'view spa profile',
            'edit spa profile',

            // HR visibility / control
            'view hr dashboard',
            'view hiring',
            'create hiring',
            'edit hiring',
            'delete hiring',
            'view applications',
            'edit applications',
            'delete applications',
            'view interviews',
            'create interviews',
            'edit interviews',
            'delete interviews',
            'view payroll',
            'edit payroll',
            'view deployments',
            'create deployments',
            'approve deployments',
            'delete deployments',

            // Finance visibility / control
            'view finance dashboard',
            'view revenue',
            'view billing',
            'create billing',
            'edit billing',
            'delete billing',
            'view finance inventory',
            'edit finance inventory',
        ]);

        // Manager
        $manager->syncPermissions([
            'book appointments',
            'view appointments',
            'edit appointments',

            'view schedule',

            'view attendance',
            'edit attendance',
            'view leave requests',
            'create leave requests',
            'edit leave requests',

            'view branches',

            'view staff',
            'create staff',
            'edit staff',

            'view services',
            'create treatments',
            'edit treatments',
            'create packages',
            'edit packages',

            'view reports',
            'view decision support',

            'view inventory',
            'view inventory logs',
            'create inventory items',
            'edit inventory items',

            'edit own profile',
            'view spa profile',

            'view deployments',
        ]);

        // Therapist
        $therapist->syncPermissions([
            'view appointments',
            'view schedule',

            'view attendance',
            'view leave requests',
            'create leave requests',

            'edit own profile',
        ]);

        // Receptionist
        $receptionist->syncPermissions([
            'book appointments',
            'view appointments',
            'edit appointments',

            'view schedule',
            'view branches',
            'view staff',
            'view services',

            'view leave requests',
            'create leave requests',

            'edit own profile',
        ]);

        // HR
        $hr->syncPermissions([
            'view hr dashboard',

            'view staff',
            'create staff',
            'edit staff',
            'delete staff',

            'view branches',
            'view schedule',

            'view attendance',
            'edit attendance',
            'view leave requests',
            'create leave requests',
            'edit leave requests',
            'delete leave requests',

            'view hiring',
            'create hiring',
            'edit hiring',
            'delete hiring',

            'view applications',
            'edit applications',
            'delete applications',

            'view interviews',
            'create interviews',
            'edit interviews',
            'delete interviews',

            'view deployments',
            'create deployments',
            'delete deployments',

            'view payroll',
            'edit payroll',

            'edit own profile',
        ]);

        // Finance
        $finance->syncPermissions([
            'view finance dashboard',

            'view revenue',

            'view billing',
            'create billing',
            'edit billing',
            'delete billing',

            'view finance inventory',
            'edit finance inventory',

            'view reports',
            'view decision support',

            'edit own profile',
        ]);

        // Customer
        $customer->syncPermissions([]);
    }
}
