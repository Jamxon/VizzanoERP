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
        Schema::create('check_normal_technic_proccesses', function (Blueprint $table) {
            $table->id();
            $table->integer('proccess_id');
            $table->integer('model_id');
            $table->integer('detal_id');
            $table->integer('Sekund');
            $table->integer('razryad_id');
            $table->integer('Summa');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_normal_technic_proccesses');
    }
};
