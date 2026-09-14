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
        Schema::create('servicios', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('nombre');
            $table->string('area');
            // Duración del trabajo en minutos: es lo que mide cada turno.
            $table->unsignedSmallInteger('duracion');
            $table->text('descripcion');
            // Ruta relativa dentro del disco `public`, como en vehiculo_imagenes.
            $table->string('foto')->nullable();
            $table->boolean('activo')->default(true);
            /* Los trabajos mayores se cotizan con el vehículo en el taller: se
               muestran en la grilla pero con «Consultar por WhatsApp». */
            $table->boolean('agendable')->default(true);
            $table->unsignedTinyInteger('orden')->default(0);
            $table->timestamps();

            // La grilla pública lista siempre los activos en este orden.
            $table->index(['activo', 'orden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servicios');
    }
};
