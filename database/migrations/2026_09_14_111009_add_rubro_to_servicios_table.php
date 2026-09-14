<?php

use App\Enums\Rubro;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El rubro parte el catálogo en dos negocios: taller y lavadero.
 *
 * El default cubre el backfill —todo lo que hay hoy es del taller— y `area`
 * pasa a nullable porque el lavadero no se clasifica por área: sus servicios
 * son tamaños de vehículo, no especialidades.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicios', function (Blueprint $tabla) {
            $tabla->string('rubro')->default(Rubro::Taller->value)->after('slug');
            $tabla->string('area')->nullable()->change();
            $tabla->unsignedInteger('precio')->nullable()->after('duracion');

            $tabla->index(['rubro', 'activo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::table('servicios', function (Blueprint $tabla) {
            $tabla->dropIndex(['rubro', 'activo', 'orden']);
            $tabla->dropColumn(['rubro', 'precio']);
            $tabla->string('area')->nullable(false)->change();
        });
    }
};
