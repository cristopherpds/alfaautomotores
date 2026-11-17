<?php

namespace App\Notifications;

use App\Models\Cotizacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * Aviso al equipo de una solicitud nueva de «Cotizá tu auto», de la web o de
 * WhatsApp: en la campana del panel y por email.
 *
 * Lo que queda en `notifications` no lleva datos de la persona (esa tabla no
 * va cifrada): sólo el auto. El nombre y el celular van en el email.
 */
#[DeleteWhenMissingModels]
class NuevaCotizacion extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public Cotizacion $cotizacion) {}

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
        $cotizacion = $this->cotizacion;

        return (new MailMessage)
            ->subject('Nueva cotización: '.$cotizacion->vehiculo())
            ->greeting('Nueva cotización')
            ->line('Entró una solicitud de «Cotizá tu auto».')
            ->line('**Origen:** '.$cotizacion->origen->label())
            ->line('**Auto:** '.$cotizacion->vehiculo().' · '.number_format($cotizacion->kilometros, 0, ',', '.').' km')
            ->line('**Cliente:** '.trim($cotizacion->nombre.' '.$cotizacion->apellido).' · '.$cotizacion->celular)
            ->action('Ver la cotización', route('panel.cotizaciones.show', $cotizacion))
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
            'tipo' => 'cotizacion',
            'titulo' => 'Nueva cotización',
            'detalle' => $this->cotizacion->vehiculo().' · '.$this->cotizacion->origen->label(),
            'url' => route('panel.cotizaciones.show', $this->cotizacion, absolute: false),
        ];
    }
}
