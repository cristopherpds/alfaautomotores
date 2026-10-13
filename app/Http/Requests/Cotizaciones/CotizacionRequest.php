<?php

namespace App\Http\Requests\Cotizaciones;

use App\Models\Cotizacion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Una solicitud de «Cotizá tu auto» desde el sitio público.
 *
 * La ruta es pública: cualquiera puede pedir cotización, no hay `authorize()`.
 */
class CotizacionRequest extends FormRequest
{
    /**
     * El año más viejo que ofrece el selector.
     */
    public const ANIO_MINIMO = 1980;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $fotos = [];

        foreach (array_keys(Cotizacion::FOTOS) as $lado) {
            // Mismo tope que las entregas: 4 MB por foto, sin HEIC.
            $fotos['foto_'.$lado] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];
        }

        return [
            'marca' => ['required', 'string', 'max:60'],
            'modelo' => ['required', 'string', 'max:60'],
            'anio' => ['required', 'integer', 'between:'.self::ANIO_MINIMO.','.(now()->year + 1)],
            'kilometros' => ['required', 'integer', 'min:0', 'max:2000000'],
            ...$fotos,
            'detalles' => ['nullable', 'string', 'max:1000'],

            'nombre' => ['required', 'string', 'max:80'],
            'apellido' => ['required', 'string', 'max:80'],
            /* Laxa a propósito, como en la reserva de turnos: se anotan como
               `099 123 456`, `+598 99…` o con guiones. */
            'celular' => ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s().]{6,25}$/'],
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
            'celular.regex' => __('Escribí el teléfono sólo con números, espacios o guiones.'),
            'foto_*.image' => __('Tiene que ser una foto (JPG, PNG o WebP).'),
            'foto_*.mimes' => __('Tiene que ser una foto (JPG, PNG o WebP).'),
            'foto_*.max' => __('La foto pesa más de 4 MB.'),
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Los kilómetros se escriben con puntos de miles («85.000»).
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('kilometros'))) {
            $this->merge(['kilometros' => str_replace(['.', ' '], '', $this->input('kilometros'))]);
        }
    }
}
