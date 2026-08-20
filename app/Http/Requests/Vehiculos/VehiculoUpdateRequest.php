<?php

namespace App\Http\Requests\Vehiculos;

use App\Concerns\VehiculoValidationRules;
use App\Models\Vehiculo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VehiculoUpdateRequest extends FormRequest
{
    use VehiculoValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->vehiculoRules($this->targetVehiculo()->id);
    }

    /**
     * Get the vehicle being updated.
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
