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
        Schema::table('order_sub_models', function (Blueprint $table) {
            $table->dropColumn('size_id');
            $table->dropColumn('quantity');
            $table->dropColumn('materials_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_sub_models', function (Blueprint $table) {
            //
        });
    }
};
