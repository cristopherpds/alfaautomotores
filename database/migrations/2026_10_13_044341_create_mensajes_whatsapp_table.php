<?php

use App\Models\Conversacion;
use App\Models\User;
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
        Schema::create('mensajes_whatsapp', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Conversacion::class)->constrained('conversaciones')->cascadeOnDelete();

            $table->string('direccion', 10);
            $table->string('tipo', 20)->default('texto');
            $table->text('cuerpo')->nullable();

            /* El adjunto: `media_id` es el de Meta (sirve para bajarlo una
               vez), `media_ruta` la copia en el disco `local`. */
            $table->string('media_id')->nullable();
            $table->string('media_ruta')->nullable();
            $table->string('media_mime', 100)->nullable();
            $table->string('media_nombre')->nullable();

            // Idempotencia del webhook: Meta reenvía el mismo mensaje.
            $table->string('wa_message_id')->nullable()->unique();
            $table->string('estado', 20);
            $table->text('error')->nullable();

            $table->boolean('generado_por_ia')->default(false);
            $table->foreignIdFor(User::class, 'enviado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('plantilla')->nullable();

            $table->timestamp('wa_timestamp')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['conversacion_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mensajes_whatsapp');
    }
};
