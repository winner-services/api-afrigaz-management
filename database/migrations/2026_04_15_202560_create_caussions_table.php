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
        Schema::create('caussions', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 10, 2);
            $table->date('transaction_date');
            $table->foreignId('category_distributor_id')->constrained('category_distributors')->onDelete('cascade');
            $table->foreignId('addedBy')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('created');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caussions');
    }
};
