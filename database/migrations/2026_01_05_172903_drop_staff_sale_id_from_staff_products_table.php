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
        Schema::table('staff_products', function (Blueprint $table) {
            $table->dropForeign(['staff_sale_id']);
            $table->dropColumn('staff_sale_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_products', function (Blueprint $table) {
            $table->unsignedBigInteger('staff_sale_id')->nullable();
            $table->foreign('staff_sale_id')->references('id')->on('staff_sales')->onDelete('cascade');
        });
    }
};
