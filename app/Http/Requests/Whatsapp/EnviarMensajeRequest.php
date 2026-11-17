<?php

namespace App\Http\Requests\Whatsapp;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EnviarMensajeRequest extends FormRequest
{
    /**
     * Tope de WhatsApp para un texto.
     */
    public const MAX_TEXTO = 4096;

    /**
     * Get the validation rules that apply to the request.
     *
     * Un texto, un archivo o las dos cosas: con archivo, el texto va de
     * epígrafe. Los topes son los de Meta (5 MB imágenes, 100 MB documentos);
     * acá se corta en 16 MB para no pedirle tanto a PHP.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cuerpo' => ['nullable', 'required_without:archivo', 'string', 'max:'.self::MAX_TEXTO],
            'archivo' => ['nullable', 'file', 'max:16384', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx'],
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
            'cuerpo.required_without' => __('Escribí un mensaje o adjuntá un archivo.'),
            'archivo.mimes' => __('Se pueden mandar fotos (JPG, PNG), PDF, Word o Excel.'),
        ];
    }
}
