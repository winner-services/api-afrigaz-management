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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('reference')
                ->unique();
            $table->enum('status', [
                'pending',
                'confirmed',
                'processing',
                'delivered',
                'cancelled',
                'rejected'
            ])->default('pending');
            $table->decimal('total', 12, 2)
                ->default(0);
            $table->string('payment_method')
                ->nullable();
            $table->string('delivery_address')
                ->nullable();
            $table->foreignId('confirmed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('rejected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('note')
                ->nullable();
            $table->decimal('amount', 12, 2)
                ->default(0);
            $table->date('order_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
