<?php

namespace App\Support;

/**
 * El índice ciego de las columnas cifradas: HMAC-SHA256 con
 * `INDICE_CIEGO_KEY` sobre el valor normalizado.
 *
 * Un dato cifrado con GCM sale distinto cada vez, así que no se puede buscar
 * con `where`. Se guarda al lado su HMAC (`celular_hash`, `email_hash`,
 * `wa_id_hash`, `matricula_hash`) y se busca por igualdad exacta contra ese.
 * Normalizar acá y no en cada llamada es lo que hace que «099 123 456» y
 * «099123456» den el mismo hash.
 */
final class IndiceCiego
{
    /**
     * El hash de un valor que ya viene normalizado.
     */
    public static function de(string $valor): string
    {
        return hash_hmac('sha256', $valor, self::clave());
    }

    /**
     * El hash de un celular o un wa_id: sólo los dígitos.
     */
    public static function deCelular(string $celular): string
    {
        return self::de((string) preg_replace('/\D+/', '', $celular));
    }

    /**
     * El hash de un email, en minúsculas y sin espacios alrededor.
     */
    public static function deEmail(?string $email): ?string
    {
        $email = $email === null ? '' : mb_strtolower(trim($email));

        return $email === '' ? null : self::de($email);
    }

    /**
     * El hash de una matrícula, en mayúsculas y sin espacios ni guiones:
     * «abc 1234» y «ABC-1234» son el mismo auto.
     */
    public static function deMatricula(?string $matricula): ?string
    {
        $matricula = self::normalizarMatricula($matricula);

        return $matricula === '' ? null : self::de($matricula);
    }

    public static function normalizarMatricula(?string $matricula): string
    {
        return mb_strtoupper((string) preg_replace('/[^\pL\pN]+/u', '', (string) $matricula));
    }

    private static function clave(): string
    {
        return Cifrado::decodificarClave(config('cifrado.indice_ciego'), 'INDICE_CIEGO_KEY');
    }
}
