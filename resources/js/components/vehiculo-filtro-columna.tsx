import { ListFilter } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export type OpcionFiltro = {
    value: string;
    label: string;
};

type VehiculoFiltroColumnaProps = {
    /** El encabezado de la columna: el filtro reemplaza al texto suelto. */
    titulo: string;
    /**
     * Qué filtra, cuando no coincide con el encabezado. La columna "Vehículo"
     * muestra el título del auto pero filtra por carrocería.
     */
    etiqueta?: string;
    opciones: OpcionFiltro[];
    seleccion: string[];
    onChange: (seleccion: string[]) => void;
};

/**
 * Filtro multi-selección para la cabecera de una columna.
 *
 * Sin nada tildado la columna no filtra; con uno o más valores muestra la
 * cantidad en un Badge, así se ve de un vistazo qué columnas están acotando la
 * tabla sin tener que abrir cada menú.
 */
export default function VehiculoFiltroColumna({
    titulo,
    etiqueta = titulo,
    opciones,
    seleccion,
    onChange,
}: VehiculoFiltroColumnaProps) {
    const activo = seleccion.length > 0;

    const alternar = (value: string, marcado: boolean) => {
        onChange(
            marcado
                ? [...seleccion, value]
                : seleccion.filter((elegido) => elegido !== value),
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    className="-ml-3 data-[state=open]:bg-accent"
                    data-test={`filtro-${etiqueta.toLowerCase()}-trigger`}
                >
                    {titulo}
                    {activo && (
                        <Badge variant="secondary">{seleccion.length}</Badge>
                    )}
                    <ListFilter />
                    <span className="sr-only">Filtrar por {etiqueta}</span>
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="start">
                <DropdownMenuLabel>Filtrar por {etiqueta}</DropdownMenuLabel>

                <DropdownMenuGroup>
                    {opciones.map((opcion) => (
                        <DropdownMenuCheckboxItem
                            key={opcion.value}
                            checked={seleccion.includes(opcion.value)}
                            onCheckedChange={(marcado) =>
                                alternar(opcion.value, marcado)
                            }
                            onSelect={(evento) => evento.preventDefault()}
                        >
                            {opcion.label}
                        </DropdownMenuCheckboxItem>
                    ))}
                </DropdownMenuGroup>

                {activo && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuGroup>
                            <DropdownMenuItem onSelect={() => onChange([])}>
                                Quitar filtro
                            </DropdownMenuItem>
                        </DropdownMenuGroup>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
