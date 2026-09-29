<?php

namespace App\Http\Requests\Clientes;

use App\Concerns\ClienteValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ClienteStoreRequest extends FormRequest
{
    use ClienteValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->clienteRules();
    }

    /**
     * Get the additional validation callbacks that should run.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [$this->celularSinRepetir()];
    }
}
