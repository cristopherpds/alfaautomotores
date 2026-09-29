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
        Schema::table('vehiculos', function (Blueprint $table): void {
            // Cuándo pasó a vendido. Lo pone y lo limpia el hook `saving` de
            // `Vehiculo`; nulo en cualquier otro estado.
            $table->timestamp('vendido_at')->nullable()->after('estado')->index();
        });

        /* Los vendidos que ya había: la única fecha que se puede recuperar es
           la de la auditoría, que existe desde hoy. Los más viejos quedan en
           null, y no cuentan para ningún mes. */
        $ventas = DB::table('auditorias')
            ->where('tipo', 'vehiculo')
            ->where('cambios->estado[1]', 'vendido')
            ->selectRaw('registro_id, max(created_at) as fecha')
            ->groupBy('registro_id')
            ->pluck('fecha', 'registro_id');

        foreach ($ventas as $vehiculoId => $fecha) {
            DB::table('vehiculos')
                ->where('id', $vehiculoId)
                ->where('estado', 'vendido')
                ->update(['vendido_at' => $fecha]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table): void {
            $table->dropIndex(['vendido_at']);
            $table->dropColumn('vendido_at');
        });
    }
};
