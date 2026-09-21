<?php

namespace App\Http\Requests\Entregas;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EntregaFechaRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Corregir la fecha es lo único editable de una entrega: la foto no se
     * reemplaza, se borra y se sube de nuevo.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }
}
