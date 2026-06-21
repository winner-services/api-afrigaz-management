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
        Schema::create('payement_agent_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents');
            $table->foreignId('paiement_id')->constrained('payement_agents');
            $table->decimal('salaire_base', 15, 2);
            $table->decimal('total_avances', 15, 2);
            $table->decimal('net_a_payer', 15, 2);
            $table->dateTime('date_paiement');
            $table->string('status')->default('en_attente'); // 'en_attente', 'paye'
            $table->string('reference')->nullable();
            $table->foreignId('account_id')->nullable()->constrained('comptes')->cascadeOnDelete();
            $table->string('reference_paiement')->nullable();
            $table->string('type_payment')->nullable();
            $table->foreignId('confirmedBy')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payement_agent_details');
    }
};
