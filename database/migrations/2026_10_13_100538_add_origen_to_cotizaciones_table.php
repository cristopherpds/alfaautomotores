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
        Schema::table('cotizaciones', function (Blueprint $table): void {
            // Las que ya había llegaron todas por el formulario del sitio.
            $table->string('origen')->default('web')->after('estado');
            // La conversación de WhatsApp de la que salió. Si se borra la
            // conversación, la cotización queda con su propia copia.
            $table->foreignId('conversacion_id')->nullable()->after('cliente_id')
                ->constrained('conversaciones')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('conversacion_id');
            $table->dropColumn('origen');
        });
    }
};
