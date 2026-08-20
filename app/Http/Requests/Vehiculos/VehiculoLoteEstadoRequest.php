<?php

namespace App\Http\Requests\Vehiculos;

use App\Enums\EstadoVehiculo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehiculoLoteEstadoRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Llegan los ids de las filas tildadas en la tabla. El tope es holgado a
     * propósito: la tabla manda como mucho una página, y sólo frena un pedido
     * armado a mano con miles de ids.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehiculos' => ['required', 'array', 'min:1', 'max:100'],
            'vehiculos.*' => ['integer', 'distinct', Rule::exists('vehiculos', 'id')],
            'estado' => ['required', Rule::enum(EstadoVehiculo::class)],
        ];
    }
}
