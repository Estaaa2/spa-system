<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Remove the duplicate phone column
        Schema::table('staff', function (Blueprint $table) {
            // Drop the duplicate column (the second one)
            $table->dropColumn('phone');

            // Re-add it properly
            $table->string('phone')->unique()->after('name');
        });
    }

    public function down()
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('phone');
            $table->string('phone')->nullable();
        });
    }
};
