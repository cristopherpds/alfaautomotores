<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Users\UserStoreRequest;
use App\Http\Requests\Users\UserUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.User::class, only: ['index']),
            new Middleware('can:create,'.User::class, only: ['create', 'store']),
            new Middleware('can:update,user', only: ['edit', 'update']),
            new Middleware('can:delete,user', only: ['destroy']),
        ];
    }

    /**
     * Show the list of registered users.
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();

        return Inertia::render('users/index', [
            'users' => User::query()
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => $this->toListItem($user, $actor))
                ->all(),
            'roles' => UserRole::options(UserRole::assignableBy($actor)),
        ]);
    }

    /**
     * Show the form to register a new user.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('users/create', [
            'roles' => UserRole::options(UserRole::assignableBy($request->user())),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Register a new user.
     */
    public function store(UserStoreRequest $request): RedirectResponse
    {
        User::create($request->validated())->markEmailAsVerified();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Usuario creado.')]);

        return to_route('users.index');
    }

    /**
     * Show the form to edit an existing user.
     */
    public function edit(Request $request, User $user): Response
    {
        $actor = $request->user();
        $assignable = UserRole::assignableBy($actor);

        return Inertia::render('users/edit', [
            'user' => $this->toListItem($user, $actor),
            'roles' => UserRole::options(array_values(array_filter(
                UserRole::cases(),
                fn (UserRole $role): bool => in_array($role, $assignable, true) || $role === $user->role,
            ))),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Update the given user and their role.
     */
    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $attributes = $request->safe()->except('password');

        if ($request->filled('password')) {
            $attributes['password'] = $request->string('password')->value();
        }

        $emailChanged = $user->email !== $attributes['email'];

        $user->update($attributes);

        if ($emailChanged) {
            $user->markEmailAsVerified();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Usuario actualizado.')]);

        return to_route('users.index');
    }

    /**
     * Delete the given user.
     */
    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Usuario eliminado.')]);

        return to_route('users.index');
    }

    /**
     * Format a user for the frontend, resolving the abilities the acting user
     * has over them so the UI never has to duplicate the policy.
     *
     * @return array{id: int, name: string, email: string, role: string, is_owner: bool, created_at: string|null, can: array{update: bool, delete: bool}}
     */
    private function toListItem(User $user, User $actor): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'is_owner' => $user->isOwner(),
            'created_at' => $user->created_at?->toIso8601String(),
            'can' => [
                'update' => $actor->can('update', $user),
                'delete' => $actor->can('delete', $user),
            ],
        ];
    }
}
