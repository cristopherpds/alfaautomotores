import { Head, router, setLayoutProps, usePoll } from '@inertiajs/react';
import { MessageSquareDashed, RefreshCw, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import ConversacionController from '@/actions/App/Http/Controllers/Panel/ConversacionController';
import PlantillaWhatsappController from '@/actions/App/Http/Controllers/Panel/PlantillaWhatsappController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { ActivarAvisosWhatsapp } from '@/components/whatsapp/avisos-whatsapp-ajustes';
import { Hilo } from '@/components/whatsapp/hilo';
import {
    filtrarBandeja,
    ListaConversaciones,
} from '@/components/whatsapp/lista-conversaciones';
import type { FiltrosBandeja } from '@/components/whatsapp/lista-conversaciones';
import { PanelContacto } from '@/components/whatsapp/panel-contacto';
import { cn } from '@/lib/utils';
import { index } from '@/routes/panel/whatsapp';
import type { ConversacionAbierta, ConversacionDeLista } from '@/types';

type Props = {
    conversaciones: ConversacionDeLista[];
    atencionHumana: number;
    filtros: FiltrosBandeja;
    whatsappConfigurado: boolean;
    conversacion: ConversacionAbierta | null;
};

/** Cada cuánto se pide de nuevo la bandeja: no hay websockets. Con la
    pestaña en segundo plano Inertia lo espacia solo. */
const REFRESCO_MS = 4000;

/** Dónde recuerda este navegador si el panel del contacto queda abierto. */
const PANEL_CONTACTO = 'alfa.bandeja.panel-contacto';

function panelContactoGuardado(): boolean {
    try {
        return window.localStorage.getItem(PANEL_CONTACTO) !== 'cerrado';
    } catch {
        return true;
    }
}

/**
 * La bandeja de WhatsApp: conversaciones, hilo abierto y contacto.
 */
export default function Whatsapp({
    conversaciones,
    atencionHumana,
    filtros,
    whatsappConfigurado,
    conversacion,
}: Props) {
    const [panelAbierto, setPanelAbierto] = useState(panelContactoGuardado);

    const mostrarPanel = (abierto: boolean) => {
        setPanelAbierto(abierto);

        try {
            window.localStorage.setItem(
                PANEL_CONTACTO,
                abierto ? 'abierto' : 'cerrado',
            );
        } catch {
            // Sin almacenamiento, el panel vuelve a abrirse al recargar.
        }
    };

    usePoll(
        REFRESCO_MS,
        { only: ['conversaciones', 'atencionHumana', 'conversacion'] },
        { mode: 'rest' },
    );

    setLayoutProps({
        breadcrumbs: [
            { title: 'WhatsApp', href: index() },
            ...(conversacion
                ? [
                      {
                          title: conversacion.nombre,
                          href: ConversacionController.show(conversacion.id),
                      },
                  ]
                : []),
        ],
    });

    const filtrar = (cambio: Partial<FiltrosBandeja>) =>
        filtrarBandeja(
            conversacion
                ? ConversacionController.show.url(conversacion.id)
                : index.url(),
            { ...filtros, ...cambio },
        );

    return (
        <>
            <Head
                title={
                    conversacion
                        ? `${conversacion.nombre} · WhatsApp`
                        : 'WhatsApp'
                }
            />

            <div className="flex h-[calc(100svh-5rem)] min-h-[32rem] flex-col gap-3 p-4">
                {!whatsappConfigurado && (
                    <Alert>
                        <TriangleAlert />
                        <AlertTitle>WhatsApp no está conectado</AlertTitle>
                        <AlertDescription>
                            Faltan las credenciales de Meta (WHATSAPP_TOKEN y
                            WHATSAPP_PHONE_NUMBER_ID en el .env). Mientras tanto
                            no entran ni salen mensajes.
                        </AlertDescription>
                    </Alert>
                )}

                {/* Sólo mientras no se decidió el permiso del navegador. */}
                <div className="flex justify-end empty:hidden">
                    <ActivarAvisosWhatsapp />
                </div>

                {conversacion?.can.administrar &&
                    !conversacion.ventanaAbierta && (
                        <div className="flex justify-end">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        PlantillaWhatsappController.sincronizar.url(),
                                        {},
                                        {
                                            preserveScroll: true,
                                            preserveState: true,
                                        },
                                    )
                                }
                            >
                                <RefreshCw />
                                Sincronizar plantillas
                            </Button>
                        </div>
                    )}

                <div
                    className={cn(
                        'grid min-h-0 flex-1 overflow-hidden rounded-xl border border-border bg-background shadow-xs',
                        'grid-cols-1 md:grid-cols-[20rem_1fr]',
                        conversacion &&
                            panelAbierto &&
                            'xl:grid-cols-[20rem_1fr_18rem]',
                    )}
                >
                    {/* En el celular se ve una sola columna: la lista o el hilo. */}
                    <div
                        className={cn(
                            'min-h-0 min-w-0',
                            conversacion ? 'hidden md:grid' : 'grid',
                        )}
                    >
                        <ListaConversaciones
                            conversaciones={conversaciones}
                            atencionHumana={atencionHumana}
                            filtros={filtros}
                            abiertaId={conversacion?.id ?? null}
                            onFiltrar={filtrar}
                        />
                    </div>

                    {conversacion ? (
                        <>
                            <Hilo
                                conversacion={conversacion}
                                panelAbierto={panelAbierto}
                                onAbrirPanel={() => mostrarPanel(true)}
                            />
                            {panelAbierto && (
                                <PanelContacto
                                    conversacion={conversacion}
                                    onCerrar={() => mostrarPanel(false)}
                                />
                            )}
                        </>
                    ) : (
                        <Empty className="hidden md:flex">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <MessageSquareDashed />
                                </EmptyMedia>
                                <EmptyTitle>Elegí una conversación</EmptyTitle>
                                <EmptyDescription>
                                    Los mensajes nuevos aparecen solos, sin
                                    recargar la página.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    )}
                </div>
            </div>
        </>
    );
}
