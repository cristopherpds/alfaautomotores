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
        Schema::create('auditorias', function (Blueprint $table): void {
            $table->id();
            // Nulo cuando no hubo usuario (reserva del sitio web, consola) o si
            // el usuario se borró después: el nombre queda en `usuario`.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('usuario');
            $table->string('accion');
            // Qué registro fue. No es morph a propósito: después de una baja
            // el registro ya no existe y la entrada tiene que seguir leyéndose.
            $table->string('tipo');
            $table->unsignedBigInteger('registro_id');
            $table->string('etiqueta');
            $table->json('cambios')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['tipo', 'registro_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
