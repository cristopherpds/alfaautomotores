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
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_fondo', 10);
            $table->string('fondo');
            $table->string('eyebrow', 60)->nullable();
            $table->string('titulo', 120);
            $table->string('bajada', 240)->nullable();
            $table->string('boton1_texto', 40)->nullable();
            $table->string('boton1_url')->nullable();
            $table->string('boton2_texto', 40)->nullable();
            $table->string('boton2_url')->nullable();
            $table->boolean('activo')->default(true);
            $table->date('desde')->nullable();
            $table->date('hasta')->nullable();
            $table->unsignedTinyInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['activo', 'orden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
