<?php

use App\Enums\EstadoTurno;
use App\Enums\OrigenTurno;
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
        Schema::create('turnos', function (Blueprint $table): void {
            $table->id();

            /* Un servicio con turnos no se borra: el ABM lo desactiva. */
            $table->foreignId('servicio_id')->constrained('servicios')->restrictOnDelete();
            $table->foreignId('puesto_id')->constrained('puestos')->cascadeOnDelete();

            /* La cita espejo en Zap, que es la que hace que el horario deje de
               ofrecerse. Sin clave foránea a propósito: la tabla la publica el
               paquete y su nombre no es asunto de esta migración. El turno es
               la fuente de verdad; esto es el puntero para poder liberarla. */
            $table->unsignedBigInteger('schedule_id')->nullable();

            $table->string('estado')->default(EstadoTurno::Pendiente->value);
            $table->string('origen')->default(OrigenTurno::Web->value);

            $table->dateTime('inicia_at');
            $table->dateTime('termina_at');

            $table->string('nombre');
            $table->string('apellido');
            $table->string('email');
            $table->string('celular');

            $table->string('vehiculo_marca')->nullable();
            $table->string('vehiculo_modelo')->nullable();
            $table->unsignedSmallInteger('vehiculo_anio')->nullable();
            $table->text('comentario')->nullable();

            $table->timestamps();

            // El calendario pide rangos; el panel filtra además por estado.
            $table->index('inicia_at');
            $table->index(['estado', 'inicia_at']);
            $table->index(['puesto_id', 'inicia_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};
