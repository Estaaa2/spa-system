<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff identity verification, part 1.
 *
 * users.profile_photo_path       private-disk path of the profile photo (never a URL)
 * users.profile_photo_updated_at cache-busting version for the photo route
 * users.identity_verified_at     set when an admin approves, cleared when the photo
 *                                or legal name changes or consent is withdrawn; the
 *                                cheap "is verified now" flag for assignment queries
 * staff.performs_treatments      owner-set flag: this staff member can be assigned
 *                                treatments even without the therapist role
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'profile_photo_path')) {
                $table->string('profile_photo_path')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('users', 'profile_photo_updated_at')) {
                $table->timestamp('profile_photo_updated_at')->nullable()->after('profile_photo_path');
            }

            if (! Schema::hasColumn('users', 'identity_verified_at')) {
                $table->timestamp('identity_verified_at')->nullable()->after('profile_photo_updated_at');
                $table->index('identity_verified_at');
            }
        });

        Schema::table('staff', function (Blueprint $table) {
            if (! Schema::hasColumn('staff', 'performs_treatments')) {
                $table->boolean('performs_treatments')->default(false)->after('employment_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'identity_verified_at')) {
                $table->dropIndex(['identity_verified_at']);
            }

            $columns = array_values(array_filter(
                ['profile_photo_path', 'profile_photo_updated_at', 'identity_verified_at'],
                fn (string $column) => Schema::hasColumn('users', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'performs_treatments')) {
                $table->dropColumn('performs_treatments');
            }
        });
    }
};
