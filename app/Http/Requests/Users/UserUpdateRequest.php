<?php

namespace App\Http\Requests\Users;

use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->targetUser()->id),
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
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
            function (Validator $validator): void {
                $target = $this->targetUser();

                if ($this->user()->is($target) && $this->input('role') !== UserRole::Admin->value) {
                    $validator->errors()->add('role', __('No podés quitarte a vos mismo el rol de administrador.'));
                }
            },
        ];
    }

    /**
     * Get the user being updated.
     */
    private function targetUser(): User
    {
        $user = $this->route('user');

        if (! $user instanceof User) {
            throw new NotFoundHttpException;
        }

        return $user;
    }
}
