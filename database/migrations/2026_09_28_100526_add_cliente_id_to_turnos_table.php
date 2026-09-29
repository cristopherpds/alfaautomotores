<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $table): void {
            // El turno guarda su propia copia de los datos (son el registro
            // de esa reserva): si se borra el cliente, el turno sigue leyéndose.
            $table->foreignId('cliente_id')->nullable()->after('puesto_id')
                ->constrained('clientes')->nullOnDelete();
        });

        /* Los turnos que ya había: uno por celular normalizado, con los datos
           del turno más reciente. Nadie queda con consentimiento: no se pidió. */
        $turnos = DB::table('turnos')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'nombre', 'apellido', 'email', 'celular', 'created_at']);

        $clientes = [];

        foreach ($turnos as $turno) {
            $clave = preg_replace('/\D+/', '', (string) $turno->celular);

            if ($clave === '') {
                continue;
            }

            $clientes[$clave] ??= DB::table('clientes')->insertGetId([
                'nombre' => $turno->nombre,
                'apellido' => $turno->apellido,
                'email' => mb_strtolower(trim((string) $turno->email)) ?: null,
                'celular' => $turno->celular,
                'celular_normalizado' => $clave,
                'acepta_novedades' => false,
                'created_at' => $turno->created_at ?? now(),
                'updated_at' => now(),
            ]);

            DB::table('turnos')->where('id', $turno->id)->update(['cliente_id' => $clientes[$clave]]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cliente_id');
        });
    }
};
