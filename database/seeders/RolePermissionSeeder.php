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
            // ── Dashboard ─────────────────────────────────────────────────────
            // view owner dashboard: kept in DB for backwards compat with any existing
            // @can checks, but is NOT assigned to any role. Superseded by
            // view business dashboard.
            'view owner dashboard',
            'view admin dashboard',
            'view business dashboard',     // all business roles

            // Dashboard widgets (controls what each role sees on the dashboard)
            'view dashboard kpis',             // today/ongoing/pending/reserved stat cards
            'view dashboard revenue',          // collected revenue, online vs walk-in, top service
            'view dashboard timeline',         // full branch appointment timeline
            'view dashboard therapist status', // therapist workload panel
            'view dashboard alerts',           // late check-ins, cancellations, overloaded warnings
            'view dashboard booking button',   // New Booking shortcut in dashboard header
            'view dashboard my today',         // therapist's own personal schedule widget

            // ── Appointments ──────────────────────────────────────────────────
            'book appointments',
            'view appointments',
            'edit appointments',
            'delete appointments',

            // ── Schedule ──────────────────────────────────────────────────────
            'view schedule',

            // ── Attendance & Leave ────────────────────────────────────────────
            'view attendance',
            'edit attendance',
            'view leave requests',
            'create leave requests',
            'edit leave requests',
            'delete leave requests',

            // ── Branches ──────────────────────────────────────────────────────
            'view branches',
            'create branches',
            'edit branches',
            'delete branches',

            // ── Staff ─────────────────────────────────────────────────────────
            'view staff',
            'create staff',
            'edit staff',
            'delete staff',

            // ── Services ──────────────────────────────────────────────────────
            'view services',
            'create treatments',
            'edit treatments',
            'delete treatments',
            'create packages',
            'edit packages',
            'delete packages',

            // ── Insights & Reports ────────────────────────────────────────────
            'view reports',
            'export reports',
            'view decision support',

            // ── Inventory ─────────────────────────────────────────────────────
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

            // ── Staff-side Settings ───────────────────────────────────────────
            'edit own profile',
            'view spa profile',
            'edit spa profile',

            // ── Admin-side System Management ──────────────────────────────────
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

            // ── HR (Workforce Suite) ──────────────────────────────────────────
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

            // ── Finance (Finance Suite) ───────────────────────────────────────
            'view revenue',
            'view billing',
            'create billing',
            'edit billing',
            'delete billing',
            'view finance inventory',
            'edit finance inventory',
        ];

        // Optional: uncomment to clean up stale permissions after all code has been updated
        Permission::whereNotIn('name', $permissions)->delete();

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // ── Roles ─────────────────────────────────────────────────────────────
        $admin        = Role::firstOrCreate(['name' => 'admin']);
        $owner        = Role::firstOrCreate(['name' => 'owner']);
        $manager      = Role::firstOrCreate(['name' => 'manager']);
        $therapist    = Role::firstOrCreate(['name' => 'therapist']);
        $receptionist = Role::firstOrCreate(['name' => 'receptionist']);
        $customer     = Role::firstOrCreate(['name' => 'customer']);
        $hr           = Role::firstOrCreate(['name' => 'hr']);
        $finance      = Role::firstOrCreate(['name' => 'finance']);

        // ── Admin ─────────────────────────────────────────────────────────────
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

        // ── Owner ─────────────────────────────────────────────────────────────
        // Full visibility across all dashboard widgets, operations, HR, and finance.
        // Note: view owner dashboard is intentionally excluded — view business dashboard
        // is the correct permission going forward.
        $owner->syncPermissions([
            'view business dashboard',
            'view dashboard kpis',
            'view dashboard revenue',
            'view dashboard timeline',
            'view dashboard therapist status',
            'view dashboard alerts',
            'view dashboard booking button',

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
            'view product inventory',
            'create product inventory',
            'edit product inventory',
            'delete product inventory',
            'view product logs',

            'edit own profile',
            'view spa profile',
            'edit spa profile',

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

            'view revenue',
            'view billing',
            'create billing',
            'edit billing',
            'delete billing',
            'view finance inventory',
            'edit finance inventory',
        ]);

        // ── Manager ───────────────────────────────────────────────────────────
        // Operational + financial visibility. No branch/spa settings,
        // no delete permissions on staff or services.
        $manager->syncPermissions([
            'view business dashboard',
            'view dashboard kpis',
            'view dashboard revenue',
            'view dashboard timeline',
            'view dashboard therapist status',
            'view dashboard alerts',
            'view dashboard booking button',

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
            'view product inventory',
            'edit product inventory',
            'view product logs',

            'edit own profile',
            'view spa profile',

            'view deployments',
        ]);

        // ── Therapist ─────────────────────────────────────────────────────────
        // Personal-only dashboard view: their own schedule widget only.
        // No branch-wide data, no revenue, no booking creation.
        $therapist->syncPermissions([
            'view business dashboard',
            'view dashboard my today',

            'view appointments',
            'view schedule',

            'view attendance',
            'view leave requests',
            'create leave requests',

            'edit own profile',
        ]);

        // ── Receptionist ──────────────────────────────────────────────────────
        // Operational focus: can book and see the timeline and alerts.
        // No revenue data, no financial or HR information.
        $receptionist->syncPermissions([
            'view business dashboard',
            'view dashboard kpis',
            'view dashboard timeline',
            'view dashboard alerts',
            'view dashboard booking button',

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

        // ── HR ────────────────────────────────────────────────────────────────
        // People-focused. KPI cards for context only; no timeline, revenue, or alerts.
        $hr->syncPermissions([
            'view business dashboard',
            'view dashboard kpis',

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

        // ── Finance ───────────────────────────────────────────────────────────
        // Financial focus: KPIs + revenue data. No operational widgets,
        // no HR, no appointment management.
        $finance->syncPermissions([
            'view business dashboard',
            'view dashboard kpis',
            'view dashboard revenue',

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

        // ── Customer ──────────────────────────────────────────────────────────
        $customer->syncPermissions([]);
    }
}