---
paths:
  - 'app/Policies/**'
---

# Policies

## El dueño de la cuenta vive en users.is_owner, no en el enum de roles
`is_owner` es una columna booleana ortogonal a `role`: el dueño es un admin con el flag. Se descartó un `UserRole::Owner` para no tener que excluirlo del select ni reescribir `isAdmin()` en todos lados.
Dos reglas en `UserPolicy`: el dueño solo se edita a sí mismo y no lo puede borrar nadie (ni él); un admin común no puede editar ni borrar a otro admin, solo el dueño.
`is_owner` está deliberadamente FUERA del atributo `#[Fillable]` de `User` — es la única barrera contra mass assignment desde `UserStoreRequest`/`UserUpdateRequest`. Hoy solo lo setean la migración y `UserFactory::owner()`.
"Solo el dueño asigna el rol admin" NO puede ir en la policy (`create()` nunca ve el rol pedido): vive en `App\Concerns\RoleAssignmentRules`, invocado desde el `after()` de ambos form requests. Ojo: dejar el rol sin cambios siempre se permite, o un admin común no podría ni guardar su propio perfil.
`UserController::toListItem()` manda `can.update` / `can.delete` al frontend resueltos con la policy real, así la tabla nunca duplica las reglas en TypeScript.
