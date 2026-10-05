import { Button, ChevronsUpDown, Collapsible, CollapsibleContent, CollapsibleTrigger } from 'alfa-ui';

const Seccion = ({ abierto }: { abierto: boolean }) => (
    <Collapsible defaultOpen={abierto} className="grid w-80 gap-2">
        <div className="flex items-center justify-between gap-4 px-4">
            <h4 className="text-sm font-semibold">Contactos de WhatsApp</h4>
            <CollapsibleTrigger asChild>
                <Button variant="ghost" size="icon" aria-label="Mostrar contactos">
                    <ChevronsUpDown />
                </Button>
            </CollapsibleTrigger>
        </div>
        <div className="rounded-md border px-4 py-2 text-sm">Ventas · Autos usados</div>
        <CollapsibleContent className="grid gap-2">
            <div className="rounded-md border px-4 py-2 text-sm">Taller · Turnos</div>
            <div className="rounded-md border px-4 py-2 text-sm">Lavadero · Consultas</div>
        </CollapsibleContent>
    </Collapsible>
);

export const Abierto = () => <Seccion abierto />;

export const Cerrado = () => <Seccion abierto={false} />;
