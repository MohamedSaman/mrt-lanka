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
        // Add staff_type column to users table
        Schema::table('users', function (Blueprint $table) {
            $table->enum('staff_type', ['salesman', 'delivery_man', 'shop_staff'])
                ->nullable()
                ->after('role')
                ->comment('Type of staff: salesman, delivery_man, or shop_staff');
        });

        // Add approval fields to sales table
        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_by')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');
            $table->unsignedBigInteger('delivered_by')->nullable()->after('rejection_reason');
            $table->timestamp('delivered_at')->nullable()->after('delivered_by');
            $table->enum('delivery_status', ['pending', 'in_transit', 'delivered', 'cancelled'])
                ->default('pending')
                ->after('delivered_at');

            // Foreign key constraints
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('delivered_by')->references('id')->on('users')->nullOnDelete();
        });

        // Add approval fields to payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_by')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');
            $table->unsignedBigInteger('collected_by')->nullable()->after('rejection_reason');
            $table->timestamp('collected_at')->nullable()->after('collected_by');

            // Foreign key constraints
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('collected_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('staff_type');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['delivered_by']);
            $table->dropColumn(['approved_by', 'approved_at', 'rejection_reason', 'delivered_by', 'delivered_at', 'delivery_status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['collected_by']);
            $table->dropColumn(['approved_by', 'approved_at', 'rejection_reason', 'collected_by', 'collected_at']);
        });
    }
};
