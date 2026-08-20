<?php

namespace App\Concerns;

use App\Enums\Combustible;
use App\Enums\EstadoVehiculo;
use App\Enums\Moneda;
use App\Enums\TipoVehiculo;
use App\Enums\Transmision;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait VehiculoValidationRules
{
    /**
     * El slug no distingue mayúsculas.
     *
     * Se normaliza antes de validar y no en el modelo para que la regla
     * `unique` compare contra lo mismo que se va a guardar: si ya existe
     * `strada-freedom-24`, cargar `Strada-Freedom-24` tiene que dar choque
     * de slug y no una segunda ficha con la misma dirección pública.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('slug')) {
            $this->merge([
                'slug' => Str::lower(trim((string) $this->input('slug'))),
            ]);
        }
    }

    /**
     * Reglas de la ficha de un vehículo, compartidas por el alta y la edición.
     *
     * @param  int|null  $vehiculoId  El vehículo que se está editando, para que su propio slug no choque consigo mismo.
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function vehiculoRules(?int $vehiculoId = null): array
    {
        return [
            'slug' => ['required', 'string', 'alpha_dash', 'max:255', Rule::unique('vehiculos', 'slug')->ignore($vehiculoId)],
            'marca' => ['required', 'string', 'max:255'],
            'modelo' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:255'],
            'anio' => ['required', 'integer', 'between:1950,'.(now()->year + 1)],
            'km' => ['required', 'integer', 'min:0', 'max:2000000'],
            'precio' => ['required', 'integer', 'min:0'],
            'moneda' => ['required', Rule::enum(Moneda::class)],
            'comb' => ['required', Rule::enum(Combustible::class)],
            'trans' => ['required', Rule::enum(Transmision::class)],
            'tipo' => ['required', Rule::enum(TipoVehiculo::class)],
            'estado' => ['required', Rule::enum(EstadoVehiculo::class)],
            'desc' => ['required', 'string', 'max:2000'],
        ];
    }
}
