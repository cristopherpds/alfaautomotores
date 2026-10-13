<?php

namespace App\Ai\Tools;

use App\Enums\MotivoDerivacion;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Pasar la conversación al equipo. Desde acá la IA se calla hasta que una
 * persona se la devuelva desde la bandeja.
 */
class DerivarAHumano extends Herramienta
{
    public function name(): string
    {
        return 'derivar_a_humano';
    }

    public function description(): Stringable|string
    {
        return 'Pasa la conversación a una persona del equipo. Usala si la persona lo pide, si quiere negociar, cancelar un turno o algo que no podés resolver, o si hay frustración. Después de llamarla, avisale en una línea que en un rato le escribe alguien.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'motivo' => $schema->string()->required()->enum(['cliente', 'modelo', 'hostilidad'])
                ->description('cliente = lo pidió o quiere algo del equipo; modelo = no lo podés resolver; hostilidad = insultos.'),
            'resumen' => $schema->string()->required()->description('Una línea para el equipo: qué quiere y en qué quedó.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $motivo = MotivoDerivacion::tryFrom((string) ($request['motivo'] ?? '')) ?? MotivoDerivacion::Modelo;

        $ficha = $this->conversacion->ficha ?? [];
        $ficha['para el equipo'] = Str::limit(trim((string) ($request['resumen'] ?? '')), 300);
        $this->conversacion->ficha = $ficha;
        $this->conversacion->saveQuietly();

        $this->conversacion->derivar($motivo);

        return $this->json([
            'derivada' => true,
            'nota' => 'Listo. Avisale en una línea que en un rato le escribe alguien del equipo. No sigas la conversación.',
        ]);
    }
}
