import { Checkbox, Label } from 'alfa-ui';

export const ConEtiqueta = () => (
    <div className="grid gap-3">
        <div className="flex items-center gap-2">
            <Checkbox id="publicar" defaultChecked />
            <Label htmlFor="publicar">Publicar en el catálogo</Label>
        </div>
        <div className="flex items-center gap-2">
            <Checkbox id="destacar" />
            <Label htmlFor="destacar">Destacar en la portada</Label>
        </div>
        <div className="flex items-center gap-2">
            <Checkbox id="vendido" disabled />
            <Label htmlFor="vendido">Marcar como vendido</Label>
        </div>
    </div>
);

export const Invalido = () => (
    <div className="flex items-center gap-2">
        <Checkbox id="terminos" aria-invalid />
        <Label htmlFor="terminos">Acepto los términos del turno</Label>
    </div>
);
