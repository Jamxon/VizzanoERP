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
        Schema::create('products', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->string('name', 200)->comment('Mahsulot nomi');
            $table->string('description', 200)->nullable()->comment('Tavsif');
            $table->string('unit')->comment('O‘lchov birligi');
            $table->decimal('price', 16, 2)->comment('Narx');
            $table->float('quantity')->comment('Miqdor');
            $table->string('img')->nullable()->comment('Rasm yo‘li');
            $table->foreignId('color_id')->constrained('colors')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamps(); // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
