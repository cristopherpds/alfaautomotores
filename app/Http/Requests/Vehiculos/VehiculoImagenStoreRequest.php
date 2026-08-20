<?php

namespace App\Http\Requests\Vehiculos;

use App\Models\Vehiculo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VehiculoImagenStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'imagenes' => ['required', 'array', 'min:1', 'max:'.Vehiculo::MAX_IMAGENES],
            'imagenes.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
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
                $cargadas = $this->targetVehiculo()->fotos()->count();
                $entrantes = is_array($this->file('imagenes')) ? count($this->file('imagenes')) : 0;

                if ($cargadas + $entrantes > Vehiculo::MAX_IMAGENES) {
                    $validator->errors()->add('imagenes', __(
                        'La galería admite hasta :cantidad fotos y ya tiene :cargadas.',
                        ['cantidad' => Vehiculo::MAX_IMAGENES, 'cargadas' => $cargadas],
                    ));
                }
            },
        ];
    }

    /**
     * Get the vehicle the photos belong to.
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
