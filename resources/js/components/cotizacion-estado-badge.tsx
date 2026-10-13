import { Badge } from '@/components/ui/badge';
import type { EstadoCotizacion } from '@/types';

/** Una nueva resalta; las ya atendidas pasan a segundo plano. */
const estadoVariants: Record<
    EstadoCotizacion,
    { label: string; variant: 'default' | 'secondary' | 'outline' }
> = {
    nueva: { label: 'Nueva', variant: 'default' },
    contactada: { label: 'Contactada', variant: 'secondary' },
    descartada: { label: 'Descartada', variant: 'outline' },
};

export default function CotizacionEstadoBadge({
    estado,
}: {
    estado: EstadoCotizacion;
}) {
    const { label, variant } = estadoVariants[estado];

    return <Badge variant={variant}>{label}</Badge>;
}
