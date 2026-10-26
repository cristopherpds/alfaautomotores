<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cifrado de los datos personales
    |--------------------------------------------------------------------------
    |
    | Clave propia (32 bytes en base64, con o sin el prefijo «base64:») para
    | los casts `encrypted` de los modelos, con AES-256-GCM. Es otra que la
    | APP_KEY: rotar la de la aplicación no deja ilegibles a los clientes, y
    | los secretos de `Ajuste` siguen con la suya.
    |
    | Generar: php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
    |
    */

    'clave' => env('CIFRADO_KEY'),

    'cipher' => 'aes-256-gcm',

    /*
    |--------------------------------------------------------------------------
    | Índice ciego
    |--------------------------------------------------------------------------
    |
    | Clave del HMAC-SHA256 con el que se buscan las columnas cifradas
    | (`*_hash`). Cambiarla obliga a recalcular todos los hashes.
    |
    */

    'indice_ciego' => env('INDICE_CIEGO_KEY'),

];
