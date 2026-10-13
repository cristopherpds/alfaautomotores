<?php

namespace App\Http\Requests\Ajustes;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConexionWhatsappRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Los secretos (`token`, `app_secret`) son opcionales: vacíos dejan el
     * que ya estaba guardado.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'token' => ['nullable', 'string', 'max:1000'],
            'app_secret' => ['nullable', 'string', 'max:200'],
            'phone_number_id' => ['nullable', 'string', 'regex:/^\d{5,30}$/'],
            'waba_id' => ['nullable', 'string', 'regex:/^\d{5,30}$/'],
            /* Va en la URL del webhook: sin caracteres que haya que escapar. */
            'verify_token' => ['nullable', 'string', 'min:16', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'graph_version' => ['nullable', 'string', 'regex:/^v\d{2}\.\d$/'],
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
            'phone_number_id.regex' => __('El identificador del número son sólo dígitos.'),
            'waba_id.regex' => __('El identificador de la cuenta son sólo dígitos.'),
            'verify_token.min' => __('Usá un token de al menos 16 caracteres: va en la URL del webhook.'),
            'verify_token.regex' => __('Sólo letras, números, guiones y guiones bajos.'),
            'graph_version.regex' => __('Tiene que tener la forma v23.0.'),
        ];
    }
}
