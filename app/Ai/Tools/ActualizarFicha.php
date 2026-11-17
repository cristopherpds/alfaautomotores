<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Anotar lo que se sabe de la persona: claves libres, como en Vocero.
 *
 * Se ve en el panel del contacto de la bandeja y vuelve al prompt en cada
 * turno, así el agente no pregunta dos veces lo mismo.
 */
class ActualizarFicha extends Herramienta
{
    private const MAX_CLAVES = 20;

    public function name(): string
    {
        return 'actualizar_ficha';
    }

    public function description(): Stringable|string
    {
        return 'Guarda un dato nuevo de la persona (qué busca, presupuesto, si entrega un usado, zona, forma de pago que prefiere…). Mandá sólo lo nuevo.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'dato' => $schema->string()->required()->description('Qué es, corto: "busca", "presupuesto", "entrega usado", "zona".'),
            'valor' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $dato = Str::limit(trim((string) ($request['dato'] ?? '')), 40, '');
        $valor = Str::limit(trim((string) ($request['valor'] ?? '')), 300);
        $ficha = $this->conversacion->ficha ?? [];

        if ($dato === '' || (! array_key_exists($dato, $ficha) && count($ficha) >= self::MAX_CLAVES)) {
            return $this->json(['error' => 'No se pudo guardar ese dato.']);
        }

        $ficha[$dato] = $valor;
        $this->conversacion->ficha = $ficha;
        $this->conversacion->saveQuietly();

        return $this->json(['guardado' => true]);
    }
}
