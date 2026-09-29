<?php

namespace App\Http\Requests\Clientes;

use App\Concerns\ClienteValidationRules;
use App\Models\Cliente;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ClienteUpdateRequest extends FormRequest
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
        return [$this->celularSinRepetir($this->targetCliente()->id)];
    }

    /**
     * Get the client being updated.
     */
    private function targetCliente(): Cliente
    {
        $cliente = $this->route('cliente');

        if (! $cliente instanceof Cliente) {
            throw new NotFoundHttpException;
        }

        return $cliente;
    }
}
