<?php

use App\Ai\FormatoWhatsapp;
use App\Ai\Guardas;

test('markdown becomes whatsapp formatting', function (string $entrada, string $salida) {
    expect(FormatoWhatsapp::convertir($entrada))->toBe($salida);
})->with([
    'bold' => ['El precio es **USD 25.000**', 'El precio es *USD 25.000*'],
    'heading' => ['## Opciones', '*Opciones*'],
    'link' => ['[ver ficha](https://alfa.test/a)', 'ver ficha: https://alfa.test/a'],
    'url untouched' => ['Mirá https://alfa.test/vehiculos/toyota_hilux_2018.', 'Mirá https://alfa.test/vehiculos/toyota_hilux_2018.'],
    'whatsapp format kept' => ['*ya* _bien_', '*ya* _bien_'],
    'code fence' => ["```\nhola\n```", 'hola'],
    'table' => ["| Modelo | Precio |\n|---|---|\n| Hilux | USD 30.000 |", '- Hilux · Precio: USD 30.000'],
]);

test('it is idempotent', function () {
    $una = FormatoWhatsapp::convertir('**Hola** [link](https://x.test)');

    expect(FormatoWhatsapp::convertir($una))->toBe($una);
});

test('asking for a person is recognized in rioplatense spanish', function (string $texto, bool $pide) {
    expect(Guardas::pideHumano($texto))->toBe($pide);
})->with([
    ['quiero hablar con una persona', true],
    ['pasame con un vendedor', true],
    ['no quiero hablar con un bot', true],
    ['¿la persona que compra tiene garantía?', false],
    ['quiero la hilux', false],
]);

test('hostility counts only consecutive messages', function () {
    expect(Guardas::rachaHostil(['son unos chantas', 'estafadores', 'basura']))->toBe(3)
        ->and(Guardas::rachaHostil(['estafadores', 'perdón, me calenté', 'basura']))->toBe(1)
        ->and(Guardas::rachaHostil(['qué garrón, la puta madre']))->toBe(0);
});

test('internal notes and literal repetitions are not sent', function () {
    $previo = 'Tenemos la Hilux 2018 con 120.000 km a USD 30.000, ¿querés verla?';

    expect(Guardas::respuestaInvalida('(registro actualizado)', []))->not->toBeNull()
        ->and(Guardas::respuestaInvalida($previo, [$previo]))->not->toBeNull()
        ->and(Guardas::respuestaInvalida('Perfecto 👍', ['Perfecto 👍']))->toBeNull();
});
