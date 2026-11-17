<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los datos personales pasan a ir cifrados (AES-256-GCM, casts `encrypted`).
 *
 * Un dato cifrado es más largo que el original y sale distinto cada vez, así
 * que las columnas pasan a `text` y los índices se mudan a su índice ciego
 * (`*_hash`, HMAC-SHA256, ver `App\Support\IndiceCiego`): la identidad del
 * cliente queda en `celular_hash` y la de la conversación en `wa_id_hash`.
 * Los hashes quedan nulos acá; los completa la migración siguiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            $table->dropUnique(['celular_normalizado']);
            $table->dropIndex(['email']);
        });

        Schema::table('clientes', function (Blueprint $table): void {
            $table->text('nombre')->change();
            $table->text('apellido')->change();
            $table->text('email')->nullable()->change();
            $table->text('celular')->change();
            $table->text('celular_normalizado')->change();
            $table->string('celular_hash', 64)->nullable()->unique()->after('celular_normalizado');
            $table->string('email_hash', 64)->nullable()->index()->after('email');
        });

        Schema::table('turnos', function (Blueprint $table): void {
            $table->text('nombre')->change();
            $table->text('apellido')->change();
            $table->text('email')->nullable()->change();
            $table->text('celular')->change();
            $table->text('matricula')->nullable()->change();
            $table->string('matricula_hash', 64)->nullable()->index()->after('matricula');
        });

        Schema::table('cotizaciones', function (Blueprint $table): void {
            $table->text('nombre')->change();
            $table->text('apellido')->change();
            $table->text('celular')->change();
        });

        Schema::table('conversaciones', function (Blueprint $table): void {
            $table->dropUnique(['wa_id']);
        });

        Schema::table('conversaciones', function (Blueprint $table): void {
            $table->text('wa_id')->change();
            $table->text('nombre_perfil')->nullable()->change();
            $table->text('ficha')->nullable()->change();
            $table->string('wa_id_hash', 64)->nullable()->unique()->after('wa_id');
        });

        Schema::table('mensajes_whatsapp', function (Blueprint $table): void {
            $table->text('media_nombre')->nullable()->change();
            $table->text('meta')->nullable()->change();
        });

        Schema::table('auditorias', function (Blueprint $table): void {
            $table->text('etiqueta')->change();
            $table->text('cambios')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('auditorias', function (Blueprint $table): void {
            $table->string('etiqueta')->change();
            $table->json('cambios')->nullable()->change();
        });

        Schema::table('mensajes_whatsapp', function (Blueprint $table): void {
            $table->string('media_nombre')->nullable()->change();
            $table->json('meta')->nullable()->change();
        });

        Schema::table('conversaciones', function (Blueprint $table): void {
            $table->dropUnique(['wa_id_hash']);
            $table->dropColumn('wa_id_hash');
        });

        Schema::table('conversaciones', function (Blueprint $table): void {
            $table->string('wa_id', 20)->change();
            $table->unique('wa_id');
            $table->string('nombre_perfil', 120)->nullable()->change();
            $table->json('ficha')->nullable()->change();
        });

        Schema::table('cotizaciones', function (Blueprint $table): void {
            $table->string('nombre', 80)->change();
            $table->string('apellido', 80)->change();
            $table->string('celular', 25)->change();
        });

        Schema::table('turnos', function (Blueprint $table): void {
            $table->dropIndex(['matricula_hash']);
            $table->dropColumn('matricula_hash');
        });

        Schema::table('turnos', function (Blueprint $table): void {
            $table->string('nombre')->change();
            $table->string('apellido')->change();
            $table->string('email')->nullable()->change();
            $table->string('celular')->change();
            $table->string('matricula', 12)->nullable()->change();
        });

        Schema::table('clientes', function (Blueprint $table): void {
            $table->dropUnique(['celular_hash']);
            $table->dropIndex(['email_hash']);
            $table->dropColumn(['celular_hash', 'email_hash']);
        });

        Schema::table('clientes', function (Blueprint $table): void {
            $table->string('nombre')->change();
            $table->string('apellido')->change();
            $table->string('email')->nullable()->change();
            $table->index('email');
            $table->string('celular')->change();
            $table->string('celular_normalizado')->change();
            $table->unique('celular_normalizado');
        });
    }
};
