<?php

namespace App\Http\Requests\HeroSlides;

use App\Enums\PosicionTexto;
use App\Enums\TipoFondo;
use App\Models\HeroSlide;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HeroSlideRequest extends FormRequest
{
    /**
     * Un destino que el botón sabe abrir: una ruta del sitio (`/catalogo`) o
     * una dirección completa (`https://wa.me/…`, `mailto:`, `tel:`).
     */
    private const DESTINO = '/^(\/(?!\/)|https?:\/\/|mailto:|tel:)\S*$/i';

    /**
     * Get the validation rules that apply to the request.
     *
     * Sirve para el alta y la edición. Sin `authorize()`: lo cubre el
     * middleware.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $slide = $this->route('slide');
        $tipo = TipoFondo::tryFrom((string) $this->input('tipo_fondo'));

        /* El fondo se exige al crear y al cambiar de imagen a video (o al
           revés): si no, el slide quedaría con un tipo que no es el de su
           archivo. Al editar sin cambiar el tipo, se conserva el que estaba. */
        $exigeFondo = ! $slide instanceof HeroSlide || $slide->tipo_fondo !== $tipo;

        return [
            'tipo_fondo' => ['required', Rule::enum(TipoFondo::class)],
            'fondo' => [
                $exigeFondo ? 'required' : 'nullable',
                'file',
                ...match ($tipo) {
                    /* El video pesa más que una foto y php.ini tiene que
                       acompañar (`upload_max_filesize`, `post_max_size`). */
                    TipoFondo::Video => ['mimetypes:video/mp4,video/webm', 'max:20480'],
                    default => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
                },
            ],
            'eyebrow' => ['nullable', 'string', 'max:60'],
            'titulo' => ['required', 'string', 'max:120'],
            'bajada' => ['nullable', 'string', 'max:240'],
            'posicion' => ['required', Rule::enum(PosicionTexto::class)],
            'boton1_texto' => ['nullable', 'string', 'max:40', 'required_with:boton1_url'],
            'boton1_url' => ['nullable', 'string', 'max:255', 'required_with:boton1_texto', 'regex:'.self::DESTINO],
            'boton2_texto' => ['nullable', 'string', 'max:40', 'required_with:boton2_url'],
            'boton2_url' => ['nullable', 'string', 'max:255', 'required_with:boton2_texto', 'regex:'.self::DESTINO],
            'activo' => ['required', 'boolean'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'orden' => ['required', 'integer', 'min:0', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'boton1_url.regex' => 'Usá una ruta del sitio (/catalogo) o una dirección completa (https://…).',
            'boton2_url.regex' => 'Usá una ruta del sitio (/catalogo) o una dirección completa (https://…).',
            'boton1_texto.required_with' => 'El botón necesita un texto.',
            'boton2_texto.required_with' => 'El botón necesita un texto.',
            'boton1_url.required_with' => 'El botón necesita un destino.',
            'boton2_url.required_with' => 'El botón necesita un destino.',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'activo' => $this->boolean('activo'),
        ]);
    }
}
