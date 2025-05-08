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
        Schema::create('part_specifications', function (Blueprint $table) {
            $table->id();
            $table->integer('specification_category_id');
            $table->string('code');
            $table->string('name');
            $table->integer('quantity')->default(1);
            $table->text('comment');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('part_specifications');
    }
};
