<?php

use App\Enums\EstadoProducto;
use App\Enums\Moneda;
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
        Schema::create('productos', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('nombre');
            $table->string('familia');
            // Código del proveedor (ETB-122, KDS-DC49): no todos lo traen.
            $table->string('codigo')->nullable();
            // Nulo a propósito: las bicicletas se cotizan y no publican precio.
            $table->unsignedInteger('precio')->nullable();
            $table->string('moneda')->default(Moneda::Uyu->value);
            $table->string('estado')->default(EstadoProducto::Borrador->value);
            // La línea de la tarjeta: potencia · velocidad · autonomía.
            $table->string('resumen');
            $table->text('desc');
            // Ficha técnica y colores, tal como los publica el proveedor.
            $table->json('specs');
            $table->json('colores');
            $table->timestamps();

            // Las dos grillas filtran siempre por familia dentro de lo público.
            $table->index(['estado', 'familia']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
