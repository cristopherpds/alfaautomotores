<?php

namespace App\Http\Requests\Productos;

use App\Models\Producto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductoImagenOrdenRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Se reciben los ids de la galería en el orden nuevo. Cada uno tiene que
     * ser del producto: si no, se podrían reordenar fotos ajenas.
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
                Rule::exists('producto_imagenes', 'id')
                    ->where('producto_id', $this->targetProducto()->id),
            ],
        ];
    }

    /**
     * Get the product the gallery belongs to.
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
