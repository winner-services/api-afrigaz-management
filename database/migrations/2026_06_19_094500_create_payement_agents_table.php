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
        Schema::create('payement_agents', function (Blueprint $table) {
            $table->id();
            $table->decimal('total_net_a_verser', 12, 2)->default(0);
            $table->decimal('total_avances_deduites', 12, 2)->default(0);
            $table->decimal('reste_a_payer', 12, 2)->default(0);
            $table->decimal('total_masse_salariale_brute', 12, 2);
            $table->foreignId('addedBy')->constrained('users');
            $table->string('mois_concerne');
            $table->dateTime('date_activation');
            $table->string('reference')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payement_agents');
    }
};
