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
        Schema::create('entregas', function (Blueprint $table): void {
            $table->id();
            /* Ruta relativa dentro del disco `public`, como en vehiculo_imagenes.
               Única: es la clave de idempotencia del importador de la semilla. */
            $table->string('ruta')->unique();
            $table->date('fecha');
            $table->timestamps();

            // La tira y el panel listan siempre por fecha; el id desempata el mismo día.
            $table->index('fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entregas');
    }
};
