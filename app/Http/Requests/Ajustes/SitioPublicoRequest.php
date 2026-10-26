<?php

namespace App\Http\Requests\Ajustes;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SitioPublicoRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Vacía vuelve a valer `APP_URL`. Sólo el origen (esquema, dominio y
     * puerto): los links se arman pegándole la ruta, así que una ruta, una
     * consulta o un ancla los romperían.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'url_publica' => ['nullable', 'string', 'max:200', 'url:http,https', function (string $atributo, mixed $valor, Closure $fail): void {
                $partes = parse_url((string) $valor);

                if ($partes === false) {
                    return;
                }

                if (isset($partes['user']) || isset($partes['query']) || isset($partes['fragment']) || ! in_array($partes['path'] ?? '', ['', '/'], true)) {
                    $fail(__('Sólo la dirección del sitio, sin nada después del dominio (https://alfaautomotores.com.uy, no https://alfaautomotores.com.uy/catalogo).'));
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
            'url_publica.url' => __('Escribila completa, con https:// adelante (https://alfaautomotores.com.uy).'),
        ];
    }
}
