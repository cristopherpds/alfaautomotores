<?php

use App\Enums\EstadoVehiculo;
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
        Schema::create('vehiculos', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('marca');
            $table->string('modelo');
            $table->string('version')->nullable();
            $table->unsignedSmallInteger('anio');
            $table->unsignedInteger('km');
            $table->unsignedInteger('precio');
            $table->string('moneda')->default(Moneda::Usd->value);
            $table->string('comb');
            $table->string('trans');
            $table->string('tipo');
            $table->string('estado')->default(EstadoVehiculo::Borrador->value);
            $table->boolean('destacado')->default(false);
            $table->text('desc');
            $table->timestamps();

            // La portada y el catálogo filtran siempre por estos dos juntos.
            $table->index(['estado', 'destacado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehiculos');
    }
};
