import { Label, Textarea } from 'alfa-ui';

export const ConEtiqueta = () => (
    <div className="grid w-96 gap-2">
        <Label htmlFor="desc">Descripción</Label>
        <Textarea id="desc" defaultValue="Cabina doble, único dueño, service oficial al día. Ideal para trabajo y uso familiar." />
        <p className="text-sm text-muted-foreground">Aparece en la ficha del vehículo.</p>
    </div>
);

export const Vacio = () => (
    <div className="grid w-96 gap-2">
        <Label htmlFor="coment">Comentario del turno</Label>
        <Textarea id="coment" placeholder="Ruido al frenar, revisar pastillas…" />
    </div>
);
