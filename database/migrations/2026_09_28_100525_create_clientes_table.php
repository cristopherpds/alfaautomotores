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
        Schema::create('clientes', function (Blueprint $table): void {
            $table->id();
            // Los últimos datos que dejó: una reserva nueva los pisa.
            $table->string('nombre');
            $table->string('apellido');
            $table->string('email')->nullable()->index();
            $table->string('celular');
            // La identidad del cliente: sólo dígitos, así «099 123 456» y
            // «099123456» son la misma persona.
            $table->string('celular_normalizado')->unique();
            // Consentimiento para promociones y recordatorios (ley 18.331):
            // falso por defecto y con la fecha en que se dio.
            $table->boolean('acepta_novedades')->default(false)->index();
            $table->timestamp('acepta_novedades_at')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
