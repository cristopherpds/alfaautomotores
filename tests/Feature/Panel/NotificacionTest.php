<?php

use App\Models\Cotizacion;
use App\Models\User;
use App\Notifications\NuevaCotizacion;

/*
 * La campana del panel: cada usuario ve y marca sólo sus avisos.
 */

/**
 * Un aviso de cotización guardado para el usuario, como lo deja la cola.
 */
function avisoDeCotizacionPara(User $usuario): string
{
    $usuario->notify(new NuevaCotizacion(Cotizacion::factory()->create([
        'marca' => 'Toyota', 'modelo' => 'Corolla', 'anio' => 2018,
    ])));

    return $usuario->notifications()->latest()->value('id');
}

test('the bell lists the user notices with the unread count', function () {
    $usuario = User::factory()->admin()->create();
    avisoDeCotizacionPara($usuario);

    $this->actingAs($usuario)
        ->getJson(route('panel.notificaciones.index'))
        ->assertOk()
        ->assertJsonPath('noLeidas', 1)
        ->assertJsonCount(1, 'avisos')
        ->assertJsonPath('avisos.0.tipo', 'cotizacion')
        ->assertJsonPath('avisos.0.titulo', 'Nueva cotización')
        ->assertJsonPath('avisos.0.leida', false);
});

test('marking a notice as read lowers the count', function () {
    $usuario = User::factory()->admin()->create();
    $id = avisoDeCotizacionPara($usuario);
    avisoDeCotizacionPara($usuario);

    $this->actingAs($usuario)
        ->patchJson(route('panel.notificaciones.leida', $id))
        ->assertOk()
        ->assertJsonPath('noLeidas', 1);

    expect($usuario->notifications()->find($id)->read_at)->not->toBeNull();
});

test('marking all as read clears the count', function () {
    $usuario = User::factory()->admin()->create();
    avisoDeCotizacionPara($usuario);
    avisoDeCotizacionPara($usuario);

    $this->actingAs($usuario)
        ->postJson(route('panel.notificaciones.leer-todas'))
        ->assertOk()
        ->assertJsonPath('noLeidas', 0);
});

test('nobody can read or mark the notices of someone else', function () {
    $duenio = User::factory()->admin()->create();
    $otro = User::factory()->admin()->create();
    $id = avisoDeCotizacionPara($duenio);

    $this->actingAs($otro)
        ->getJson(route('panel.notificaciones.index'))
        ->assertJsonPath('noLeidas', 0)
        ->assertJsonCount(0, 'avisos');

    $this->actingAs($otro)
        ->patchJson(route('panel.notificaciones.leida', $id))
        ->assertNotFound();

    expect($duenio->notifications()->find($id)->read_at)->toBeNull();
});

test('guests cannot see the bell', function () {
    $this->getJson(route('panel.notificaciones.index'))->assertUnauthorized();
});
