import { Head, router, setLayoutProps, usePoll } from '@inertiajs/react';
import { MessageSquareDashed, RefreshCw, TriangleAlert } from 'lucide-react';
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
    filtros: FiltrosBandeja;
    whatsappConfigurado: boolean;
    conversacion: ConversacionAbierta | null;
};

/** Cada cuánto se pide de nuevo la bandeja: no hay websockets. Con la
    pestaña en segundo plano Inertia lo espacia solo. */
const REFRESCO_MS = 4000;

/**
 * La bandeja de WhatsApp: conversaciones, hilo abierto y contacto.
 */
export default function Whatsapp({
    conversaciones,
    filtros,
    whatsappConfigurado,
    conversacion,
}: Props) {
    usePoll(
        REFRESCO_MS,
        { only: ['conversaciones', 'conversacion'] },
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
                        'grid min-h-0 flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 bg-background',
                        'grid-cols-1 md:grid-cols-[20rem_1fr] xl:grid-cols-[20rem_1fr_18rem]',
                    )}
                >
                    {/* En el celular se ve una sola columna: la lista o el hilo. */}
                    <div
                        className={cn(
                            'min-h-0',
                            conversacion ? 'hidden md:grid' : 'grid',
                        )}
                    >
                        <ListaConversaciones
                            conversaciones={conversaciones}
                            filtros={filtros}
                            abiertaId={conversacion?.id ?? null}
                            onFiltrar={filtrar}
                        />
                    </div>

                    {conversacion ? (
                        <>
                            <Hilo conversacion={conversacion} />
                            <PanelContacto conversacion={conversacion} />
                        </>
                    ) : (
                        <Empty className="hidden md:flex xl:col-span-2">
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
