<?php

namespace App\Http\Requests\Turnos;

use App\Enums\Rubro;
use App\Models\Puesto;
use App\Models\Servicio;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * La reserva de un turno desde el sitio público.
 *
 * Las reglas son las mismas en los dos negocios; lo único que cambia es el
 * rubro, que acota los servicios reservables y direcciona la ventana de
 * reserva a su propia configuración. Por eso la clase es abstracta y cada
 * rubro tiene una hija de tres líneas.
 */
abstract class TurnoPublicoRequest extends FormRequest
{
    /**
     * El negocio al que pertenece esta reserva.
     */
    abstract protected function rubro(): Rubro;

    /**
     * Get the validation rules that apply to the request.
     *
     * La ruta es pública: cualquiera puede reservar, no hay `authorize()`.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rubro = $this->rubro();

        $desde = now()->addDays((int) $rubro->config('anticipacion_dias'))->toDateString();
        $hasta = now()->addDays((int) $rubro->config('horizonte_dias'))->toDateString();

        return [
            /* Acotado al rubro: desde `/lavadero` no se reserva un service, ni
               al revés, aunque el slug exista y esté activo. */
            'servicio' => ['required', 'string', Rule::exists('servicios', 'slug')
                ->where('activo', true)
                ->where('rubro', $rubro->value)],
            /* `date_format` y no `date`: es lo que manda un `<input type="date">`
               y evita que entre `14/09/2026` y se guarde otra cosa. */
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$desde, 'before_or_equal:'.$hasta],
            'hora' => ['required', 'date_format:H:i'],

            'nombre' => ['required', 'string', 'max:80'],
            'apellido' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email:rfc', 'max:120'],
            /* Laxa a propósito: se anotan como `099 123 456`, `+598 99…` o con
               guiones, y rechazar formatos es perder turnos. */
            'celular' => ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s().]{6,25}$/'],

            'vehiculo_marca' => ['nullable', 'string', 'max:60'],
            'vehiculo_modelo' => ['nullable', 'string', 'max:60'],
            'vehiculo_anio' => ['nullable', 'integer', 'between:1950,'.(now()->year + 1)],
            'matricula' => ['nullable', 'string', 'max:12'],
            'comentario' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get the additional validation callbacks that should run.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $servicio = Servicio::where('slug', $this->string('servicio')->value())
                    ->where('rubro', $this->rubro())
                    ->first();

                if ($servicio === null) {
                    return;
                }

                if (! $servicio->agendable) {
                    $validator->errors()->add('servicio', __('Ese trabajo se cotiza con el vehículo en el taller: escribinos por WhatsApp.'));

                    return;
                }

                /* El horario tiene que ser uno de los que ofrece la agenda: así
                   se descarta de una el domingo, la hora fuera de horario y el
                   hueco que ya se ocupó. */
                $fecha = Date::createFromFormat('Y-m-d', $this->string('fecha')->value())->startOfDay();

                if (! in_array($this->string('hora')->value(), Puesto::huecosDelDia($fecha, $servicio), true)) {
                    $validator->errors()->add('hora', __('Ese horario ya no está libre. Elegí otro, por favor.'));
                }
            },
        ];
    }
}
