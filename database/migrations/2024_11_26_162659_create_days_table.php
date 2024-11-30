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
        Schema::create('daily_groups', function (Blueprint $table) {
            $table->id();
            $table->integer('work_count');
            $table->integer('total_work_time');
            $table->integer('expected_model');
            $table->integer('group_id');
            $table->integer('real_model');
            $table->integer('diff_model');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('days');
    }
};
