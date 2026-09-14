<?php

use App\Enums\Rubro;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada puesto pertenece a un rubro.
 *
 * Es lo que evita que un lavado bloquee un puesto de mecánica: la
 * disponibilidad de un servicio sólo mira los puestos de su propio rubro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('puestos', function (Blueprint $tabla) {
            $tabla->string('rubro')->default(Rubro::Taller->value)->after('nombre');

            $tabla->index(['rubro', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::table('puestos', function (Blueprint $tabla) {
            $tabla->dropIndex(['rubro', 'activo']);
            $tabla->dropColumn('rubro');
        });
    }
};
