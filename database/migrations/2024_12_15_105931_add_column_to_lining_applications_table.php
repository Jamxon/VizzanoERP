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
        Schema::table('lining_applications', function (Blueprint $table) {
            $table->integer('razryad_id');
            $table->string('machine');
            $table->integer('second');
            $table->integer('summa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lining_applications', function (Blueprint $table) {
            //
        });
    }
};
