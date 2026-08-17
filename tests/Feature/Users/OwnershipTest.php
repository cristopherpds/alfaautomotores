<?php

use App\Enums\UserRole;
use App\Models\User;

test('a plain admin cannot edit another admin', function () {
    $admin = User::factory()->admin()->create();
    $otroAdmin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('users.edit', $otroAdmin))
        ->assertForbidden();

    $this->actingAs($admin)
        ->put(route('users.update', $otroAdmin), [
            'name' => 'Nombre Nuevo',
            'email' => $otroAdmin->email,
            'role' => UserRole::Vendedor->value,
        ])
        ->assertForbidden();

    expect($otroAdmin->refresh()->role)->toBe(UserRole::Admin);
});

test('a plain admin cannot delete another admin', function () {
    $admin = User::factory()->admin()->create();
    $otroAdmin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete(route('users.destroy', $otroAdmin))
        ->assertForbidden();

    expect(User::find($otroAdmin->id))->not->toBeNull();
});

test('a plain admin cannot edit the owner', function () {
    $owner = User::factory()->owner()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('users.edit', $owner))
        ->assertForbidden();

    $this->actingAs($admin)
        ->put(route('users.update', $owner), [
            'name' => 'Nombre Nuevo',
            'email' => $owner->email,
            'role' => UserRole::Vendedor->value,
        ])
        ->assertForbidden();

    expect($owner->refresh()->name)->not->toBe('Nombre Nuevo');
});

test('nobody can delete the owner', function (bool $actorEsDueno) {
    $owner = User::factory()->owner()->create();
    $actor = $actorEsDueno ? $owner : User::factory()->admin()->create();

    $this->actingAs($actor)
        ->delete(route('users.destroy', $owner))
        ->assertForbidden();

    expect(User::find($owner->id))->not->toBeNull();
})->with([
    'otro admin' => false,
    'el propio dueño' => true,
]);

test('a plain admin cannot register an admin', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('users.create'))
        ->post(route('users.store'), [
            'name' => 'Ana Administradora',
            'email' => 'ana@alfaautomotores.test',
            'role' => UserRole::Admin->value,
            'password' => 'contrasena-segura',
            'password_confirmation' => 'contrasena-segura',
        ])
        ->assertSessionHasErrors('role');

    expect(User::count())->toBe(1);
});

test('a plain admin cannot promote another user to admin', function () {
    $admin = User::factory()->admin()->create();
    $vendedor = User::factory()->role(UserRole::Vendedor)->create();

    $this->actingAs($admin)
        ->from(route('users.edit', $vendedor))
        ->put(route('users.update', $vendedor), [
            'name' => $vendedor->name,
            'email' => $vendedor->email,
            'role' => UserRole::Admin->value,
        ])
        ->assertSessionHasErrors('role');

    expect($vendedor->refresh()->role)->toBe(UserRole::Vendedor);
});

test('a plain admin can still edit themselves', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('users.update', $admin), [
            'name' => 'Nombre Nuevo',
            'email' => $admin->email,
            'role' => UserRole::Admin->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('users.index'));

    expect($admin->refresh()->name)->toBe('Nombre Nuevo')
        ->and($admin->role)->toBe(UserRole::Admin);
});

test('a plain admin can still manage vendedores and equipo', function (UserRole $role) {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->role($role)->create();

    $this->actingAs($admin)
        ->put(route('users.update', $user), [
            'name' => 'Nombre Nuevo',
            'email' => $user->email,
            'role' => $role->value,
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->name)->toBe('Nombre Nuevo');

    $this->actingAs($admin)
        ->delete(route('users.destroy', $user))
        ->assertRedirect(route('users.index'));

    expect(User::find($user->id))->toBeNull();
})->with([
    'vendedor' => UserRole::Vendedor,
    'equipo' => UserRole::Equipo,
]);

test('the owner can register and delete admins', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('users.store'), [
            'name' => 'Ana Administradora',
            'email' => 'ana@alfaautomotores.test',
            'role' => UserRole::Admin->value,
            'password' => 'contrasena-segura',
            'password_confirmation' => 'contrasena-segura',
        ])
        ->assertSessionHasNoErrors();

    $admin = User::where('email', 'ana@alfaautomotores.test')->firstOrFail();

    expect($admin->role)->toBe(UserRole::Admin)
        ->and($admin->isOwner())->toBeFalse();

    $this->actingAs($owner)
        ->delete(route('users.destroy', $admin))
        ->assertRedirect(route('users.index'));

    expect(User::find($admin->id))->toBeNull();
});

test('the owner can edit and demote another admin', function () {
    $owner = User::factory()->owner()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($owner)
        ->put(route('users.update', $admin), [
            'name' => 'Nombre Nuevo',
            'email' => $admin->email,
            'role' => UserRole::Vendedor->value,
        ])
        ->assertSessionHasNoErrors();

    expect($admin->refresh()->name)->toBe('Nombre Nuevo')
        ->and($admin->role)->toBe(UserRole::Vendedor);
});

test('the owner keeps the ownership flag out of mass assignment', function () {
    $owner = User::factory()->owner()->create();
    $vendedor = User::factory()->role(UserRole::Vendedor)->create();

    $this->actingAs($owner)
        ->put(route('users.update', $vendedor), [
            'name' => $vendedor->name,
            'email' => $vendedor->email,
            'role' => UserRole::Vendedor->value,
            'is_owner' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($vendedor->refresh()->isOwner())->toBeFalse();
});

test('a plain admin only sees the roles they can assign', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('users.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('roles', 2)
            ->where('roles.0.value', UserRole::Vendedor->value)
            ->where('roles.1.value', UserRole::Equipo->value)
        );
});

test('the owner sees every role', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->get(route('users.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('roles', 3));
});

test('the edit form keeps the current role available even when it is not assignable', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('users.edit', $admin))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('roles', 3)
            ->where('user.is_owner', false)
            ->where('user.can.update', true)
            ->where('user.can.delete', false)
        );
});

test('the user list exposes the abilities of the acting user', function () {
    $owner = User::factory()->owner()->create(['name' => 'Aaa Dueño']);
    $admin = User::factory()->admin()->create(['name' => 'Bbb Admin']);
    User::factory()->role(UserRole::Vendedor)->create(['name' => 'Ccc Vendedor']);

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.0.is_owner', true)
            ->where('users.0.can.update', false)
            ->where('users.0.can.delete', false)
            ->where('users.1.can.update', true)
            ->where('users.1.can.delete', false)
            ->where('users.2.can.update', true)
            ->where('users.2.can.delete', true)
        );

    expect($owner->isOwner())->toBeTrue();
});
