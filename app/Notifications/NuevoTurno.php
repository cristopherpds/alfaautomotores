<?php

namespace App\Notifications;

use App\Ai\Prompt;
use App\Models\Turno;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * Aviso al equipo de un turno que reservó un cliente (web o WhatsApp) y que
 * queda pendiente de confirmar: en la campana del panel y por email.
 *
 * Igual que `NuevaCotizacion`, en `notifications` va sólo el servicio y el
 * horario; los datos de la persona, en el email.
 */
#[DeleteWhenMissingModels]
class NuevoTurno extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public Turno $turno) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $turno = $this->turno;
        $rubro = $turno->servicio->rubro->label();

        $mensaje = (new MailMessage)
            ->subject('Nuevo turno de '.mb_strtolower($rubro).': '.$turno->servicio->nombre)
            ->greeting('Nuevo turno de '.mb_strtolower($rubro))
            ->line('Queda pendiente hasta que alguien del equipo lo confirme.')
            ->line('**Origen:** '.$turno->origen->label())
            ->line('**Servicio:** '.$turno->servicio->nombre)
            ->line('**Cuándo:** '.Prompt::etiqueta($turno->inicia_at))
            ->line('**Cliente:** '.$turno->cliente().' · '.$turno->celular);

        if ($turno->vehiculo() !== null) {
            $mensaje->line('**Vehículo:** '.$turno->vehiculo());
        }

        return $mensaje
            ->action('Ver la agenda', $this->enlace(absolute: true))
            ->salutation('Alfa Automotores');
    }

    /**
     * Lo que muestra la campana del panel.
     *
     * @return array{tipo: string, titulo: string, detalle: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'turno',
            'titulo' => 'Nuevo turno de '.mb_strtolower($this->turno->servicio->rubro->label()),
            'detalle' => $this->turno->servicio->nombre.' · '.Prompt::etiqueta($this->turno->inicia_at),
            'url' => $this->enlace(absolute: false),
        ];
    }

    /**
     * La agenda en el mes del turno.
     */
    private function enlace(bool $absolute): string
    {
        return route('panel.turnos.index', [
            'desde' => $this->turno->inicia_at->copy()->startOfMonth()->toDateString(),
            'hasta' => $this->turno->inicia_at->copy()->endOfMonth()->toDateString(),
        ], $absolute);
    }
}
