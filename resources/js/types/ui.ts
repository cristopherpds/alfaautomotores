import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

/** Los contadores de WhatsApp del menú lateral y del título de la pestaña. */
export type ContadoresWhatsapp = {
    noLeidos: number;
    atencionHumana: number;
};

/** Algo que pasó en WhatsApp desde el último pedido de avisos. */
export type EventoWhatsapp = {
    conversacionId: number;
    nombre: string;
    preview: string;
    tipo: 'mensaje' | 'derivacion';
    at: string;
};

/** La respuesta de `panel.whatsapp.avisos`. */
export type AvisosWhatsapp = ContadoresWhatsapp & {
    cursor: { mensaje: number; derivacion: number; derivacionId: number };
    eventos: EventoWhatsapp[];
};

/** Un aviso de la campana: una cotización o un turno que entró. */
export type Notificacion = {
    id: string;
    tipo: 'cotizacion' | 'turno' | string;
    titulo: string;
    detalle: string;
    url: string | null;
    leida: boolean;
    at: string;
};

/** La respuesta de `panel.notificaciones.*`. */
export type ResumenNotificaciones = {
    noLeidas: number;
    avisos: Notificacion[];
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};
