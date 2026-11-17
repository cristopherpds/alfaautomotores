<?php

namespace App\Http\Requests\Whatsapp;

use App\Models\Conversacion;
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
     * Borrar el hilo lo autoriza el middleware del controller; borrar también
     * el cliente pide además poder borrar clientes (sólo admin, ley 18.331).
     */
    public function authorize(): bool
    {
        if ($this->input('alcance') !== 'contacto') {
            return true;
        }

        /** @var Conversacion $conversacion */
        $conversacion = $this->route('conversacion');

        return $conversacion->cliente === null
            || (bool) $this->user()?->can('delete', $conversacion->cliente);
    }

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
