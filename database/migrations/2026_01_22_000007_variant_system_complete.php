<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only add columns that don't already exist

        // Check if distributor_price exists
        if (!Schema::hasColumn('product_prices', 'distributor_price')) {
            Schema::table('product_prices', function (Blueprint $table) {
                $table->decimal('distributor_price', 10, 2)->nullable()->after('wholesale_price')
                    ->comment('Distributor wholesale price - 4th pricing tier');
            });
        }

        // Create product_variants table if it doesn't exist
        if (!Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function (Blueprint $table) {
                $table->id();
                $table->string('variant_name')->index();
                $table->json('variant_values')->comment('Array of variant values e.g. ["5", "6", "7", "8"]');
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
                $table->index('status');
            });
        }

        // Add variant columns to product_prices if they don't exist
        if (!Schema::hasColumn('product_prices', 'variant_id')) {
            Schema::table('product_prices', function (Blueprint $table) {
                $table->foreignId('variant_id')->nullable()->after('product_id')
                    ->constrained('product_variants')->onDelete('cascade');
                $table->string('variant_value')->nullable()->after('variant_id');
                $table->enum('pricing_mode', ['single', 'variant'])->default('single')->after('variant_value');
                $table->unique(['product_id', 'variant_id', 'variant_value'], 'unique_product_variant_value_price');
            });
        }

        // Add variant columns to product_stocks if they don't exist
        if (!Schema::hasColumn('product_stocks', 'variant_id')) {
            Schema::table('product_stocks', function (Blueprint $table) {
                // Drop old constraint if it exists using a direct DB check to avoid SQL errors
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                try {
                    $indexExists = DB::select("SHOW INDEX FROM product_stocks WHERE Key_name = 'unique_product_variant_stock'");

                    if (!empty($indexExists)) {
                        DB::statement('ALTER TABLE product_stocks DROP INDEX unique_product_variant_stock');
                    }
                } finally {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                }

                $table->foreignId('variant_id')->nullable()->after('product_id')
                    ->constrained('product_variants')->onDelete('cascade');
                $table->string('variant_value')->nullable()->after('variant_id');
                $table->unique(['product_id', 'variant_id', 'variant_value'], 'unique_product_variant_value_stock');
            });
        }

        // Add variant_id to product_details if it doesn't exist
        if (!Schema::hasColumn('product_details', 'variant_id')) {
            Schema::table('product_details', function (Blueprint $table) {
                $table->foreignId('variant_id')->nullable()->after('supplier_id')
                    ->constrained('product_variants')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_details', function (Blueprint $table) {
            $table->dropForeign(['variant_id']);
            $table->dropColumn('variant_id');
        });

        Schema::table('product_stocks', function (Blueprint $table) {
            $table->dropUnique('unique_product_variant_value_stock');
            $table->dropForeign(['variant_id']);
            $table->dropColumn(['variant_id', 'variant_value']);
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropUnique('unique_product_variant_value_price');
            $table->dropForeign(['variant_id']);
            $table->dropColumn(['variant_id', 'variant_value', 'pricing_mode', 'distributor_price']);
        });

        Schema::dropIfExists('product_variants');
    }
};
