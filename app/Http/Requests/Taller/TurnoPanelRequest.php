<?php

namespace App\Http\Requests\Taller;

use App\Models\Puesto;
use App\Models\Servicio;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TurnoPanelRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Igual que la reserva de la web salvo la ventana: el taller puede cargar
     * un turno para hoy mismo. Sin `authorize()`: lo cubre el middleware.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'servicio' => ['required', 'string', Rule::exists('servicios', 'slug')->where('activo', true)],
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora' => ['required', 'date_format:H:i'],

            'nombre' => ['required', 'string', 'max:80'],
            'apellido' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email:rfc', 'max:120'],
            'celular' => ['required', 'string', 'max:25'],

            'vehiculo_marca' => ['nullable', 'string', 'max:60'],
            'vehiculo_modelo' => ['nullable', 'string', 'max:60'],
            'vehiculo_anio' => ['nullable', 'integer', 'between:1950,'.(now()->year + 1)],
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

                $servicio = Servicio::where('slug', $this->string('servicio')->value())->first();

                if ($servicio === null) {
                    return;
                }

                $fecha = Date::createFromFormat('Y-m-d', $this->string('fecha')->value())->startOfDay();

                if (! in_array($this->string('hora')->value(), Puesto::huecosDelDia($fecha, $servicio), true)) {
                    $validator->errors()->add('hora', __('Ese horario no está libre.'));
                }
            },
        ];
    }
}
