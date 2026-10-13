<?php

use App\Models\Cliente;
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
        Schema::create('conversaciones', function (Blueprint $table) {
            $table->id();
            /* El número tal como lo manda Meta: E.164 sin «+» (`59899123456`).
               Es la identidad de la conversación: una por número. */
            $table->string('wa_id', 20)->unique();
            $table->string('nombre_perfil', 120)->nullable();
            $table->foreignIdFor(Cliente::class)->nullable()->constrained()->nullOnDelete();

            /* La IA contesta sólo con `ia_activa` y sin derivación: una
               conversación derivada queda callada hasta que una persona la
               devuelva. */
            $table->boolean('ia_activa')->default(true);
            $table->timestamp('derivada_at')->nullable();
            $table->string('motivo_derivacion', 20)->nullable();

            // La ventana de 24 h de Meta se cuenta desde el último entrante.
            $table->timestamp('ultimo_entrante_at')->nullable();
            $table->timestamp('ultimo_mensaje_at')->nullable()->index();
            $table->unsignedInteger('no_leidos')->default(0);

            $table->json('ficha')->nullable();
            $table->timestamp('archivada_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversaciones');
    }
};
