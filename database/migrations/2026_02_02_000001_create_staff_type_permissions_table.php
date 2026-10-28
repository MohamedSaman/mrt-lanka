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
        Schema::create('staff_type_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('staff_type'); // salesman, delivery_man, shop_staff
            $table->string('permission_key');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Unique constraint to prevent duplicates
            $table->unique(['staff_type', 'permission_key']);

            // Index for faster lookups
            $table->index('staff_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_type_permissions');
    }
};
