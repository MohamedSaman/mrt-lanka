<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('discount_type', 20)->default('fixed')->after('total_discount')->comment('Type of discount: fixed or percentage');
            $table->decimal('discount_percentage', 5, 2)->default(0)->after('discount_type')->comment('Percentage value if discount_type is percentage');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_percentage']);
        });
    }
};
