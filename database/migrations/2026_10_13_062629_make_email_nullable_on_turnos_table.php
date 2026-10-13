<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Un turno que reserva el agente de WhatsApp no trae email: sólo el
     * número. La web y el panel lo siguen pidiendo en su validación.
     */
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
