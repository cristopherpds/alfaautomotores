<?php

namespace App\Http\Requests\Ajustes;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SitioPublicoRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Vacía vuelve a valer `APP_URL`.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'url_publica' => ['nullable', 'string', 'max:200', 'url:http,https'],
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
            'url_publica.url' => __('Escribila completa, con https:// adelante (https://alfaautomotores.com.uy).'),
        ];
    }
}
