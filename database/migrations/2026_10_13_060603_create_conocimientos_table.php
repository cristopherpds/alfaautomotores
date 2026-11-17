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
        /* Lo que el agente de WhatsApp sabe del negocio, como el knowledge
           base de Vocero: pares pregunta/respuesta y bloques libres. Es su
           única fuente de verdad junto con el stock y la agenda. */
        Schema::create('conocimientos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 10);
            $table->string('pregunta')->nullable();
            $table->text('respuesta')->nullable();
            $table->text('contenido')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conocimientos');
    }
};
