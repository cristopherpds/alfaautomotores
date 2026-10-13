<?php

namespace App\Ai\Agents;

use App\Ai\Tools\ActualizarFicha;
use App\Ai\Tools\BuscarStock;
use App\Ai\Tools\ConsultarServicios;
use App\Ai\Tools\DerivarAHumano;
use App\Ai\Tools\ProponerHorarios;
use App\Ai\Tools\RegistrarCotizacion;
use App\Ai\Tools\ReprogramarTurno;
use App\Ai\Tools\ReservarTurno;
use App\Models\Conversacion;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * El agente de IA del WhatsApp de Alfa.
 *
 * No recuerda nada por su cuenta: el historial sale de `mensajes_whatsapp`
 * (lo arma `ResponderConversacion`) y el prompt de `App\Ai\Prompt`. Las
 * herramientas van atadas a la conversación del turno.
 *
 * Con `$rapido` le pide a OpenRouter lo mismo que nea-agent
 * (`LLM_REASONING_EFFORT=minimal`, `LLM_PROVIDER_SORT=throughput`): sin
 * razonamiento largo y al proveedor más rápido, porque cada segundo lo pasa
 * la persona mirando «escribiendo…». Si un modelo rechaza esas opciones, el
 * turno reintenta sin ellas.
 */
#[MaxSteps(8)]
#[Temperature(0.4)]
class AsistenteWhatsapp implements Agent, Conversational, HasProviderOptions, HasTools
{
    use Promptable;

    /**
     * @param  list<Message>  $historial
     */
    public function __construct(
        public Conversacion $conversacion,
        public string $sistema,
        public array $historial = [],
        public bool $rapido = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        if (! $this->rapido) {
            return [];
        }

        return [
            'reasoning' => ['effort' => 'minimal'],
            'provider' => ['sort' => 'throughput'],
        ];
    }

    public function instructions(): Stringable|string
    {
        return $this->sistema;
    }

    /**
     * @return list<Message>
     */
    public function messages(): iterable
    {
        return $this->historial;
    }

    public function tools(): iterable
    {
        return [
            new BuscarStock($this->conversacion),
            new ConsultarServicios($this->conversacion),
            new ProponerHorarios($this->conversacion),
            new ReservarTurno($this->conversacion),
            new ReprogramarTurno($this->conversacion),
            new RegistrarCotizacion($this->conversacion),
            new ActualizarFicha($this->conversacion),
            new DerivarAHumano($this->conversacion),
        ];
    }
}
