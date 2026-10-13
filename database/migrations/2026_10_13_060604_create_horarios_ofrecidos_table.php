<?php

use App\Models\Conversacion;
use App\Models\Servicio;
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
        /* Los horarios que el agente le ofreció a cada conversación. Es la
           regla de nea-agent: sólo se reserva un horario de esta lista, así el
           modelo no puede inventar «el martes a las 10». */
        Schema::create('horarios_ofrecidos', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Conversacion::class)->constrained('conversaciones')->cascadeOnDelete();
            $table->foreignIdFor(Servicio::class)->constrained()->cascadeOnDelete();
            $table->dateTime('inicia_at');
            $table->timestamps();

            $table->index(['conversacion_id', 'servicio_id', 'inicia_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horarios_ofrecidos');
    }
};
