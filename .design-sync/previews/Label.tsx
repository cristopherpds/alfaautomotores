import { Input, Label, Switch } from 'alfa-ui';

export const ConCampo = () => (
    <div className="grid w-72 gap-2">
        <Label htmlFor="nombre">Nombre del cliente</Label>
        <Input id="nombre" defaultValue="Lucía Ferreira" />
    </div>
);

export const ConSwitch = () => (
    <div className="flex items-center gap-2">
        <Switch id="activo" defaultChecked />
        <Label htmlFor="activo">Botón de WhatsApp activo</Label>
    </div>
);
