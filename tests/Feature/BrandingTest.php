<?php

/*
 * El favicon y el isotipo del panel salen de archivos servidos por ruta fija
 * (`resources/views/app.blade.php` y `resources/js/components/app-logo-icon.tsx`).
 * Si alguno se pierde, la página sigue cargando y el fallo pasa desapercibido:
 * queda el icono genérico del navegador o una imagen rota en el sidebar.
 */

test('the head only links the Alfa icons', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<link rel="icon" href="/favicon.ico" sizes="any">', false)
        ->assertSee('<link rel="apple-touch-icon" href="/apple-touch-icon.png">', false)
        ->assertDontSee('favicon.svg');
});

test('the icon files ship with the public assets', function () {
    expect(public_path('favicon.ico'))->toBeFile()
        ->and(public_path('apple-touch-icon.png'))->toBeFile()
        ->and(public_path('assets/isotipo-alfa.png'))->toBeFile()
        ->and(public_path('favicon.svg'))->not->toBeFile();
});

test('the favicon is a well formed ICO container', function () {
    $cabecera = file_get_contents(public_path('favicon.ico'), length: 6);

    // Reservado (0), tipo ICO (1) y la cantidad de tamaños que empaquetamos.
    expect($cabecera)->toBe("\x00\x00\x01\x00\x03\x00");
});
