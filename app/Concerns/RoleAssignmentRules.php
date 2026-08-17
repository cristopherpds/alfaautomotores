<?php

namespace App\Concerns;

use App\Enums\UserRole;
use Illuminate\Validation\Validator;

trait RoleAssignmentRules
{
    /**
     * Reject a role the current user is not allowed to hand out.
     *
     * The policies cannot cover this: `UserPolicy::create()` never sees the
     * role being requested, so the administrator role is guarded here.
     * Leaving a role untouched is always allowed — otherwise an administrator
     * could not even save their own profile.
     */
    protected function validateAssignableRole(Validator $validator, ?UserRole $currentRole = null): void
    {
        $requestedRole = $this->input('role');

        if ($validator->errors()->has('role') || $requestedRole === $currentRole?->value) {
            return;
        }

        $assignable = array_map(
            fn (UserRole $role): string => $role->value,
            UserRole::assignableBy($this->user()),
        );

        if (! in_array($requestedRole, $assignable, true)) {
            $validator->errors()->add('role', __('Solo el dueño de la cuenta puede asignar el rol de administrador.'));
        }
    }
}
