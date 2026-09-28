<?php

namespace App\Concerns;

use App\Enums\EstadoProducto;
use App\Enums\FamiliaProducto;
use App\Enums\Moneda;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ProductoValidationRules
{
    /**
     * Normalizar la ficha antes de validar.
     *
     * El slug va en minúsculas por la misma razón que en `VehiculoValidationRules`:
     * que `unique` compare contra lo que se va a guardar.
     *
     * Las filas de la ficha técnica y los colores que llegan vacías se
     * descartan: el editor siempre deja una fila en blanco para seguir
     * cargando, y eso no es un error (ojo: `ConvertEmptyStringsToNull` ya las
     * convirtió en null antes de llegar acá). `specs` y `colores` quedan como listas
     * aunque no llegue ninguna fila, porque las columnas no son nulas.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('slug')) {
            $this->merge([
                'slug' => Str::lower(trim((string) $this->input('slug'))),
            ]);
        }

        $specs = is_array($this->input('specs')) ? $this->input('specs') : [];
        $colores = is_array($this->input('colores')) ? $this->input('colores') : [];

        $this->merge([
            'specs' => array_values(array_filter(
                $specs,
                fn (mixed $fila): bool => ! is_array($fila)
                    || trim((string) ($fila[0] ?? '')) !== ''
                    || trim((string) ($fila[1] ?? '')) !== '',
            )),
            'colores' => array_values(array_filter(
                $colores,
                fn (mixed $color): bool => $color !== null && (! is_string($color) || trim($color) !== ''),
            )),
        ]);
    }

    /**
     * Reglas de la ficha de un producto, compartidas por el alta y la edición.
     *
     * `precio` es opcional para cualquier familia: las bicicletas se cotizan
     * por WhatsApp y los modelos que salen del catálogo PDF tampoco llevan
     * precio de mostrador.
     *
     * @param  int|null  $productoId  El producto que se está editando, para que su propio slug no choque consigo mismo.
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function productoRules(?int $productoId = null): array
    {
        return [
            'slug' => ['required', 'string', 'alpha_dash', 'max:255', Rule::unique('productos', 'slug')->ignore($productoId)],
            'nombre' => ['required', 'string', 'max:255'],
            'familia' => ['required', Rule::enum(FamiliaProducto::class)],
            'codigo' => ['nullable', 'string', 'max:50'],
            'precio' => ['nullable', 'integer', 'min:0'],
            'moneda' => ['required', Rule::enum(Moneda::class)],
            'estado' => ['required', Rule::enum(EstadoProducto::class)],
            'resumen' => ['required', 'string', 'max:255'],
            'desc' => ['required', 'string', 'max:2000'],
            'specs' => ['present', 'array', 'max:40'],
            'specs.*' => ['array', 'list', 'size:2'],
            'specs.*.*' => ['required', 'string', 'max:255'],
            'colores' => ['present', 'array', 'max:20'],
            'colores.*' => ['string', 'distinct', 'max:50'],
        ];
    }
}
