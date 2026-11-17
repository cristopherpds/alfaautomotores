<?php

namespace App\Ai\Tools;

use App\Models\Conversacion;
use Laravel\Ai\Contracts\Tool;

/**
 * Una herramienta del agente de WhatsApp, atada a la conversación del turno.
 *
 * Todas devuelven JSON: el modelo lo lee mejor que prosa y no se confunde
 * con lo que tiene que contestarle a la persona. El nombre es el que usa el
 * prompt (`Prompt::chasis()`), en castellano y en snake_case.
 */
abstract class Herramienta implements Tool
{
    public function __construct(protected Conversacion $conversacion) {}

    abstract public function name(): string;

    /**
     * @param  array<string, mixed>  $datos
     */
    protected function json(array $datos): string
    {
        return (string) json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
