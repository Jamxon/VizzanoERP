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
        Schema::create('order_printing_times', function (Blueprint $table) {
            $table->id();
            $table->integer('order_model_id');
            $table->dateTime('planned_time');
            $table->dateTime('actual_time')->nullable();
            $table->string('status')->default('printing');
            $table->text('comment')->nullable();
            $table->integer('user_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_printing_times');
    }
};
