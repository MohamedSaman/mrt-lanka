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
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('opening_balance', 12, 2)->default(0)->after('notes');
            $table->decimal('due_amount', 12, 2)->default(0)->after('opening_balance');
            $table->decimal('total_due', 12, 2)->default(0)->after('due_amount');
            $table->decimal('overpaid_amount', 12, 2)->default(0)->after('total_due');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['opening_balance', 'due_amount', 'total_due', 'overpaid_amount']);
        });
    }
};
