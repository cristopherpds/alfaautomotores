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
        /*
         * Los puestos del taller: cuántos autos se atienden a la vez.
         *
         * Cada uno es un recurso con su propia agenda (Zap no maneja capacidad
         * mayor que uno sobre un mismo recurso), así que N autos en paralelo
         * son N puestos. Los crea `taller:agenda` a partir de
         * `config('taller.puestos')`.
         */
        Schema::create('puestos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('puestos');
    }
};
