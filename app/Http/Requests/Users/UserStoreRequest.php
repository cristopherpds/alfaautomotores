<?php

namespace App\Http\Requests\Users;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Concerns\RoleAssignmentRules;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserStoreRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules, RoleAssignmentRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * Get the additional validation callbacks that should run.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateAssignableRole($validator),
        ];
    }
}
