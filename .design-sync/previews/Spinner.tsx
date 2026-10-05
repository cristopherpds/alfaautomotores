import { Button, Spinner } from 'alfa-ui';

export const Tamanos = () => (
    <div className="flex items-center gap-4">
        <Spinner />
        <Spinner className="size-6" />
        <Spinner className="size-8 text-muted-foreground" />
    </div>
);

export const EnBoton = () => (
    <Button disabled>
        <Spinner />
        Exportando…
    </Button>
);
