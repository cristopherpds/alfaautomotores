<?php

namespace App\Http\Requests\Ajustes;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConexionIaRequest extends FormRequest
{
    /**
     * Cómo se llaman los modelos de voz a texto de OpenRouter
     * (`openai/whisper-1`, `openai/gpt-4o-transcribe`…).
     */
    public const MODELOS_DE_VOZ = '/whisper|transcri|speech-to-text/i';

    /**
     * Get the validation rules that apply to the request.
     *
     * Los modelos van con el nombre de OpenRouter (`proveedor/modelo`).
     * El de las notas de voz va al endpoint `audio/transcriptions`, que sólo
     * acepta modelos de voz a texto: un modelo de chat (aunque oiga audio,
     * como Gemini) responde «does not exist» y ninguna nota se transcribe.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'api_key' => ['nullable', 'string', 'max:300'],
            'modelo' => ['required', 'string', 'max:120', 'regex:/^[\w.-]+\/[\w.:-]+$/'],
            'modelo_transcripcion' => ['required', 'string', 'max:120', 'regex:/^[\w.-]+\/[\w.:-]+$/', function (string $atributo, mixed $valor, Closure $fallar): void {
                if (preg_match(self::MODELOS_DE_VOZ, (string) $valor) !== 1) {
                    $fallar(__('Tiene que ser un modelo de voz a texto, como openai/whisper-1 u openai/gpt-4o-mini-transcribe. Un modelo de chat no sirve acá.'));
                }
            }],
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
