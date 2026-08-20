<?php

namespace App\Http\Requests\Vehiculos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehiculoLoteDestroyRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehiculos' => ['required', 'array', 'min:1', 'max:100'],
            'vehiculos.*' => ['integer', 'distinct', Rule::exists('vehiculos', 'id')],
        ];
    }
}
