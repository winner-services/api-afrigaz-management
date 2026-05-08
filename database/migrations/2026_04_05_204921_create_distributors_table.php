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
        Schema::create('distributors', function (Blueprint $table) {
            $table->id();
            $table->enum('type', [
                'physical',
                'company'
            ])->default('physical');
            $table->string('reference')->unique();
            $table->string('name');
            $table->string('gender')->nullable();
            $table->string('rccm')->nullable();
            $table->string('idnat')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('manager_name')->nullable();
            // 🔹 Identité
            $table->string('identity_type')->nullable();
            $table->string('identity_number')->nullable();
            $table->string('identity_document')->nullable();
            // 🔹 Contacts
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('password');
            // 🔹 Adresse
            $table->string('country')->default('RDC');
            $table->string('city')->nullable();
            $table->string('commune')->nullable();
            $table->string('quartier')->nullable();
            $table->string('avenue')->nullable();

            $table->string('status')->default('actif');
            $table->boolean('is_deleted')->default(false);
            $table->foreignId('category_distributor_id')->nullable()->constrained('category_distributors')->nullOnDelete();
            $table->foreignId('addedBy')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distributors');
    }
};
