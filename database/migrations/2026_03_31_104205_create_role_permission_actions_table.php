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
        Schema::create('role_permission_actions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('permission_id')->nullable();

            // 🔹 Actions possibles
            $table->boolean('voir')->default(false);
            $table->boolean('ajouter')->default(false);
            $table->boolean('modifier')->default(false);
            $table->boolean('supprimer')->default(false);

            $table->timestamps();

            $table->foreign('role_id')
                ->references('id')->on('roles')
                ->onDelete('set null');

            $table->foreign('permission_id')
                ->references('id')->on('permissions')
                ->onDelete('set null');

            $table->unique(['role_id', 'permission_id'], 'role_permission_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permission_actions');
    }
};
