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
        // First, temporarily disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            // Drop the old unique constraint if it exists
            $indexExists = DB::select("SHOW INDEX FROM product_stocks WHERE Key_name = 'unique_product_variant_stock'");

            if (!empty($indexExists)) {
                DB::statement('ALTER TABLE product_stocks DROP INDEX unique_product_variant_stock');
            }

            // Check if new constraint already exists
            $newIndexExists = DB::select("SHOW INDEX FROM product_stocks WHERE Key_name = 'unique_product_variant_value_stock'");

            if (empty($newIndexExists)) {
                // Add the new unique constraint that includes variant_value
                DB::statement('ALTER TABLE product_stocks ADD UNIQUE unique_product_variant_value_stock(product_id, variant_id, variant_value)');
            }
        } finally {
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->dropUnique('unique_product_variant_value_stock');
            $table->unique(['product_id', 'variant_id'], 'unique_product_variant_stock');
        });
    }
};
