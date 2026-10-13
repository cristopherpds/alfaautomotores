<?php

namespace App\Http\Requests\Ajustes;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConexionIaRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Los modelos van con el nombre de OpenRouter (`proveedor/modelo`).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'api_key' => ['nullable', 'string', 'max:300'],
            'modelo' => ['required', 'string', 'max:120', 'regex:/^[\w.-]+\/[\w.:-]+$/'],
            'modelo_transcripcion' => ['required', 'string', 'max:120', 'regex:/^[\w.-]+\/[\w.:-]+$/'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'modelo.regex' => __('Escribilo como en OpenRouter: proveedor/modelo (google/gemini-2.5-flash).'),
            'modelo_transcripcion.regex' => __('Escribilo como en OpenRouter: proveedor/modelo.'),
        ];
    }
}
