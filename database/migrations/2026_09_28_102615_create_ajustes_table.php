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
        // Ajustes del sitio que se cambian desde el panel: una fila por
        // grupo (`boton_whatsapp`), con sus valores juntos en un json.
        Schema::create('ajustes', function (Blueprint $table): void {
            $table->id();
            $table->string('clave')->unique();
            $table->json('valor');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ajustes');
    }
};
