import { Label, Switch } from 'alfa-ui';

export const Estados = () => (
    <div className="grid gap-3">
        <div className="flex items-center gap-2">
            <Switch id="s1" defaultChecked />
            <Label htmlFor="s1">Mostrar en la portada</Label>
        </div>
        <div className="flex items-center gap-2">
            <Switch id="s2" />
            <Label htmlFor="s2">Disponible siempre</Label>
        </div>
        <div className="flex items-center gap-2">
            <Switch id="s3" disabled />
            <Label htmlFor="s3">Contacto de respaldo</Label>
        </div>
    </div>
);

export const EnFila = () => (
    <div className="flex w-96 items-center justify-between rounded-lg border p-4">
        <div className="grid gap-1">
            <p className="text-sm font-medium">Botón de WhatsApp</p>
            <p className="text-sm text-muted-foreground">Se muestra en todas las páginas del sitio.</p>
        </div>
        <Switch defaultChecked />
    </div>
);
