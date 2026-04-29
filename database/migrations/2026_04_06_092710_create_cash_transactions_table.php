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
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reason')->nullable();
            $table->enum('type', ['Revenue', 'Depense']);
            $table->decimal('amount', 10, 2);
            $table->date('transaction_date');
            $table->decimal('solde', 10, 2);
            $table->string('reference')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('status')->default('created');
            $table->foreignId('cash_account_id')->constrained('cash_accounts');
            $table->foreignId('cash_categorie_id')->nullable()->constrained('cash_categories')->nullOnDelete();
            $table->foreignId('addedBy')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
    }
};
