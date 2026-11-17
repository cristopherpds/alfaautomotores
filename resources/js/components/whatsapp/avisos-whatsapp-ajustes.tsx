import { BellRing } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    desbloquearSonido,
    EVENTO_PREFERENCIAS,
    guardarSonido,
    pedirPermisoDeNotificaciones,
    permisoDeNotificaciones,
    registrarServiceWorker,
    sonarAviso,
    sonidoActivado,
} from '@/lib/avisos-whatsapp';

/**
 * El permiso de notificaciones y el sonido, al día entre componentes.
 */
function usePreferenciasDeAvisos() {
    const [permiso, setPermiso] = useState(permisoDeNotificaciones);
    const [sonido, setSonido] = useState(sonidoActivado);

    useEffect(() => {
        const actualizar = () => {
            setPermiso(permisoDeNotificaciones());
            setSonido(sonidoActivado());
        };

        window.addEventListener(EVENTO_PREFERENCIAS, actualizar);

        return () =>
            window.removeEventListener(EVENTO_PREFERENCIAS, actualizar);
    }, []);

    const activarNotificaciones = async () => {
        const resultado = await pedirPermisoDeNotificaciones();

        if (resultado === 'granted') {
            void registrarServiceWorker();
            toast.success('Listo: vas a ver los mensajes nuevos de WhatsApp.');
        } else if (resultado === 'denied') {
            toast.error(
                'El navegador bloqueó las notificaciones. Habilitalas desde el candado de la barra de direcciones.',
            );
        }
    };

    return { permiso, sonido, activarNotificaciones };
}

/**
 * El botón de la bandeja: aparece sólo mientras no se haya decidido el permiso.
 */
export function ActivarAvisosWhatsapp() {
    const { permiso, activarNotificaciones } = usePreferenciasDeAvisos();

    if (permiso !== 'default') {
        return null;
    }

    return (
        <Button variant="outline" size="sm" onClick={activarNotificaciones}>
            <BellRing />
            Activar notificaciones
        </Button>
    );
}

/**
 * La sección de la configuración: notificaciones del sistema y sonido.
 */
export function AvisosWhatsappAjustes() {
    const { permiso, sonido, activarNotificaciones } =
        usePreferenciasDeAvisos();

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Avisos de WhatsApp"
                description="Notificaciones y sonido cuando entra un mensaje o la IA deriva una conversación. Valen para este navegador."
            />

            <div className="flex items-center justify-between gap-4">
                <div className="space-y-0.5">
                    <p className="text-sm font-medium">
                        Notificaciones de escritorio
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {permiso === null &&
                            'Este navegador no muestra notificaciones.'}
                        {permiso === 'granted' && 'Activadas.'}
                        {permiso === 'denied' &&
                            'Bloqueadas: habilitalas desde el candado de la barra de direcciones.'}
                        {permiso === 'default' &&
                            'El navegador te va a pedir permiso.'}
                    </p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={permiso !== 'default'}
                    onClick={activarNotificaciones}
                >
                    <BellRing />
                    Activar notificaciones
                </Button>
            </div>

            <div className="flex items-center justify-between gap-4">
                <div className="space-y-0.5">
                    <Label htmlFor="avisos-sonido">Sonido</Label>
                    <p
                        id="avisos-sonido-ayuda"
                        className="text-sm text-muted-foreground"
                    >
                        Dos notas cortas con cada novedad.
                    </p>
                </div>
                <Switch
                    id="avisos-sonido"
                    aria-describedby="avisos-sonido-ayuda"
                    checked={sonido}
                    onCheckedChange={(activado) => {
                        guardarSonido(activado);

                        if (activado) {
                            desbloquearSonido();
                            window.setTimeout(sonarAviso, 50);
                        }
                    }}
                />
            </div>
        </div>
    );
}
