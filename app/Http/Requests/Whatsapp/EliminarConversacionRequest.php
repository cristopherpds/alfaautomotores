<?php

namespace App\Http\Requests\Whatsapp;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EliminarConversacionRequest extends FormRequest
{
    /**
     * Sólo el hilo, o también el cliente con su ficha y sus otras
     * conversaciones.
     *
     * @var list<string>
     */
    public const ALCANCES = ['hilo', 'contacto'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'alcance' => ['required', Rule::in(self::ALCANCES)],
        ];
    }

    /**
     * Si también se borra el cliente vinculado.
     */
    public function tambienElCliente(): bool
    {
        return $this->validated('alcance') === 'contacto';
    }
}
