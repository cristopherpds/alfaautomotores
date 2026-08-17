---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Authorize with HasMiddleware, not authorizeResource
The base `App\Http\Controllers\Controller` is empty (no AuthorizesRequests), so `$this->authorize()` / `authorizeResource()` fail at runtime with "Call to undefined method middleware()".
Authorize by implementing `Illuminate\Routing\Controllers\HasMiddleware` and returning `new Middleware('can:ability,Model|routeParam', only: [...])` — see UserController.
Roles live in the `role` column on users, cast to `App\Enums\UserRole`; check with `$user->isAdmin()` / `hasRole()` and gate through `App\Policies\UserPolicy`.
