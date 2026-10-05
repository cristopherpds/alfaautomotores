import { Input, Label, Search } from 'alfa-ui';

export const ConEtiqueta = () => (
    <div className="grid w-72 gap-2">
        <Label htmlFor="marca">Marca</Label>
        <Input id="marca" placeholder="Fiat, Chevrolet, Chery…" />
    </div>
);

export const Buscador = () => (
    <div className="relative w-72">
        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
        <Input type="search" placeholder="Buscar por nombre o celular" className="pl-8" />
    </div>
);

export const Estados = () => (
    <div className="grid w-72 gap-4">
        <div className="grid gap-2">
            <Label htmlFor="precio">Precio (USD)</Label>
            <Input id="precio" type="number" defaultValue="23900" />
        </div>
        <div className="grid gap-2">
            <Label htmlFor="km">Kilometraje</Label>
            <Input id="km" aria-invalid defaultValue="-100" />
            <p className="text-sm text-destructive">El kilometraje no puede ser negativo.</p>
        </div>
        <div className="grid gap-2">
            <Label htmlFor="slug">Slug</Label>
            <Input id="slug" disabled defaultValue="strada-freedom-24" />
        </div>
    </div>
);
