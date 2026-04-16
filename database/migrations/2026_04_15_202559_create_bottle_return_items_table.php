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
        Schema::create('bottle_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bottle_return_id')
                ->constrained('bottle_returns')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            // 🔥 état bouteille
            $table->enum('condition', [
                'good',
                'damaged',
                'repair'
            ]);

            // 🔥 quantité
            $table->integer('quantity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bottle_return_items');
    }
};
