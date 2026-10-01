<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_consumptions', function (Blueprint $table) {
            $table->foreignId('package_id')
                ->nullable()
                ->after('treatment_id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('booking_consumption_items', function (Blueprint $table) {
            $table->foreignId('treatment_id')
                ->nullable()
                ->after('product_batch_id')
                ->constrained()
                ->nullOnDelete();

            $table->string('treatment_name')
                ->nullable()
                ->after('treatment_id');
        });
    }

    public function down(): void
    {
        Schema::table('booking_consumption_items', function (Blueprint $table) {
            $table->dropForeign(['treatment_id']);
            $table->dropColumn([
                'treatment_id',
                'treatment_name',
            ]);
        });

        Schema::table('booking_consumptions', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropColumn('package_id');
        });
    }
};
