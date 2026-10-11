<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->string('image_path_2')->nullable()->after('image_path');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->string('image_path_2')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->dropColumn('image_path_2');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('image_path_2');
        });
    }
};
