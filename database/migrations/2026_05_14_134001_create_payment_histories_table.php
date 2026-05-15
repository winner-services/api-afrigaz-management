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
        Schema::create('payment_histories', function (Blueprint $table) {
            $table->id();
            $table->enum('payment_type', [
                'sale',
                'customer_debt',
                'distributor_debt',
                'refund',
                'transfer',
                'bonus',
                'other'
            ]);
            $table->unsignedBigInteger('reference_id')
                ->nullable();

            $table->string('reference')->nullable();
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();

            $table->foreignId('distributor_id')
                ->nullable()
                ->constrained('distributors')
                ->nullOnDelete();
            $table->foreignId('cash_account_id')
                ->nullable()
                ->constrained('cash_accounts')
                ->nullOnDelete();
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();
            $table->decimal('paid_amount', 10, 2);
            $table->string('payment_method')->nullable();
            $table->date('payment_date');
            $table->foreignId('addedBy')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->enum('status', [
                'pending',
                'paid',
                'cancelled',
                'failed'
            ])->default('paid');
            $table->text('description')
                ->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_histories');
    }
};
