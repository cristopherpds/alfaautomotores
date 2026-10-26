import { router, useHttp, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import {
    desbloquearSonido,
    mostrarNotificacion,
    registrarServiceWorker,
    sonarAviso,
} from '@/lib/avisos-whatsapp';
import { avisos, show } from '@/routes/panel/whatsapp';
import type {
    AvisosWhatsapp,
    ContadoresWhatsapp,
    EventoWhatsapp,
} from '@/types';

/** Cada cuánto se preguntan las novedades desde cualquier página del panel. */
const CADA_MS = 15000;

/** En la bandeja, más seguido: ahí se está atendiendo. */
const CADA_MS_EN_LA_BANDEJA = 5000;

const PAGINA_BANDEJA = 'panel/whatsapp/index';

const PREFIJO_TITULO = /^\(\d+\) /;

type Cursor = AvisosWhatsapp['cursor'];

/**
 * Los avisos de WhatsApp de todo el panel (como los de vocero).
 *
 * Pregunta cada pocos segundos por `panel.whatsapp.avisos` y, con lo nuevo
 * desde la vez anterior (mensajes entrantes y derivaciones de la IA), hace
 * sonar dos notas y muestra la notificación del sistema. No avisa del chat
 * que ya está abierto y a la vista. Además pone «(N) » en el título de la
 * pestaña y devuelve los contadores para los badges del menú lateral.
 *
 * Sin websockets: el primer pedido va sin cursor y sólo sirve para
 * enterarse de dónde está parado, así no avisa de lo viejo al entrar.
 */
export function useAvisosWhatsapp(): ContadoresWhatsapp | null {
    const page = usePage();
    const compartidos = page.props.whatsapp ?? null;
    const habilitado = compartidos !== null;
    const enLaBandeja = page.component === PAGINA_BANDEJA;

    const [contadores, setContadores] = useState<ContadoresWhatsapp | null>(
        compartidos,
    );

    /* Lo que llega con cada visita de Inertia (las páginas completas traen
       los contadores compartidos) también vale. Se ajusta durante el
       render, comparando con lo último que se compartió. */
    const [ultimosCompartidos, setUltimosCompartidos] =
        useState<ContadoresWhatsapp | null>(compartidos);

    if (
        compartidos !== null &&
        (compartidos.noLeidos !== ultimosCompartidos?.noLeidos ||
            compartidos.atencionHumana !== ultimosCompartidos?.atencionHumana)
    ) {
        setUltimosCompartidos(compartidos);
        setContadores(compartidos);
    }

    /* La página actual, para saber dentro del intervalo si el chat del
       aviso es el que está abierto. */
    const paginaActual = useRef(page);

    useEffect(() => {
        paginaActual.current = page;
    }, [page]);

    const http = useHttp<Record<string, never>, AvisosWhatsapp>({});
    const pedir = useRef(http.get);

    useEffect(() => {
        pedir.current = http.get;
    }, [http.get]);

    const cursor = useRef<Cursor | null>(null);

    useEffect(() => {
        if (!habilitado) {
            return;
        }

        let cancelado = false;
        let pidiendo = false;

        const avisar = (evento: EventoWhatsapp): boolean => {
            const actual = paginaActual.current;
            const abierta = (
                actual.props.conversacion as { id?: number } | null | undefined
            )?.id;

            if (
                actual.component === PAGINA_BANDEJA &&
                abierta === evento.conversacionId &&
                document.visibilityState === 'visible'
            ) {
                return false;
            }

            const url = show.url(evento.conversacionId);

            void mostrarNotificacion({
                titulo:
                    evento.tipo === 'derivacion'
                        ? `${evento.nombre} necesita una persona`
                        : evento.nombre,
                cuerpo: evento.preview,
                etiqueta: `whatsapp-${evento.conversacionId}`,
                url,
                alTocar: () => router.visit(url),
            });

            return true;
        };

        const consultar = async (): Promise<void> => {
            if (pidiendo) {
                return;
            }

            pidiendo = true;

            try {
                const respuesta = await pedir.current(
                    avisos.url(
                        cursor.current === null
                            ? undefined
                            : { query: { ...cursor.current } },
                    ),
                );

                if (cancelado) {
                    return;
                }

                setContadores({
                    noLeidos: respuesta.noLeidos,
                    atencionHumana: respuesta.atencionHumana,
                });

                if (cursor.current !== null) {
                    const avisados = respuesta.eventos
                        .slice()
                        .reverse()
                        .filter(avisar);

                    if (avisados.length > 0) {
                        sonarAviso();
                    }
                }

                cursor.current = respuesta.cursor;
            } catch {
                // Sin red o sesión vencida: se reintenta en la próxima vuelta.
            } finally {
                pidiendo = false;
            }
        };

        void consultar();
        const intervalo = window.setInterval(
            () => void consultar(),
            enLaBandeja ? CADA_MS_EN_LA_BANDEJA : CADA_MS,
        );

        return () => {
            cancelado = true;
            window.clearInterval(intervalo);
        };
    }, [habilitado, enLaBandeja]);

    // El navegador sólo deja sonar después de un gesto del usuario.
    useEffect(() => {
        if (!habilitado) {
            return;
        }

        const desbloquear = () => desbloquearSonido();

        window.addEventListener('pointerdown', desbloquear);
        window.addEventListener('keydown', desbloquear);

        return () => {
            window.removeEventListener('pointerdown', desbloquear);
            window.removeEventListener('keydown', desbloquear);
        };
    }, [habilitado]);

    // El clic en una notificación del service worker llega como mensaje.
    useEffect(() => {
        if (!habilitado || !('serviceWorker' in navigator)) {
            return;
        }

        void registrarServiceWorker();

        const alRecibir = (event: MessageEvent) => {
            const datos = event.data as { tipo?: string; url?: string } | null;

            if (datos?.tipo === 'abrir-conversacion' && datos.url) {
                router.visit(datos.url);
            }
        };

        navigator.serviceWorker.addEventListener('message', alRecibir);

        return () =>
            navigator.serviceWorker.removeEventListener('message', alRecibir);
    }, [habilitado]);

    // «(3) WhatsApp - Alfa»: Inertia cambia el título en cada visita.
    const noLeidos = contadores?.noLeidos ?? 0;

    useEffect(() => {
        const aplicar = () => {
            const base = document.title.replace(PREFIJO_TITULO, '');
            const titulo = noLeidos > 0 ? `(${noLeidos}) ${base}` : base;

            if (document.title !== titulo) {
                document.title = titulo;
            }
        };

        aplicar();

        const observador = new MutationObserver(aplicar);
        observador.observe(document.head, {
            childList: true,
            subtree: true,
            characterData: true,
        });

        return () => {
            observador.disconnect();
            document.title = document.title.replace(PREFIJO_TITULO, '');
        };
    }, [noLeidos]);

    return habilitado ? contadores : null;
}
