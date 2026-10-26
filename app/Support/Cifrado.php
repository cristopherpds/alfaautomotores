<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Encryption\Encrypter;
use RuntimeException;

/**
 * El cifrador de los datos personales: AES-256-GCM con `CIFRADO_KEY`.
 *
 * Es el que usan los casts `encrypted` de todos los modelos
 * (`Model::encryptUsing()` en `AppServiceProvider`). Arma el cifrador real
 * recién al primer uso: sin la clave, `artisan` sigue andando y lo que falla
 * es leer o guardar un dato cifrado, con un mensaje que dice qué falta (y no
 * en silencio con la APP_KEY, que dejaría datos cifrados con otra clave).
 */
class Cifrado implements EncrypterContract, StringEncrypter
{
    private ?Encrypter $encrypter = null;

    /**
     * El cifrador real, armado con la clave de `config/cifrado.php`.
     */
    public function encrypter(): Encrypter
    {
        return $this->encrypter ??= new Encrypter(
            self::decodificarClave(config('cifrado.clave'), 'CIFRADO_KEY'),
            (string) config('cifrado.cipher', 'aes-256-gcm'),
        );
    }

    /**
     * Si un valor guardado ya está cifrado con esta clave.
     */
    public function estaCifrado(?string $valor): bool
    {
        if ($valor === null || $valor === '') {
            return false;
        }

        try {
            $this->decryptString($valor);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    /**
     * Una clave de `.env` en bytes: base64, con o sin el prefijo «base64:».
     */
    public static function decodificarClave(mixed $clave, string $variable): string
    {
        $clave = is_string($clave) ? trim($clave) : '';

        if ($clave === '') {
            throw new RuntimeException("Falta {$variable} en el .env: es la clave de los datos personales cifrados. Generala con: php -r \"echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;\"");
        }

        $bytes = base64_decode(str_starts_with($clave, 'base64:') ? substr($clave, 7) : $clave, true);

        if ($bytes === false || strlen($bytes) !== 32) {
            throw new RuntimeException("{$variable} tiene que ser de 32 bytes en base64.");
        }

        return $bytes;
    }

    public function encrypt(#[\SensitiveParameter] $value, $serialize = true): string
    {
        return $this->encrypter()->encrypt($value, $serialize);
    }

    public function decrypt($payload, $unserialize = true): mixed
    {
        return $this->encrypter()->decrypt($payload, $unserialize);
    }

    public function encryptString(#[\SensitiveParameter] $value): string
    {
        return $this->encrypter()->encryptString($value);
    }

    public function decryptString($payload): string
    {
        return $this->encrypter()->decryptString($payload);
    }

    public function getKey(): string
    {
        return $this->encrypter()->getKey();
    }

    /**
     * @return array<int, string>
     */
    public function getAllKeys(): array
    {
        return $this->encrypter()->getAllKeys();
    }

    /**
     * @return array<int, string>
     */
    public function getPreviousKeys(): array
    {
        return $this->encrypter()->getPreviousKeys();
    }
}
