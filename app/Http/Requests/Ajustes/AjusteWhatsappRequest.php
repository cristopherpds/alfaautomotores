<?php

namespace App\Http\Requests\Ajustes;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AjusteWhatsappRequest extends FormRequest
{
    /**
     * Cuántos contactos entran en la tarjeta sin que se vuelva una lista.
     */
    public const MAX_CONTACTOS = 6;

    /**
     * Los números se guardan como los pide wa.me: sólo dígitos, con código
     * de país. Se limpian antes de validar para aceptar «+598 99 123 456».
     */
    protected function prepareForValidation(): void
    {
        $contactos = $this->input('contactos');

        if (! is_array($contactos)) {
            return;
        }

        $this->merge([
            'contactos' => array_map(function (mixed $contacto): mixed {
                if (is_array($contacto) && isset($contacto['numero'])) {
                    $contacto['numero'] = preg_replace('/\D+/', '', (string) $contacto['numero']);
                }

                return $contacto;
            }, $contactos),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'activo' => ['required', 'boolean'],
            'titulo' => ['required', 'string', 'max:40'],
            'subtitulo' => ['required', 'string', 'max:80'],
            'contactos' => ['required', 'array', 'min:1', 'max:'.self::MAX_CONTACTOS],
            'contactos.*.nombre' => ['required', 'string', 'max:40'],
            'contactos.*.detalle' => ['nullable', 'string', 'max:60'],
            'contactos.*.numero' => ['required', 'digits_between:8,15'],
            'contactos.*.mensaje' => ['required', 'string', 'max:300'],
            'contactos.*.respaldo' => ['required', 'boolean'],
            'contactos.*.horario.siempre' => ['required', 'boolean'],
            'contactos.*.horario.dias' => ['array', 'required_if:contactos.*.horario.siempre,false'],
            // Sin `distinct`: con el comodín compara contra los días de TODOS
            // los contactos, y dos contactos que atienden el mismo lunes
            // fallarían. Los repetidos se limpian al guardar.
            'contactos.*.horario.dias.*' => ['integer', 'between:1,7'],
            'contactos.*.horario.desde' => ['required', 'date_format:H:i'],
            'contactos.*.horario.hasta' => ['required', 'date_format:H:i', 'different:contactos.*.horario.desde'],
        ];
    }

    /**
     * Get the additional validation callbacks that should run.
     *
     * Tiene que haber exactamente un respaldo: es el número que queda cuando
     * nadie está en horario, así el botón nunca se queda sin a quién llamar.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $contactos = $this->input('contactos');

                if (! is_array($contactos) || $validator->errors()->has('contactos')) {
                    return;
                }

                $respaldos = count(array_filter(
                    $contactos,
                    fn (mixed $contacto): bool => is_array($contacto) && filter_var($contacto['respaldo'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ));

                if ($respaldos !== 1) {
                    $validator->errors()->add('contactos', __('Marcá un solo contacto como respaldo: es el que se muestra cuando ninguno está en horario.'));
                }
            },
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
            'contactos.*.numero.digits_between' => __('El número tiene que tener entre 8 y 15 dígitos, con el código de país (598…).'),
            'contactos.*.horario.dias.required_if' => __('Elegí al menos un día, o marcalo como siempre disponible.'),
            'contactos.*.horario.hasta.different' => __('El horario tiene que empezar y terminar a horas distintas.'),
            'contactos.max' => __('Entran hasta :max contactos en la tarjeta.'),
        ];
    }
}
