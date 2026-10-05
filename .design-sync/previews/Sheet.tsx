import {
    Button,
    Input,
    Label,
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from 'alfa-ui';

export const Filtros = () => (
    <Sheet defaultOpen>
        <SheetContent>
            <SheetHeader>
                <SheetTitle>Filtrar clientes</SheetTitle>
                <SheetDescription>Acotá la lista por nombre, celular o fecha del último turno.</SheetDescription>
            </SheetHeader>
            <div className="grid gap-4 px-4">
                <div className="grid gap-2">
                    <Label htmlFor="buscar">Nombre o celular</Label>
                    <Input id="buscar" placeholder="Ferreira, 098 776 210…" />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="desde">Último turno desde</Label>
                    <Input id="desde" type="date" defaultValue="2026-08-01" />
                </div>
            </div>
            <SheetFooter>
                <Button>Aplicar filtros</Button>
                <SheetClose asChild>
                    <Button variant="outline">Cerrar</Button>
                </SheetClose>
            </SheetFooter>
        </SheetContent>
    </Sheet>
);
