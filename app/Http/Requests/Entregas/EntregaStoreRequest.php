<?php

namespace App\Http\Requests\Entregas;

use App\Models\Entrega;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EntregaStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Sin `authorize()`: lo cubre el middleware del controlador.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /* `date_format` y no `date` a secas: es lo que manda un
               `<input type="date">` y lo que espera el cast del modelo. Una
               entrega futura no existe. */
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'fotos' => ['required', 'array', 'min:1', 'max:'.Entrega::MAX_POR_LOTE],
            'fotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }
}
