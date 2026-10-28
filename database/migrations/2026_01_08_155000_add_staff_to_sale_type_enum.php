<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the sale_type enum to include 'staff'
        DB::statement("ALTER TABLE sales MODIFY sale_type ENUM('pos', 'admin', 'staff') DEFAULT 'pos'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum without 'staff'
        DB::statement("ALTER TABLE sales MODIFY sale_type ENUM('pos', 'admin') DEFAULT 'pos'");
    }
};
