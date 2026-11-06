<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Landing footer Phase 2 (Q10-A, Q5-A): record which version of the legal
 * text someone accepted, and when.
 *
 * - users / pending_registrations: Terms accepted at sign-up (copied from
 *   the pending row when the account is created).
 * - online_reservation_payments: Terms accepted at booking checkout.
 * - applicants: consent to the Privacy Policy on the public Apply Now form.
 *
 * Additive only; existing rows stay null (accepted before this was recorded).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'pending_registrations', 'online_reservation_payments'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('terms_version', 20)->nullable();
                $t->timestamp('terms_accepted_at')->nullable();
            });
        }

        Schema::table('applicants', function (Blueprint $t) {
            $t->string('privacy_consent_version', 20)->nullable();
            $t->timestamp('privacy_consent_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['users', 'pending_registrations', 'online_reservation_payments'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['terms_version', 'terms_accepted_at']);
            });
        }

        Schema::table('applicants', function (Blueprint $t) {
            $t->dropColumn(['privacy_consent_version', 'privacy_consent_at']);
        });
    }
};
