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
        Schema::create('cash_categories', function (Blueprint $table) {
            $table->id();
            $table->string('designation');
            $table->string('description')->nullable();
            $table->enum('type', ['Revenue', 'Depense']);
            $table->string('status')->default('created');
            $table->foreignId('addedBy')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_categories');
    }
};
