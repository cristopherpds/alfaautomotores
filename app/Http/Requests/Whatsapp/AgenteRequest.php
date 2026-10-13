<?php

namespace App\Http\Requests\Whatsapp;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AgenteRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'activo' => ['required', 'boolean'],
            'nombre' => ['required', 'string', 'max:40'],
            'tono' => ['nullable', 'string', 'max:500'],
            'instrucciones' => ['nullable', 'string', 'max:6000'],
            'reglas_escalado' => ['nullable', 'string', 'max:3000'],
            'saludo' => ['nullable', 'string', 'max:500'],
        ];
    }
}
