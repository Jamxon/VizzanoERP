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
        Schema::create('constructor_orders', function (Blueprint $table) {
            $table->id();
            $table->integer('order_model_id');
            $table->integer('submodel_id');
            $table->integer('size_id');
            $table->integer('quantity');
            $table->string('status')->default('printing');
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('constructor_orders');
    }
};
