<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Apagado por defecto: los slides que ya existían se siguen viendo igual.
     */
    public function up(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->boolean('con_logo')->default(false)->after('fondo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->dropColumn('con_logo');
        });
    }
};
