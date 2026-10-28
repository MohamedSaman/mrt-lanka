<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            $table->decimal('retail_price', 10, 2)->nullable()->after('selling_price')->comment('Retail customer price');
            $table->decimal('wholesale_price', 10, 2)->nullable()->after('retail_price')->comment('Wholesale customer price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropColumn(['retail_price', 'wholesale_price']);
        });
    }
};
