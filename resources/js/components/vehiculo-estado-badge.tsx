import { Badge } from '@/components/ui/badge';
import type { EstadoVehiculoAdmin } from '@/types';

/**
 * Etiqueta interna del estado. No usa `estadoLegible()` de `lib/catalogo.ts`:
 * ese traduce `publicado` a "Disponible" para el cliente, y en el panel hace
 * falta el nombre real.
 */
const estadoVariants: Record<
    EstadoVehiculoAdmin,
    {
        label: string;
        variant: 'default' | 'secondary' | 'outline' | 'destructive';
    }
> = {
    borrador: { label: 'Borrador', variant: 'outline' },
    publicado: { label: 'Publicado', variant: 'default' },
    reservado: { label: 'Reservado', variant: 'secondary' },
    vendido: { label: 'Vendido', variant: 'destructive' },
};

/** Las mismas etiquetas, para el filtro de la columna Estado. */
export const ETIQUETAS_ESTADO: Record<EstadoVehiculoAdmin, string> =
    Object.fromEntries(
        Object.entries(estadoVariants).map(([estado, { label }]) => [
            estado,
            label,
        ]),
    ) as Record<EstadoVehiculoAdmin, string>;

export default function VehiculoEstadoBadge({
    estado,
}: {
    estado: EstadoVehiculoAdmin;
}) {
    const { label, variant } = estadoVariants[estado];

    return <Badge variant={variant}>{label}</Badge>;
}
