<?php

namespace App\Http\Requests\Vehiculos;

use App\Models\Vehiculo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DestacarVehiculoRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'destacado' => ['required', 'boolean'],
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
                $vehiculo = $this->targetVehiculo();

                // Quitar un destacado y volver a marcar uno ya marcado siempre
                // se permiten: el tope sólo frena sumar uno nuevo.
                if (! $this->boolean('destacado') || $vehiculo->destacado) {
                    return;
                }

                // Un borrador no llega al sitio público, así que destacarlo
                // gastaría un lugar de la portada que nadie ve.
                if (! $vehiculo->esDestacable()) {
                    $validator->errors()->add('destacado', __(
                        'Un borrador no puede ir a la portada. Publicalo primero.',
                    ));

                    return;
                }

                if (Vehiculo::contarDestacados() >= Vehiculo::MAX_DESTACADOS) {
                    $validator->errors()->add('destacado', __(
                        'Ya hay :cantidad vehículos destacados. Quitá uno antes de agregar otro.',
                        ['cantidad' => Vehiculo::MAX_DESTACADOS],
                    ));
                }
            },
        ];
    }

    /**
     * Get the vehicle being pinned or unpinned.
     */
    private function targetVehiculo(): Vehiculo
    {
        $vehiculo = $this->route('vehiculo');

        if (! $vehiculo instanceof Vehiculo) {
            throw new NotFoundHttpException;
        }

        return $vehiculo;
    }
}
