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
        Schema::create('outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('items')->onDelete('cascade');
            $table->float('quantity');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->uuid('barcode')->unique();
            $table->foreignId('provider_id')->constrained('providers')->onDelete('restrict');
            $table->foreignId('outcome_type_id')->constrained('outcome_types')->onDelete('restrict');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->foreignId('model_id')->nullable()->constrained('order_models')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('out_comes');
    }
};
