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
        Schema::create('model_kartas', function (Blueprint $table) {
            $table->id();
            $table->integer('model_id');
            $table->string('material_name');
            $table->string('image')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_kartas');
    }
};
