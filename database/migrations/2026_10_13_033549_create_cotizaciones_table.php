<?php

use App\Models\Cliente;
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
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Cliente::class)->nullable()->constrained()->nullOnDelete();

            $table->string('marca', 60);
            $table->string('modelo', 60);
            $table->unsignedSmallInteger('anio');
            $table->unsignedInteger('kilometros');
            $table->text('detalles')->nullable();

            /* Rutas dentro del disco `local` (privado): son fotos del auto de
               un particular, no se sirven desde `public/`. */
            $table->string('foto_derecha')->nullable();
            $table->string('foto_izquierda')->nullable();
            $table->string('foto_interior')->nullable();
            $table->string('foto_tablero')->nullable();

            // La copia de los datos de contacto de esta solicitud.
            $table->string('nombre', 80);
            $table->string('apellido', 80);
            $table->string('celular', 25);

            $table->string('estado', 20)->default('nueva')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizaciones');
    }
};
