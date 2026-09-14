<?php

namespace App\Http\Requests\Taller;

use App\Enums\AreaServicio;
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
            'area' => ['required', Rule::enum(AreaServicio::class)],
            /* En minutos y múltiplo de 5: es el largo del turno, y con valores
               sueltos los horarios ofrecidos caen en horas ilegibles. */
            'duracion' => ['required', 'integer', 'min:15', 'max:480', 'multiple_of:5'],
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
        ]);
    }
}
