<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La matrícula acompaña a los otros datos del vehículo, y como ellos es
 * opcional: sirve para reconocer el auto en el patio, no para identificar al
 * cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $tabla) {
            $tabla->string('matricula', 12)->nullable()->after('vehiculo_anio');
        });
    }

    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $tabla) {
            $tabla->dropColumn('matricula');
        });
    }
};
