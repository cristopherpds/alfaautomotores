<?php

namespace App\Http\Requests\Productos;

use App\Models\Producto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductoImagenStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'imagenes' => ['required', 'array', 'min:1', 'max:'.Producto::MAX_IMAGENES],
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
                $cargadas = $this->targetProducto()->fotos()->count();
                $entrantes = is_array($this->file('imagenes')) ? count($this->file('imagenes')) : 0;

                if ($cargadas + $entrantes > Producto::MAX_IMAGENES) {
                    $validator->errors()->add('imagenes', __(
                        'La galería admite hasta :cantidad fotos y ya tiene :cargadas.',
                        ['cantidad' => Producto::MAX_IMAGENES, 'cargadas' => $cargadas],
                    ));
                }
            },
        ];
    }

    /**
     * Get the product the photos belong to.
     */
    private function targetProducto(): Producto
    {
        $producto = $this->route('producto');

        if (! $producto instanceof Producto) {
            throw new NotFoundHttpException;
        }

        return $producto;
    }
}
