<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->unique();
            $table->integer('group_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->enum('payment_type', ['hourly', 'monthly'])->default('monthly');
            $table->decimal('salary', 10, 2)->nullable();
            $table->date('hiring_date')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->string('address')->nullable();
            $table->string('passport_number')->nullable();
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
