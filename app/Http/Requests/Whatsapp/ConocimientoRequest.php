<?php

namespace App\Http\Requests\Whatsapp;

use App\Models\Conocimiento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConocimientoRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in([Conocimiento::PREGUNTA, Conocimiento::BLOQUE])],
            'pregunta' => ['nullable', 'required_if:tipo,'.Conocimiento::PREGUNTA, 'string', 'max:255'],
            'respuesta' => ['nullable', 'required_if:tipo,'.Conocimiento::PREGUNTA, 'string', 'max:3000'],
            'contenido' => ['nullable', 'required_if:tipo,'.Conocimiento::BLOQUE, 'string', 'max:6000'],
        ];
    }

    /**
     * Sólo los campos del tipo elegido: una pregunta no guarda contenido
     * suelto ni al revés.
     *
     * @return array{tipo: string, pregunta: string|null, respuesta: string|null, contenido: string|null}
     */
    public function datos(): array
    {
        $esPregunta = $this->validated('tipo') === Conocimiento::PREGUNTA;

        return [
            'tipo' => $this->validated('tipo'),
            'pregunta' => $esPregunta ? $this->validated('pregunta') : null,
            'respuesta' => $esPregunta ? $this->validated('respuesta') : null,
            'contenido' => $esPregunta ? null : $this->validated('contenido'),
        ];
    }
}
