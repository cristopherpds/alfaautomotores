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
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * El agente de IA del WhatsApp de Alfa.
 *
 * No recuerda nada por su cuenta: el historial sale de `mensajes_whatsapp`
 * (lo arma `ResponderConversacion`) y el prompt de `App\Ai\Prompt`. Las
 * herramientas van atadas a la conversación del turno.
 */
#[MaxSteps(8)]
#[Temperature(0.4)]
class AsistenteWhatsapp implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  list<Message>  $historial
     */
    public function __construct(
        public Conversacion $conversacion,
        public string $sistema,
        public array $historial = [],
    ) {}

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
