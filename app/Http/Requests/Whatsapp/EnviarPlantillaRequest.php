<?php

namespace App\Http\Requests\Whatsapp;

use App\Enums\EstadoPlantilla;
use App\Models\PlantillaWhatsapp;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EnviarPlantillaRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plantilla' => ['required', 'integer', Rule::exists('plantillas_whatsapp', 'id')->where('estado', EstadoPlantilla::Aprobada->value)],
            'variables' => ['array'],
            'variables.*' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * Cada `{{n}}` del cuerpo necesita su valor: Meta rechaza la plantilla
     * con variables de menos.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $plantilla = $this->plantilla();

                if (count($this->input('variables', [])) !== $plantilla->variables) {
                    $validator->errors()->add('variables', __('Completá las :cantidad variables de la plantilla.', ['cantidad' => $plantilla->variables]));
                }
            },
        ];
    }

    public function plantilla(): PlantillaWhatsapp
    {
        return PlantillaWhatsapp::findOrFail($this->integer('plantilla'));
    }
}
