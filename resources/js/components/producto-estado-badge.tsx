import { Badge } from '@/components/ui/badge';
import type { EstadoProductoAdmin } from '@/types';

/**
 * Etiqueta interna del estado. Como en `vehiculo-estado-badge.tsx`, no usa
 * `estadoLegible()` de `lib/productos.ts`: ese es el texto del cliente y no
 * conoce el borrador.
 */
const estadoVariants: Record<
    EstadoProductoAdmin,
    {
        label: string;
        variant: 'default' | 'secondary' | 'outline' | 'destructive';
    }
> = {
    borrador: { label: 'Borrador', variant: 'outline' },
    publicado: { label: 'Publicado', variant: 'default' },
    por_encargue: { label: 'Por encargue', variant: 'secondary' },
    sin_stock: { label: 'Sin stock', variant: 'destructive' },
};

export default function ProductoEstadoBadge({
    estado,
}: {
    estado: EstadoProductoAdmin;
}) {
    const { label, variant } = estadoVariants[estado];

    return <Badge variant={variant}>{label}</Badge>;
}
