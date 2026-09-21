<?php

namespace App\Http\Requests\Servicios;

use App\Enums\AreaServicio;
use App\Enums\Rubro;
use App\Models\Servicio;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServicioRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Sirve para el alta y la edición: lo único que cambia es a quién ignora
     * la regla de slug único. Sin `authorize()`: lo cubre el middleware.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $servicio = $this->route('servicio');
        $id = $servicio instanceof Servicio ? $servicio->id : null;

        return [
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('servicios', 'slug')->ignore($id)],
            'nombre' => ['required', 'string', 'max:80'],
            'rubro' => ['required', Rule::enum(Rubro::class)],
            /* El área clasifica el trabajo dentro del taller y es el filtro de
               su grilla; el lavadero no la usa, así que sólo se exige ahí. */
            'area' => ['nullable', 'required_if:rubro,'.Rubro::Taller->value, Rule::enum(AreaServicio::class)],
            /* En minutos y múltiplo de 5: es el largo del turno, y con valores
               sueltos los horarios ofrecidos caen en horas ilegibles. */
            'duracion' => ['required', 'integer', 'min:15', 'max:480', 'multiple_of:5'],
            /* Precio de lista, sin centavos. Lo muestra el lavadero en cada
               tarjeta; el taller cotiza con el vehículo delante y va vacío. */
            'precio' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'descripcion' => ['required', 'string', 'max:400'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'activo' => ['required', 'boolean'],
            'agendable' => ['required', 'boolean'],
            'orden' => ['required', 'integer', 'min:0', 'max:255'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => str($this->string('slug')->value())->trim()->lower()->value(),
            'activo' => $this->boolean('activo'),
            'agendable' => $this->boolean('agendable'),
            /* Los selects vacíos llegan como '' y `nullable` no los toma por
               nulos: sin esto, un lavado sin área falla la validación del enum
               y un precio en blanco entraría como 0. */
            'area' => $this->input('area') === '' ? null : $this->input('area'),
            'precio' => $this->input('precio') === '' ? null : $this->input('precio'),
        ]);
    }
}
