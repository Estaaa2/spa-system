<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates `manage refunds` and gives it to the platform admin role only: the
 * money sits in the Levictas PayMongo account, so Levictas sends refunds, not
 * the spa (customer cancellation, Nov 2026). Data only, idempotent; same
 * upgrade path as add_verify_staff_identity_permission.
 */
return new class extends Migration
{
    private const PERMISSION = 'manage refunds';

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);

        $admin = Role::where('name', 'admin')->where('guard_name', 'web')->first();

        if ($admin && ! $admin->hasPermissionTo($permission)) {
            $admin->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::where('name', self::PERMISSION)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
