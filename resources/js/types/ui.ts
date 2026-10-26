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
    cursor: { mensaje: number; derivacion: number };
    eventos: EventoWhatsapp[];
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};
