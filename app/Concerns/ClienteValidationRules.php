<?php

namespace App\Concerns;

use App\Models\Cliente;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

trait ClienteValidationRules
{
    /**
     * Reglas de la ficha de un cliente, compartidas por el alta y la edición.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function clienteRules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:80'],
            'apellido' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:120'],
            // Se cuentan los dígitos, no los caracteres: «099 123 456» y
            // «099-123-456» son válidos; «abc» no.
            'celular' => ['required', 'string', 'max:25', function (string $atributo, mixed $valor, Closure $falla): void {
                if (strlen(Cliente::normalizarCelular((string) $valor)) < 6) {
                    $falla(__('El celular tiene que tener al menos 6 dígitos.'));
                }
            }],
            'acepta_novedades' => ['boolean'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * El celular es la identidad del cliente: otro con los mismos dígitos es
     * la misma persona cargada dos veces.
     *
     * Se compara normalizado, por eso no alcanza con una regla `unique`
     * sobre lo que se escribió.
     */
    protected function celularSinRepetir(?int $ignorarId = null): Closure
    {
        return function (Validator $validator) use ($ignorarId): void {
            if ($validator->errors()->has('celular')) {
                return;
            }

            $existe = Cliente::query()
                ->where('celular_normalizado', Cliente::normalizarCelular((string) $this->input('celular')))
                ->when($ignorarId, fn ($query, int $id) => $query->whereKeyNot($id))
                ->exists();

            if ($existe) {
                $validator->errors()->add('celular', __('Ya hay un cliente con ese celular.'));
            }
        };
    }
}
