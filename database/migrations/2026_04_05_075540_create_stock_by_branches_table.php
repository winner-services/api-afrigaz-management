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
        Schema::create('stock_by_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branche_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();
            $table->integer('stock_quantity')->default(0);
            $table->boolean('is_empty')->default(false);
            $table->enum('condition_state', [
                'good',
                'damaged',
                'repair'
            ])->default('good');
            $table->foreignId('categorie_id')
                ->nullable()
                ->constrained('product_categories')
                ->nullOnDelete();
            $table->string('status')->default('created');
            $table->timestamps();
            $table->unique([
                'branche_id',
                'product_id',
                'is_empty',
                'condition_state'
            ], 'unique_stock_state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_by_branches');
    }
};
