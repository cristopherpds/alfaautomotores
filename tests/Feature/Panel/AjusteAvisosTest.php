<?php

use App\Enums\UserRole;
use App\Models\Ajuste;
use App\Models\User;

/*
 * Ajustes › Avisos por email: qué emails reciben los clientes. Sólo admins.
 */

test('the customer confirmation email starts off', function () {
    expect(Ajuste::avisos())->toBe(['confirmacion_turno' => false]);
});

test('an admin sees and turns on the turn confirmation email', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('panel.ajustes.avisos'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('panel/ajustes/avisos')
            ->where('avisos.confirmacion_turno', false)
        );

    $this->actingAs($admin)
        ->from(route('panel.ajustes.avisos'))
        ->put(route('panel.ajustes.avisos.update'), ['confirmacion_turno' => true])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('panel.ajustes.avisos'));

    expect(Ajuste::avisos()['confirmacion_turno'])->toBeTrue();
});

test('sellers cannot change the email notices', function () {
    $vendedor = User::factory()->role(UserRole::Vendedor)->create();

    $this->actingAs($vendedor)->get(route('panel.ajustes.avisos'))->assertForbidden();
    $this->actingAs($vendedor)
        ->put(route('panel.ajustes.avisos.update'), ['confirmacion_turno' => true])
        ->assertForbidden();

    expect(Ajuste::avisos()['confirmacion_turno'])->toBeFalse();
});

test('the switch has to be a boolean', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('panel.ajustes.avisos.update'), ['confirmacion_turno' => 'quizás'])
        ->assertSessionHasErrors('confirmacion_turno');
});
