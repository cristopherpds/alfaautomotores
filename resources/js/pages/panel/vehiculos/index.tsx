import { Form, Head, Link, router } from '@inertiajs/react';
import {
    Car,
    ExternalLink,
    ImageOff,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    SearchX,
    Tags,
    Trash2,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import VehiculoController from '@/actions/App/Http/Controllers/Panel/VehiculoController';
import VehiculoLoteController from '@/actions/App/Http/Controllers/Panel/VehiculoLoteController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import VehiculoDestacadoSwitch from '@/components/vehiculo-destacado-switch';
import VehiculoEstadoBadge, {
    ETIQUETAS_ESTADO,
} from '@/components/vehiculo-estado-badge';
import VehiculoFiltroColumna from '@/components/vehiculo-filtro-columna';
import type { OpcionFiltro } from '@/components/vehiculo-filtro-columna';
import { fmtKm, fmtPrecio } from '@/lib/alfa';
import { create, index } from '@/routes/panel/vehiculos';
import { show } from '@/routes/vehiculos';
import type { EstadoVehiculoAdmin, ManagedVehiculo } from '@/types';

type Props = {
    vehiculos: ManagedVehiculo[];
    puedeCrear: boolean;
    maxDestacados: number;
};

/** Filas por página. El índice trae el stock entero, así que se corta acá. */
const POR_PAGINA = 10;

type Filtros = {
    busqueda: string;
    tipos: string[];
    estados: string[];
    /** 'si' | 'no': tildar los dos es lo mismo que no filtrar. */
    destacado: string[];
};

const SIN_FILTROS: Filtros = {
    busqueda: '',
    tipos: [],
    estados: [],
    destacado: [],
};

const DESTACADO_OPCIONES: OpcionFiltro[] = [
    { value: 'si', label: 'En la portada' },
    { value: 'no', label: 'Fuera de la portada' },
];

/** Sin tildes ni mayúsculas: buscar "citroen" tiene que encontrar "Citroën". */
function normalizar(texto: string): string {
    return texto
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
}

function unicos(valores: string[]): string[] {
    return Array.from(new Set(valores)).sort();
}

/**
 * Los números que muestra el paginador: la primera, la última, la actual y sus
 * vecinas. `gap` marca dónde va el "…" cuando el salto es de más de una página.
 */
function ventanaDePaginas(actual: number, total: number): (number | 'gap')[] {
    if (total <= 7) {
        return Array.from({ length: total }, (_, indice) => indice + 1);
    }

    const cercanas = [1, actual - 1, actual, actual + 1, total]
        .filter((pagina) => pagina >= 1 && pagina <= total)
        .sort((a, b) => a - b);

    return Array.from(new Set(cercanas)).flatMap((pagina, indice, lista) => {
        const anterior = lista[indice - 1];

        return anterior !== undefined && pagina - anterior > 1
            ? (['gap', pagina] as (number | 'gap')[])
            : [pagina];
    });
}

/**
 * La barra que aparece al tildar filas. Cambiar estado y eliminar viajan con
 * la lista de ids: el backend recorre los modelos de a uno, así que el lote
 * respeta las mismas invariantes que una edición suelta.
 */
function VehiculoLoteAcciones({
    seleccion,
    onListo,
}: {
    seleccion: number[];
    onListo: () => void;
}) {
    /* Igual que en las acciones de fila: el diálogo va fuera del menú. */
    const [confirmando, setConfirmando] = useState(false);
    const [enviando, setEnviando] = useState(false);

    /* `preserveState` mantiene los filtros y la página, que son estado local. */
    const comunes = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            setConfirmando(false);
            onListo();
        },
        onFinish: () => setEnviando(false),
    };

    const cambiarEstado = (estado: EstadoVehiculoAdmin) => {
        setEnviando(true);

        router.patch(
            VehiculoLoteController.estado.url(),
            { vehiculos: seleccion, estado },
            comunes,
        );
    };

    const eliminar = () => {
        setEnviando(true);

        router.delete(VehiculoLoteController.destroy.url(), {
            ...comunes,
            data: { vehiculos: seleccion },
        });
    };

    return (
        <div className="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/50 px-3 py-2">
            <p className="text-sm font-medium" aria-live="polite">
                {seleccion.length === 1
                    ? '1 vehículo seleccionado'
                    : `${seleccion.length} vehículos seleccionados`}
            </p>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={enviando}
                        data-test="lote-estado-trigger"
                    >
                        <Tags />
                        Cambiar estado
                    </Button>
                </DropdownMenuTrigger>

                <DropdownMenuContent align="start">
                    <DropdownMenuLabel>Pasar a</DropdownMenuLabel>

                    <DropdownMenuGroup>
                        {Object.entries(ETIQUETAS_ESTADO).map(
                            ([estado, etiqueta]) => (
                                <DropdownMenuItem
                                    key={estado}
                                    onSelect={() =>
                                        cambiarEstado(
                                            estado as EstadoVehiculoAdmin,
                                        )
                                    }
                                    data-test={`lote-estado-${estado}-button`}
                                >
                                    {etiqueta}
                                </DropdownMenuItem>
                            ),
                        )}
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>

            <Button
                variant="destructive"
                size="sm"
                disabled={enviando}
                onClick={() => setConfirmando(true)}
                data-test="lote-eliminar-button"
            >
                <Trash2 />
                Eliminar
            </Button>

            <Button
                variant="ghost"
                size="sm"
                className="ml-auto"
                onClick={onListo}
                data-test="lote-limpiar-button"
            >
                <X />
                Quitar selección
            </Button>

            <Dialog open={confirmando} onOpenChange={setConfirmando}>
                <DialogContent>
                    <DialogTitle>
                        {seleccion.length === 1
                            ? '¿Eliminar el vehículo seleccionado?'
                            : `¿Eliminar los ${seleccion.length} vehículos seleccionados?`}
                    </DialogTitle>
                    <DialogDescription>
                        Se borran también sus fotos. Esta acción no se puede
                        deshacer.
                    </DialogDescription>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">Cancelar</Button>
                        </DialogClose>

                        <Button
                            variant="destructive"
                            disabled={enviando}
                            onClick={eliminar}
                            data-test="confirm-lote-eliminar-button"
                        >
                            Eliminar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function VehiculoRowActions({ vehiculo }: { vehiculo: ManagedVehiculo }) {
    /* El diálogo vive fuera del menú: si colgara del contenido del menú se
       desmontaría junto con él al elegir la opción. */
    const [confirmando, setConfirmando] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        data-test={`vehiculo-${vehiculo.id}-actions`}
                    >
                        <MoreHorizontal />
                        <span className="sr-only">
                            Acciones para {vehiculo.titulo}
                        </span>
                    </Button>
                </DropdownMenuTrigger>

                <DropdownMenuContent align="end">
                    <DropdownMenuLabel>Acciones</DropdownMenuLabel>

                    <DropdownMenuGroup>
                        {vehiculo.estado !== 'borrador' && (
                            <DropdownMenuItem asChild>
                                <a
                                    href={show(vehiculo.slug).url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <ExternalLink />
                                    Ver en el sitio
                                </a>
                            </DropdownMenuItem>
                        )}

                        {vehiculo.can.update && (
                            <DropdownMenuItem asChild>
                                <Link
                                    href={VehiculoController.edit(vehiculo.id)}
                                    data-test={`edit-vehiculo-${vehiculo.id}-link`}
                                >
                                    <Pencil />
                                    Editar vehículo
                                </Link>
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuGroup>

                    {vehiculo.can.delete && (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuGroup>
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() => setConfirmando(true)}
                                    data-test={`delete-vehiculo-${vehiculo.id}-button`}
                                >
                                    <Trash2 />
                                    Eliminar vehículo
                                </DropdownMenuItem>
                            </DropdownMenuGroup>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog open={confirmando} onOpenChange={setConfirmando}>
                <DialogContent>
                    <DialogTitle>¿Eliminar {vehiculo.titulo}?</DialogTitle>
                    <DialogDescription>
                        Se borran también sus fotos. Esta acción no se puede
                        deshacer.
                    </DialogDescription>

                    <Form
                        {...VehiculoController.destroy.form(vehiculo.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setConfirmando(false)}
                    >
                        {({ processing }) => (
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>

                                <Button
                                    variant="destructive"
                                    disabled={processing}
                                    asChild
                                >
                                    <button
                                        type="submit"
                                        data-test={`confirm-delete-vehiculo-${vehiculo.id}-button`}
                                    >
                                        Eliminar
                                    </button>
                                </Button>
                            </DialogFooter>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}

export default function VehiculosIndex({
    vehiculos,
    puedeCrear,
    maxDestacados,
}: Props) {
    /* Cuenta sobre el stock entero, no sobre lo filtrado: el tope de la portada
       no cambia porque estés mirando una página o una búsqueda. */
    const destacados = vehiculos.filter(
        (vehiculo) => vehiculo.destacado,
    ).length;

    const [filtros, setFiltros] = useState<Filtros>(SIN_FILTROS);
    const [pagina, setPagina] = useState(1);
    const [seleccion, setSeleccion] = useState<number[]>([]);

    /* La selección es de la página que se está viendo, así que se limpia cada
       vez que cambian las filas: si no, quedarían tildados ids que ya no se ven
       pero igual se mandarían. */
    const irAPagina = (destino: number) => {
        setPagina(destino);
        setSeleccion([]);
    };

    /* Cualquier cambio de filtro vuelve a la primera página: si estabas en la 3
       y el resultado ahora tiene una sola, la tabla quedaría vacía sin motivo. */
    const actualizar = (cambio: Partial<Filtros>) => {
        setFiltros((previos) => ({ ...previos, ...cambio }));
        setPagina(1);
        setSeleccion([]);
    };

    /* Las opciones salen del stock real, igual que en el catálogo público: si
       mañana entra una carrocería nueva aparece sola en el filtro. */
    const opciones = useMemo(
        () => ({
            tipos: unicos(vehiculos.map((vehiculo) => vehiculo.tipo)).map(
                (tipo): OpcionFiltro => ({ value: tipo, label: tipo }),
            ),
            estados: unicos(vehiculos.map((vehiculo) => vehiculo.estado)).map(
                (estado): OpcionFiltro => ({
                    value: estado,
                    label: ETIQUETAS_ESTADO[
                        estado as keyof typeof ETIQUETAS_ESTADO
                    ],
                }),
            ),
        }),
        [vehiculos],
    );

    const visibles = useMemo(() => {
        const busqueda = normalizar(filtros.busqueda.trim());

        return vehiculos.filter((vehiculo) => {
            const texto = normalizar(
                `${vehiculo.titulo} ${vehiculo.slug} ${vehiculo.tipo} ${vehiculo.anio}`,
            );

            return (
                (busqueda === '' || texto.includes(busqueda)) &&
                (filtros.tipos.length === 0 ||
                    filtros.tipos.includes(vehiculo.tipo)) &&
                (filtros.estados.length === 0 ||
                    filtros.estados.includes(vehiculo.estado)) &&
                (filtros.destacado.length === 0 ||
                    filtros.destacado.includes(
                        vehiculo.destacado ? 'si' : 'no',
                    ))
            );
        });
    }, [vehiculos, filtros]);

    const filtrando =
        filtros.busqueda !== '' ||
        filtros.tipos.length > 0 ||
        filtros.estados.length > 0 ||
        filtros.destacado.length > 0;

    const limpiar = () => {
        setFiltros(SIN_FILTROS);
        setPagina(1);
        setSeleccion([]);
    };

    /* Se acota por las dudas: si borrás un vehículo estando en la última
       página, la lista se achica sin pasar por `actualizar`. */
    const paginas = Math.max(1, Math.ceil(visibles.length / POR_PAGINA));
    const paginaActual = Math.min(pagina, paginas);
    const desde = (paginaActual - 1) * POR_PAGINA;
    const filas = visibles.slice(desde, desde + POR_PAGINA);

    /* La columna de checkboxes no tiene sentido para el rol equipo, que no
       gestiona nada: se oculta entera en vez de mostrarla toda deshabilitada. */
    const gestiona = vehiculos.some((vehiculo) => vehiculo.can.update);
    const seleccionables = filas
        .filter((vehiculo) => vehiculo.can.update)
        .map((vehiculo) => vehiculo.id);
    const todasTildadas =
        seleccionables.length > 0 && seleccionables.length === seleccion.length;

    const alternarFila = (id: number, tildado: boolean) => {
        setSeleccion((previa) =>
            tildado ? [...previa, id] : previa.filter((otro) => otro !== id),
        );
    };

    return (
        <>
            <Head title="Vehículos" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Vehículos"
                        description={`Cargá el stock y elegí cuáles salen en la portada (${destacados} de ${maxDestacados} destacados)`}
                    />

                    {puedeCrear && (
                        <Button asChild data-test="create-vehiculo-button">
                            <Link href={create()}>
                                <Plus />
                                Nuevo vehículo
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative w-full sm:max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />

                        <Input
                            type="search"
                            value={filtros.busqueda}
                            onChange={(evento) =>
                                actualizar({ busqueda: evento.target.value })
                            }
                            placeholder="Buscar por marca, modelo, año o slug"
                            aria-label="Buscar vehículos"
                            className="pl-9"
                            data-test="buscar-vehiculos-input"
                        />
                    </div>

                    {filtrando && (
                        <Button
                            variant="ghost"
                            onClick={limpiar}
                            data-test="limpiar-filtros-button"
                        >
                            <X />
                            Limpiar
                        </Button>
                    )}

                    <p className="ml-auto text-sm text-muted-foreground">
                        {filtrando
                            ? `${visibles.length} de ${vehiculos.length} vehículos`
                            : `${vehiculos.length} vehículos`}
                    </p>
                </div>

                {seleccion.length > 0 && (
                    <VehiculoLoteAcciones
                        seleccion={seleccion}
                        onListo={() => setSeleccion([])}
                    />
                )}

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {gestiona && (
                                    <TableHead className="w-12 pl-4">
                                        <Checkbox
                                            checked={
                                                todasTildadas
                                                    ? true
                                                    : seleccion.length > 0
                                                      ? 'indeterminate'
                                                      : false
                                            }
                                            disabled={
                                                seleccionables.length === 0
                                            }
                                            onCheckedChange={(tildado) =>
                                                setSeleccion(
                                                    tildado
                                                        ? seleccionables
                                                        : [],
                                                )
                                            }
                                            aria-label="Seleccionar los vehículos de esta página"
                                            data-test="seleccionar-todos-checkbox"
                                        />
                                    </TableHead>
                                )}

                                <TableHead className="w-20 px-4">
                                    Foto
                                </TableHead>

                                <TableHead>
                                    <VehiculoFiltroColumna
                                        titulo="Vehículo"
                                        etiqueta="carrocería"
                                        opciones={opciones.tipos}
                                        seleccion={filtros.tipos}
                                        onChange={(tipos) =>
                                            actualizar({ tipos })
                                        }
                                    />
                                </TableHead>

                                <TableHead>Año</TableHead>
                                <TableHead>Kilómetros</TableHead>
                                <TableHead>Precio</TableHead>

                                <TableHead>
                                    <VehiculoFiltroColumna
                                        titulo="Estado"
                                        opciones={opciones.estados}
                                        seleccion={filtros.estados}
                                        onChange={(estados) =>
                                            actualizar({ estados })
                                        }
                                    />
                                </TableHead>

                                <TableHead>
                                    <VehiculoFiltroColumna
                                        titulo="Destacado"
                                        opciones={DESTACADO_OPCIONES}
                                        seleccion={filtros.destacado}
                                        onChange={(destacado) =>
                                            actualizar({ destacado })
                                        }
                                    />
                                </TableHead>

                                <TableHead className="w-12 px-4">
                                    <span className="sr-only">Acciones</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {filas.length === 0 && (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell colSpan={gestiona ? 9 : 8}>
                                        <Empty>
                                            <EmptyHeader>
                                                <EmptyMedia variant="icon">
                                                    {filtrando ? (
                                                        <SearchX />
                                                    ) : (
                                                        <Car />
                                                    )}
                                                </EmptyMedia>

                                                <EmptyTitle>
                                                    {filtrando
                                                        ? 'Ningún vehículo coincide'
                                                        : 'Todavía no hay vehículos cargados'}
                                                </EmptyTitle>

                                                <EmptyDescription>
                                                    {filtrando
                                                        ? 'Probá con otra búsqueda o sacá alguno de los filtros de las columnas.'
                                                        : 'Cargá el primero y después subile las fotos.'}
                                                </EmptyDescription>
                                            </EmptyHeader>

                                            <EmptyContent>
                                                {filtrando ? (
                                                    <Button
                                                        variant="outline"
                                                        onClick={limpiar}
                                                    >
                                                        <X />
                                                        Limpiar filtros
                                                    </Button>
                                                ) : (
                                                    puedeCrear && (
                                                        <Button asChild>
                                                            <Link
                                                                href={create()}
                                                            >
                                                                <Plus />
                                                                Nuevo vehículo
                                                            </Link>
                                                        </Button>
                                                    )
                                                )}
                                            </EmptyContent>
                                        </Empty>
                                    </TableCell>
                                </TableRow>
                            )}

                            {filas.map((vehiculo) => (
                                <TableRow
                                    key={vehiculo.id}
                                    data-state={
                                        seleccion.includes(vehiculo.id)
                                            ? 'selected'
                                            : undefined
                                    }
                                >
                                    {gestiona && (
                                        <TableCell className="pl-4">
                                            <Checkbox
                                                checked={seleccion.includes(
                                                    vehiculo.id,
                                                )}
                                                disabled={!vehiculo.can.update}
                                                onCheckedChange={(tildado) =>
                                                    alternarFila(
                                                        vehiculo.id,
                                                        tildado === true,
                                                    )
                                                }
                                                aria-label={`Seleccionar ${vehiculo.titulo}`}
                                                data-test={`seleccionar-vehiculo-${vehiculo.id}-checkbox`}
                                            />
                                        </TableCell>
                                    )}

                                    <TableCell className="px-4">
                                        {vehiculo.portada ? (
                                            <img
                                                src={vehiculo.portada}
                                                alt=""
                                                className="aspect-4/3 w-16 rounded-md object-cover"
                                            />
                                        ) : (
                                            <span
                                                className="flex aspect-4/3 w-16 items-center justify-center rounded-md bg-muted text-muted-foreground"
                                                aria-label="Sin fotos"
                                            >
                                                <ImageOff />
                                            </span>
                                        )}
                                    </TableCell>

                                    <TableCell className="font-medium">
                                        {vehiculo.titulo}
                                        <span className="block text-xs text-muted-foreground">
                                            {vehiculo.tipo} · {vehiculo.slug}
                                        </span>
                                    </TableCell>

                                    <TableCell className="text-muted-foreground">
                                        {vehiculo.anio}
                                    </TableCell>

                                    <TableCell className="text-muted-foreground">
                                        {fmtKm(vehiculo.km)}
                                    </TableCell>

                                    <TableCell>
                                        {fmtPrecio(
                                            vehiculo.precio,
                                            vehiculo.moneda,
                                        )}
                                    </TableCell>

                                    <TableCell>
                                        <VehiculoEstadoBadge
                                            estado={vehiculo.estado}
                                        />
                                    </TableCell>

                                    <TableCell>
                                        <VehiculoDestacadoSwitch
                                            vehiculoId={vehiculo.id}
                                            destacado={vehiculo.destacado}
                                            disabled={
                                                !vehiculo.can.update ||
                                                !vehiculo.destacable
                                            }
                                            aria-label={`Destacar ${vehiculo.titulo} en la portada`}
                                        />
                                    </TableCell>

                                    <TableCell className="px-4 text-right">
                                        <VehiculoRowActions
                                            vehiculo={vehiculo}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                {paginas > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-sm text-muted-foreground">
                            Mostrando {desde + 1}–{desde + filas.length} de{' '}
                            {visibles.length}
                        </p>

                        <Pagination className="mx-0 w-auto">
                            <PaginationContent>
                                <PaginationItem>
                                    <PaginationPrevious
                                        onClick={() =>
                                            irAPagina(paginaActual - 1)
                                        }
                                        disabled={paginaActual === 1}
                                    />
                                </PaginationItem>

                                {ventanaDePaginas(paginaActual, paginas).map(
                                    (pagina, indice) => (
                                        <PaginationItem
                                            key={
                                                pagina === 'gap'
                                                    ? `gap-${indice}`
                                                    : pagina
                                            }
                                        >
                                            {pagina === 'gap' ? (
                                                <PaginationEllipsis />
                                            ) : (
                                                <PaginationLink
                                                    isActive={
                                                        pagina === paginaActual
                                                    }
                                                    onClick={() =>
                                                        irAPagina(pagina)
                                                    }
                                                    aria-label={`Ir a la página ${pagina}`}
                                                >
                                                    {pagina}
                                                </PaginationLink>
                                            )}
                                        </PaginationItem>
                                    ),
                                )}

                                <PaginationItem>
                                    <PaginationNext
                                        onClick={() =>
                                            irAPagina(paginaActual + 1)
                                        }
                                        disabled={paginaActual === paginas}
                                    />
                                </PaginationItem>
                            </PaginationContent>
                        </Pagination>
                    </div>
                )}
            </div>
        </>
    );
}

VehiculosIndex.layout = {
    breadcrumbs: [{ title: 'Vehículos', href: index() }],
};
