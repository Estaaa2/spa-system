<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_verification_otp_hash')->nullable()->after('email_verified_at');
            $table->timestamp('email_verification_otp_expires_at')->nullable()->after('email_verification_otp_hash');
            $table->timestamp('email_verification_otp_sent_at')->nullable()->after('email_verification_otp_expires_at');
            $table->unsignedTinyInteger('email_verification_otp_attempts')->default(0)->after('email_verification_otp_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'email_verification_otp_hash',
                'email_verification_otp_expires_at',
                'email_verification_otp_sent_at',
                'email_verification_otp_attempts',
            ]);
        });
    }
};
