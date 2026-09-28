<?php

namespace App\Http\Requests\Productos;

use App\Concerns\ProductoValidationRules;
use App\Models\Producto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductoUpdateRequest extends FormRequest
{
    use ProductoValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->productoRules($this->targetProducto()->id);
    }

    /**
     * Get the product being updated.
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
