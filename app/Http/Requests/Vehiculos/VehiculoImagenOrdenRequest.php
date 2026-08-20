<?php

namespace App\Http\Requests\Vehiculos;

use App\Models\Vehiculo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VehiculoImagenOrdenRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Se reciben los ids de la galería en el orden nuevo. Cada uno tiene que
     * ser del vehículo: si no, se podrían reordenar fotos ajenas.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'imagenes' => ['required', 'array', 'min:1'],
            'imagenes.*' => [
                'integer',
                'distinct',
                Rule::exists('vehiculo_imagenes', 'id')
                    ->where('vehiculo_id', $this->targetVehiculo()->id),
            ],
        ];
    }

    /**
     * Get the vehicle the gallery belongs to.
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
