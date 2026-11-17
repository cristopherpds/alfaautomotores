<?php

use App\Support\Cifrado;
use App\Support\IndiceCiego;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cifra las filas que ya había y calcula su índice ciego.
 *
 * Va con `DB::table` y no con los modelos a propósito: el cast `encrypted`
 * revienta al leer un valor que todavía está en claro. Las columnas van
 * escritas acá (y no sacadas de los casts) para que la migración siga
 * haciendo lo mismo aunque los modelos cambien.
 *
 * Es idempotente: un valor que ya descifra con `CIFRADO_KEY` no se vuelve a
 * cifrar, así que correrla dos veces (o después de que la app ya guardó filas
 * cifradas) no rompe nada. Los JSON se cifran tal cual, que es como los
 * guarda el cast `encrypted:array`.
 */
return new class extends Migration
{
    /**
     * Las columnas cifradas de cada tabla.
     *
     * @var array<string, list<string>>
     */
    private const COLUMNAS = [
        'clientes' => ['nombre', 'apellido', 'email', 'celular', 'celular_normalizado', 'notas'],
        'turnos' => ['nombre', 'apellido', 'email', 'celular', 'matricula', 'comentario'],
        'cotizaciones' => ['nombre', 'apellido', 'celular', 'detalles'],
        'conversaciones' => ['wa_id', 'nombre_perfil', 'ficha'],
        'mensajes_whatsapp' => ['cuerpo', 'media_nombre', 'error', 'meta'],
        'auditorias' => ['etiqueta', 'cambios'],
    ];

    public function up(): void
    {
        $cifrado = app(Cifrado::class);

        foreach (self::COLUMNAS as $tabla => $columnas) {
            DB::table($tabla)->chunkById(200, function ($filas) use ($tabla, $columnas, $cifrado): void {
                foreach ($filas as $fila) {
                    $claro = [];
                    $cambios = [];

                    foreach ($columnas as $columna) {
                        $valor = $fila->{$columna};

                        if ($valor === null) {
                            $claro[$columna] = null;

                            continue;
                        }

                        if ($cifrado->estaCifrado($valor)) {
                            $claro[$columna] = $cifrado->decryptString($valor);
                        } else {
                            $claro[$columna] = (string) $valor;
                            $cambios[$columna] = $cifrado->encryptString((string) $valor);
                        }
                    }

                    foreach ($this->hashes($tabla, $claro) as $columna => $hash) {
                        if ($fila->{$columna} !== $hash) {
                            $cambios[$columna] = $hash;
                        }
                    }

                    if ($cambios !== []) {
                        DB::table($tabla)->where('id', $fila->id)->update($cambios);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        $cifrado = app(Cifrado::class);

        foreach (self::COLUMNAS as $tabla => $columnas) {
            DB::table($tabla)->chunkById(200, function ($filas) use ($tabla, $columnas, $cifrado): void {
                foreach ($filas as $fila) {
                    $cambios = [];

                    foreach ($columnas as $columna) {
                        if ($cifrado->estaCifrado($fila->{$columna})) {
                            $cambios[$columna] = $cifrado->decryptString($fila->{$columna});
                        }
                    }

                    if ($cambios !== []) {
                        DB::table($tabla)->where('id', $fila->id)->update($cambios);
                    }
                }
            });
        }
    }

    /**
     * El índice ciego de una fila, a partir de sus datos en claro.
     *
     * @param  array<string, string|null>  $claro
     * @return array<string, string|null>
     */
    private function hashes(string $tabla, array $claro): array
    {
        return match ($tabla) {
            'clientes' => [
                'celular_hash' => IndiceCiego::deCelular($claro['celular_normalizado'] ?? $claro['celular'] ?? ''),
                'email_hash' => IndiceCiego::deEmail($claro['email']),
            ],
            'conversaciones' => ['wa_id_hash' => IndiceCiego::deCelular($claro['wa_id'] ?? '')],
            'turnos' => ['matricula_hash' => IndiceCiego::deMatricula($claro['matricula'])],
            default => [],
        };
    }
};
