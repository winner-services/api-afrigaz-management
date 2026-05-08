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
        Schema::create('abouts', function (Blueprint $table) {
            $table->id();
            $table->text('denomination')->nullable();
            $table->text('rccm')->nullable();
            $table->text('register')->nullable();
            $table->text('national_id')->nullable();
            $table->text('import_export')->nullable();
            $table->text('tax_number')->nullable();
            $table->text('phone')->nullable();
            $table->text('address')->nullable();
            $table->text('email')->nullable();
            $table->text('logo')->nullable();
            $table->text('logo2')->nullable();
            $table->time('opening_time');
            $table->time('closing_time');
            $table->integer('grace_minutes')
                ->default(15);
            $table->json('working_days')
                ->nullable();
            $table->timestamps();
            $table->time('opening_time')->nullable();
            $table->time('closing_time')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abouts');
    }
};
