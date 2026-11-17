<?php

namespace App\Notifications;

use App\Ai\Prompt;
use App\Models\Ajuste;
use App\Models\Turno;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * El email al cliente cuando el equipo confirma su turno.
 *
 * Va por ruta a demanda (`Notification::route('mail', …)`): el cliente no es
 * un usuario. Sólo sale si está prendido en Ajustes › Avisos; lo decide
 * `Turno::avisarConfirmacion()`.
 */
#[DeleteWhenMissingModels]
class TurnoConfirmado extends Notification implements ShouldQueueAfterCommit
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
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $turno = $this->turno;
        $whatsapp = Ajuste::whatsappDisponible(now())['contactos'][0]['numero'];

        return (new MailMessage)
            ->subject('Tu turno está confirmado: '.Prompt::etiqueta($turno->inicia_at))
            ->greeting('¡Hola, '.$turno->nombre.'!')
            ->line('Te confirmamos el turno en el '.mb_strtolower($turno->servicio->rubro->label()).' de '.config('alfa.nombre').'.')
            ->line('**Servicio:** '.$turno->servicio->nombre)
            ->line('**Cuándo:** '.ucfirst(Prompt::etiqueta($turno->inicia_at)))
            ->line('**Dónde:** '.config('alfa.direccion').', '.config('alfa.ciudad'))
            ->line('Si no podés venir o querés cambiar el horario, escribinos por WhatsApp.')
            ->action('Escribir por WhatsApp', 'https://wa.me/'.$whatsapp)
            ->salutation('¡Te esperamos! '.config('alfa.nombre'));
    }
}
