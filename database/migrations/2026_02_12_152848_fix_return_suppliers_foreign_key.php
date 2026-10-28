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
        Schema::table('return_suppliers', function (Blueprint $table) {
            // Drop the incorrect foreign key
            $table->dropForeign(['product_id']);

            // Add correct foreign key to product_details
            $table->foreign('product_id')
                ->references('id')
                ->on('product_details')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_suppliers', function (Blueprint $table) {
            // Revert back to original (incorrect) constraint
            $table->dropForeign(['product_id']);

            $table->foreign('product_id')
                ->references('id')
                ->on('product_stocks')
                ->onDelete('cascade');
        });
    }
};
