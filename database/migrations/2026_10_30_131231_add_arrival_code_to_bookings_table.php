<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arrival code for online home-service bookings (staff verification D7, Q41/Q42).
 *
 * arrival_code           4 digits, encrypted (the customer must be able to see it
 *                        again, so it can't be a hash). Never shown to staff.
 * arrival_attempts       wrong codes entered; 5 locks the code
 * arrival_locked_at      set on the 5th wrong code; then only the branch can confirm
 * arrival_confirmed_at   when the therapist (code) or the branch (manual) confirmed
 * arrival_confirmed_by   who confirmed
 * arrival_method         code | manual
 * arrival_manual_reason  why the branch confirmed without the code
 *
 * Recording arrival does not change the booking status (decision Q6): status
 * stays clock-driven (reserved → pending → ongoing → completed).
 * Timestamps are nullable on purpose (MariaDB explicit_defaults_for_timestamp=OFF).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'arrival_code')) {
                $table->text('arrival_code')->nullable()->after('end_time');
                $table->unsignedTinyInteger('arrival_attempts')->default(0)->after('arrival_code');
                $table->timestamp('arrival_locked_at')->nullable()->after('arrival_attempts');
                $table->timestamp('arrival_confirmed_at')->nullable()->after('arrival_locked_at');
                $table->foreignId('arrival_confirmed_by')->nullable()->after('arrival_confirmed_at')
                    ->constrained('users')->nullOnDelete();
                $table->string('arrival_method', 10)->nullable()->after('arrival_confirmed_by');
                $table->text('arrival_manual_reason')->nullable()->after('arrival_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'arrival_confirmed_by')) {
                $table->dropConstrainedForeignId('arrival_confirmed_by');
            }

            $columns = array_values(array_filter(
                ['arrival_code', 'arrival_attempts', 'arrival_locked_at', 'arrival_confirmed_at', 'arrival_method', 'arrival_manual_reason'],
                fn (string $column) => Schema::hasColumn('bookings', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
