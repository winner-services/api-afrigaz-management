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
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->dateTime('requested_at');
            $table->integer('requested_minutes');
            $table->text('reason');
            $table->enum('status', [
                'pending',
                'approved',
                'rejected'
            ])->default('pending');
            $table->dateTime('approved_until')
                ->nullable();
            $table->dateTime('approved_at')
                ->nullable();
            $table->dateTime('rejected_at')
                ->nullable();
            $table->dateTime('operation_date');
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('rejected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
