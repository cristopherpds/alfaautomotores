import { Badge, Check } from 'alfa-ui';

export const Variants = () => (
    <div className="flex flex-wrap items-center gap-2">
        <Badge>Publicado</Badge>
        <Badge variant="secondary">Confirmado</Badge>
        <Badge variant="outline">Pendiente</Badge>
        <Badge variant="destructive">Cancelado</Badge>
    </div>
);

export const ConIcono = () => (
    <div className="flex flex-wrap items-center gap-2">
        <Badge variant="secondary">
            <Check />
            Completado
        </Badge>
        <Badge variant="outline" className="tabular-nums">
            12
        </Badge>
    </div>
);
