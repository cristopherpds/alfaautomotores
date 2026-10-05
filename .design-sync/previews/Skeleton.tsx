import { Skeleton } from 'alfa-ui';

export const Fila = () => (
    <div className="flex items-center gap-4">
        <Skeleton className="size-12 rounded-full" />
        <div className="space-y-2">
            <Skeleton className="h-4 w-56" />
            <Skeleton className="h-4 w-40" />
        </div>
    </div>
);

export const Tarjeta = () => (
    <div className="grid w-72 gap-3">
        <Skeleton className="aspect-video w-full rounded-xl" />
        <Skeleton className="h-4 w-3/4" />
        <Skeleton className="h-4 w-1/2" />
    </div>
);
