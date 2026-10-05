import type { AccionAuditoria } from '@/types';

/**
 * Las etiquetas de la auditoría, compartidas por `/panel/auditoria` y la
 * actividad reciente del dashboard.
 */

/** Los tipos que escribe `tipoDeAuditoria()` en cada modelo. */
export const TIPOS_AUDITORIA: Record<string, string> = {
    vehiculo: 'Vehículo',
    foto_vehiculo: 'Foto de vehículo',
    producto: 'Producto',
    foto_producto: 'Foto de producto',
    entrega: 'Entrega',
    hero: 'Slide de portada',
    servicio: 'Servicio',
    turno: 'Turno',
    cliente: 'Cliente',
    usuario: 'Usuario',
    ajuste: 'Ajuste del sitio',
};

export const ACCIONES_AUDITORIA: Record<
    AccionAuditoria,
    { label: string; variant: 'default' | 'secondary' | 'destructive' }
> = {
    creado: { label: 'Creó', variant: 'default' },
    modificado: { label: 'Modificó', variant: 'secondary' },
    eliminado: { label: 'Eliminó', variant: 'destructive' },
};

/** El tipo en palabras; uno que todavía no esté en el mapa sale crudo. */
export function tipoLegible(tipo: string): string {
    return TIPOS_AUDITORIA[tipo] ?? tipo;
}
