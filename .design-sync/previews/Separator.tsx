import { Separator } from 'alfa-ui';

export const Default = () => (
    <div className="w-72">
        <div className="space-y-1">
            <h4 className="text-sm font-medium">Alfa Automotores</h4>
            <p className="text-sm text-muted-foreground">Automotora, taller y lavadero.</p>
        </div>
        <Separator className="my-4" />
        <div className="flex h-5 items-center gap-4 text-sm">
            <span>Catálogo</span>
            <Separator orientation="vertical" />
            <span>Taller</span>
            <Separator orientation="vertical" />
            <span>Lavadero</span>
        </div>
    </div>
);
