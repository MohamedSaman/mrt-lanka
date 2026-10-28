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
        if (!Schema::hasColumn('product_details', 'variant_id')) {
            Schema::table('product_details', function (Blueprint $table) {
                $table->foreignId('variant_id')
                    ->nullable()
                    ->constrained('product_variants')
                    ->nullOnDelete()
                    ->after('supplier_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variant_id');
        });
    }
};
