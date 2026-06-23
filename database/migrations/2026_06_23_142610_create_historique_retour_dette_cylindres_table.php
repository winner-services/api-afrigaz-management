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
        Schema::create('historique_retour_dette_cylindres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dette_detail_id')->constrained('dette_cylindre_details')->cascadeOnDelete();
            $table->foreignId('distributor_id')->nullable()->constrained('distributors')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('returned_quantity')->default(0);
            $table->dateTime('date_retour')->nullable();
            $table->foreignId('addedBy')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historique_retour_dette_cylindres');
    }
};
