<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku')->nullable()->after('spa_id');
            $table->string('barcode')->nullable()->after('sku');
            $table->text('description')->nullable()->after('brand');
            $table->string('category')->nullable()->after('description');
            $table->string('inventory_type')->default('backbar')->after('category');
            $table->string('purchase_unit', 30)->nullable()->after('unit');
            $table->string('usage_unit', 30)->nullable()->after('purchase_unit');
            $table->decimal('conversion_factor', 14, 3)->default(1)->after('usage_unit');
            $table->decimal('retail_price', 12, 2)->nullable()->after('conversion_factor');
            $table->decimal('acquisition_cost', 12, 2)->nullable()->after('retail_price');
            $table->boolean('is_active')->default(true)->after('acquisition_cost');

            $table->unique(['spa_id', 'sku'], 'products_spa_sku_unique');
            $table->index(['spa_id', 'category']);
            $table->index(['spa_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_spa_sku_unique');
            $table->dropIndex(['spa_id', 'category']);
            $table->dropIndex(['spa_id', 'is_active']);

            $table->dropColumn([
                'sku',
                'barcode',
                'description',
                'category',
                'inventory_type',
                'purchase_unit',
                'usage_unit',
                'conversion_factor',
                'retail_price',
                'acquisition_cost',
                'is_active',
            ]);
        });
    }
};
