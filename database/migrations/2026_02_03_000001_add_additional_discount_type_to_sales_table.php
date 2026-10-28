<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('additional_discount_type', 20)->default('fixed')->after('discount_amount')->comment('Type of additional discount: fixed or percentage');
            $table->decimal('additional_discount_percentage', 5, 2)->default(0)->after('additional_discount_type')->comment('Percentage value if additional_discount_type is percentage');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['additional_discount_type', 'additional_discount_percentage']);
        });
    }
};
