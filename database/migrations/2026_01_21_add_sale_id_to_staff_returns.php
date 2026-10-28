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
        Schema::table('staff_returns', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('staff_id')->constrained('sales')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_returns', function (Blueprint $table) {
            $table->dropForeignIdFor('sales');
        });
    }
};
