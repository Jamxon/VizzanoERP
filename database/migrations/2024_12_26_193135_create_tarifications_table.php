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
        Schema::create('tarifications', function (Blueprint $table) {
            $table->id();
            $table->integer('tarification_category_id');
            $table->integer('user_id');
            $table->string('name');
            $table->integer('razryad_id');
            $table->integer('typewriter_id');
            $table->double('second');
            $table->double('summa');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tarifications');
    }
};
