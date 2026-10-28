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
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('expenses', 'category_id')) {
                $table->unsignedBigInteger('category_id')->nullable()->after('user_id');
            }

            // Add foreign keys where possible
            if (Schema::hasTable('users')) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            }
            if (Schema::hasTable('expense_categories')) {
                $table->foreign('category_id')->references('id')->on('expense_categories')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'category_id')) {
                try { $table->dropForeign(['category_id']); } catch (\Exception $e) {}
                $table->dropColumn('category_id');
            }
            if (Schema::hasColumn('expenses', 'user_id')) {
                try { $table->dropForeign(['user_id']); } catch (\Exception $e) {}
                $table->dropColumn('user_id');
            }
        });
    }
};
