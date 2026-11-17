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
        /* Copia local de las plantillas de la cuenta de Meta: se crean y se
           aprueban allá, acá sólo se sincronizan para poder mandarlas cuando
           la ventana de 24 h está cerrada. */
        Schema::create('plantillas_whatsapp', function (Blueprint $table) {
            $table->id();
            $table->string('wa_template_id')->nullable();
            $table->string('nombre');
            $table->string('idioma', 10);
            $table->string('categoria', 20)->nullable();
            $table->text('cuerpo')->nullable();
            $table->unsignedTinyInteger('variables')->default(0);
            $table->string('estado', 20);
            $table->string('motivo_rechazo')->nullable();
            $table->timestamps();

            $table->unique(['nombre', 'idioma']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plantillas_whatsapp');
    }
};
