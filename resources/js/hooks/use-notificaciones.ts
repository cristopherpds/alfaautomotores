import { router, useHttp } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { mostrarNotificacion, sonarAviso } from '@/lib/avisos-whatsapp';
import { index, leerTodas, leida } from '@/routes/panel/notificaciones';
import type { Notificacion, ResumenNotificaciones } from '@/types';

/** Cada cuánto se pregunta por avisos nuevos desde cualquier página del panel. */
const CADA_MS = 15000;

export type Notificaciones = {
    resumen: ResumenNotificaciones | null;
    abrir: (aviso: Notificacion) => void;
    marcarTodas: () => void;
};

/**
 * La campana del panel: cotizaciones y turnos que entran desde la web o
 * WhatsApp (como `useAvisosWhatsapp`, por polling y sin websockets).
 *
 * El primer pedido sólo anota qué avisos ya existían, así no se anuncia lo
 * viejo al entrar. Después, cada aviso nuevo sin leer hace sonar las dos
 * notas, sale como toast y como notificación del sistema (si se dio
 * permiso desde la bandeja de WhatsApp).
 */
export function useNotificaciones(): Notificaciones {
    const [resumen, setResumen] = useState<ResumenNotificaciones | null>(null);

    const consulta = useHttp<Record<string, never>, ResumenNotificaciones>({});
    const accion = useHttp<Record<string, never>, ResumenNotificaciones>({});

    const pedir = useRef(consulta.get);

    useEffect(() => {
        pedir.current = consulta.get;
    }, [consulta.get]);

    /* Los ids ya vistos; null hasta el primer pedido. */
    const conocidos = useRef<Set<string> | null>(null);

    useEffect(() => {
        let cancelado = false;
        let pidiendo = false;

        const anunciar = (aviso: Notificacion) => {
            const url = aviso.url;

            toast.info(aviso.titulo, {
                description: aviso.detalle,
                action: url
                    ? { label: 'Ver', onClick: () => router.visit(url) }
                    : undefined,
            });

            if (url) {
                void mostrarNotificacion({
                    titulo: aviso.titulo,
                    cuerpo: aviso.detalle,
                    etiqueta: `aviso-${aviso.id}`,
                    url,
                    alTocar: () => router.visit(url),
                });
            }
        };

        const consultar = async (): Promise<void> => {
            if (pidiendo) {
                return;
            }

            pidiendo = true;

            try {
                const respuesta = await pedir.current(index.url());

                if (cancelado) {
                    return;
                }

                setResumen(respuesta);

                const vistos = conocidos.current;

                if (vistos !== null) {
                    const nuevos = respuesta.avisos
                        .filter(
                            (aviso) => !aviso.leida && !vistos.has(aviso.id),
                        )
                        .reverse();

                    nuevos.forEach(anunciar);

                    if (nuevos.length > 0) {
                        sonarAviso();
                    }
                }

                conocidos.current = new Set([
                    ...(vistos ?? []),
                    ...respuesta.avisos.map((aviso) => aviso.id),
                ]);
            } catch {
                // Sin red o sesión vencida: se reintenta en la próxima vuelta.
            } finally {
                pidiendo = false;
            }
        };

        void consultar();
        const intervalo = window.setInterval(() => void consultar(), CADA_MS);

        return () => {
            cancelado = true;
            window.clearInterval(intervalo);
        };
    }, []);

    const abrir = useCallback(
        (aviso: Notificacion) => {
            if (!aviso.leida) {
                void accion
                    .patch(leida.url(aviso.id))
                    .then(setResumen)
                    .catch(() => undefined);
            }

            if (aviso.url) {
                router.visit(aviso.url);
            }
        },
        [accion],
    );

    const marcarTodas = useCallback(() => {
        void accion
            .post(leerTodas.url())
            .then(setResumen)
            .catch(() => undefined);
    }, [accion]);

    return { resumen, abrir, marcarTodas };
}
