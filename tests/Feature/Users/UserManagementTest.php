<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('admins can see the user list', function () {
    $admin = User::factory()->admin()->create();
    $vendedor = User::factory()->role(UserRole::Vendedor)->create();

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('users/index')
            ->has('users', 2)
            ->has('roles', 3)
        );

    expect($vendedor->role)->toBe(UserRole::Vendedor);
});

test('non admins cannot see the user list', function (UserRole $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)
        ->get(route('users.index'))
        ->assertForbidden();
})->with([
    'vendedor' => UserRole::Vendedor,
    'equipo' => UserRole::Equipo,
]);

test('guests are redirected to the login page', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});

test('admins can register a user with a role', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Ana Vendedora',
            'email' => 'ana@alfaautomotores.test',
            'role' => UserRole::Vendedor->value,
            'password' => 'contrasena-segura',
            'password_confirmation' => 'contrasena-segura',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('users.index'));

    $user = User::where('email', 'ana@alfaautomotores.test')->firstOrFail();

    expect($user->role)->toBe(UserRole::Vendedor)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('contrasena-segura', $user->password))->toBeTrue();
});

test('the role must be one of the supported roles', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('users.create'))
        ->post(route('users.store'), [
            'name' => 'Ana Vendedora',
            'email' => 'ana@alfaautomotores.test',
            'role' => 'gerente',
            'password' => 'contrasena-segura',
            'password_confirmation' => 'contrasena-segura',
        ])
        ->assertSessionHasErrors('role');

    expect(User::count())->toBe(1);
});

test('non admins cannot register users', function () {
    $vendedor = User::factory()->role(UserRole::Vendedor)->create();

    $this->actingAs($vendedor)
        ->post(route('users.store'), [
            'name' => 'Ana Vendedora',
            'email' => 'ana@alfaautomotores.test',
            'role' => UserRole::Admin->value,
            'password' => 'contrasena-segura',
            'password_confirmation' => 'contrasena-segura',
        ])
        ->assertForbidden();

    expect(User::count())->toBe(1);
});

test('admins can change the role of another user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->role(UserRole::Equipo)->create();

    $this->actingAs($admin)
        ->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => UserRole::Vendedor->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('users.index'));

    expect($user->refresh()->role)->toBe(UserRole::Vendedor);
});

test('the password is only updated when one is provided', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->role(UserRole::Equipo)->create();
    $originalPassword = $user->password;

    $this->actingAs($admin)
        ->put(route('users.update', $user), [
            'name' => 'Nombre Nuevo',
            'email' => $user->email,
            'role' => UserRole::Equipo->value,
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->password)->toBe($originalPassword)
        ->and($user->name)->toBe('Nombre Nuevo');

    $this->actingAs($admin)
        ->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => UserRole::Equipo->value,
            'password' => 'otra-contrasena-segura',
            'password_confirmation' => 'otra-contrasena-segura',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('otra-contrasena-segura', $user->refresh()->password))->toBeTrue();
});

test('admins cannot remove their own admin role', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('users.edit', $admin))
        ->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => UserRole::Vendedor->value,
        ])
        ->assertSessionHasErrors('role');

    expect($admin->refresh()->role)->toBe(UserRole::Admin);
});

test('admins can delete another user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->role(UserRole::Equipo)->create();

    $this->actingAs($admin)
        ->delete(route('users.destroy', $user))
        ->assertRedirect(route('users.index'));

    expect(User::find($user->id))->toBeNull();
});

test('admins cannot delete themselves', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete(route('users.destroy', $admin))
        ->assertForbidden();

    expect(User::find($admin->id))->not->toBeNull();
});
