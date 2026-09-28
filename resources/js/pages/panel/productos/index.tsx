import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    Bike,
    ExternalLink,
    ImageOff,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    SearchX,
    Trash2,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import ProductoController from '@/actions/App/Http/Controllers/Panel/ProductoController';
import Heading from '@/components/heading';
import ProductoEstadoBadge from '@/components/producto-estado-badge';
import { Button } from '@/components/ui/button';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import VehiculoFiltroColumna from '@/components/vehiculo-filtro-columna';
import { fmtPrecio } from '@/lib/alfa';
import { familiaLegible } from '@/lib/productos';
import { indiceDeSeccion, TITULO_SECCION } from '@/lib/productos-panel';
import { create } from '@/routes/panel/productos';
import { show } from '@/routes/productos';
import type { ManagedProducto, OpcionSelect, SeccionProducto } from '@/types';

type Props = {
    seccion: SeccionProducto;
    productos: ManagedProducto[];
    familias: OpcionSelect[];
    estados: OpcionSelect[];
    puedeCrear: boolean;
};

type Filtros = {
    busqueda: string;
    familias: string[];
    estados: string[];
};

const SIN_FILTROS: Filtros = { busqueda: '', familias: [], estados: [] };

const DESCRIPCION: Record<SeccionProducto, string> = {
    movilidad:
        'Motos, scooters, monopatines, hoverboards, triciclos y bicicletas eléctricas de /movilidad',
    bicicletas:
        'Las bicicletas sin motor de /bicicletas; se publican sin precio y se cotizan por WhatsApp',
};

/** Sin tildes ni mayúsculas: buscar "monopatin" tiene que encontrar "Monopatín". */
function normalizar(texto: string): string {
    return texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

function ProductoRowActions({ producto }: { producto: ManagedProducto }) {
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
                        data-test={`producto-${producto.id}-actions`}
                    >
                        <MoreHorizontal />
                        <span className="sr-only">
                            Acciones para {producto.nombre}
                        </span>
                    </Button>
                </DropdownMenuTrigger>

                <DropdownMenuContent align="end">
                    <DropdownMenuLabel>Acciones</DropdownMenuLabel>

                    <DropdownMenuGroup>
                        {producto.estado !== 'borrador' && (
                            <DropdownMenuItem asChild>
                                <a
                                    href={show(producto.slug).url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <ExternalLink />
                                    Ver en el sitio
                                </a>
                            </DropdownMenuItem>
                        )}

                        {producto.can.update && (
                            <DropdownMenuItem asChild>
                                <Link
                                    href={ProductoController.edit(producto.id)}
                                    data-test={`edit-producto-${producto.id}-link`}
                                >
                                    <Pencil />
                                    Editar producto
                                </Link>
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuGroup>

                    {producto.can.delete && (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuGroup>
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() => setConfirmando(true)}
                                    data-test={`delete-producto-${producto.id}-button`}
                                >
                                    <Trash2 />
                                    Eliminar producto
                                </DropdownMenuItem>
                            </DropdownMenuGroup>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog open={confirmando} onOpenChange={setConfirmando}>
                <DialogContent>
                    <DialogTitle>¿Eliminar {producto.nombre}?</DialogTitle>
                    <DialogDescription>
                        Se borran también sus fotos. Esta acción no se puede
                        deshacer.
                    </DialogDescription>

                    <Form
                        {...ProductoController.destroy.form(producto.id)}
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
                                        data-test={`confirm-delete-producto-${producto.id}-button`}
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

/**
 * El catálogo de una sección, borradores incluidos. Son unas decenas de filas,
 * así que la búsqueda y los filtros son de navegador y no hay paginación.
 */
export default function ProductosIndex({
    seccion,
    productos,
    familias,
    estados,
    puedeCrear,
}: Props) {
    const titulo = TITULO_SECCION[seccion];

    setLayoutProps({
        breadcrumbs: [{ title: titulo, href: indiceDeSeccion(seccion) }],
    });

    const [filtros, setFiltros] = useState<Filtros>(SIN_FILTROS);

    /* Pasar de Movilidad a Bicicletas desde el sidebar puede reusar el mismo
       componente: los filtros de la otra sección se descartan acá. */
    const [seccionFiltrada, setSeccionFiltrada] = useState(seccion);

    if (seccionFiltrada !== seccion) {
        setSeccionFiltrada(seccion);
        setFiltros(SIN_FILTROS);
    }

    const actualizar = (cambio: Partial<Filtros>) =>
        setFiltros((previos) => ({ ...previos, ...cambio }));

    const visibles = useMemo(() => {
        const busqueda = normalizar(filtros.busqueda.trim());

        return productos.filter((producto) => {
            const texto = normalizar(
                `${producto.nombre} ${producto.slug} ${producto.codigo ?? ''}`,
            );

            return (
                (busqueda === '' || texto.includes(busqueda)) &&
                (filtros.familias.length === 0 ||
                    filtros.familias.includes(producto.familia)) &&
                (filtros.estados.length === 0 ||
                    filtros.estados.includes(producto.estado))
            );
        });
    }, [productos, filtros]);

    const filtrando =
        filtros.busqueda !== '' ||
        filtros.familias.length > 0 ||
        filtros.estados.length > 0;

    const limpiar = () => setFiltros(SIN_FILTROS);

    return (
        <>
            <Head title={titulo} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={titulo}
                        description={DESCRIPCION[seccion]}
                    />

                    {puedeCrear && (
                        <Button asChild data-test="create-producto-button">
                            <Link href={create({ query: { seccion } })}>
                                <Plus />
                                Nuevo producto
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
                            placeholder="Buscar por nombre, código o slug"
                            aria-label="Buscar productos"
                            className="pl-9"
                            data-test="buscar-productos-input"
                        />
                    </div>

                    {filtrando && (
                        <Button variant="ghost" onClick={limpiar}>
                            <X />
                            Limpiar
                        </Button>
                    )}

                    <p className="ml-auto text-sm text-muted-foreground">
                        {filtrando
                            ? `${visibles.length} de ${productos.length} productos`
                            : `${productos.length} productos`}
                    </p>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-20 px-4">
                                    Foto
                                </TableHead>

                                <TableHead>
                                    {familias.length > 1 ? (
                                        <VehiculoFiltroColumna
                                            titulo="Producto"
                                            etiqueta="familia"
                                            opciones={familias}
                                            seleccion={filtros.familias}
                                            onChange={(seleccion) =>
                                                actualizar({
                                                    familias: seleccion,
                                                })
                                            }
                                        />
                                    ) : (
                                        'Producto'
                                    )}
                                </TableHead>

                                <TableHead>Precio</TableHead>

                                <TableHead>
                                    <VehiculoFiltroColumna
                                        titulo="Estado"
                                        opciones={estados}
                                        seleccion={filtros.estados}
                                        onChange={(seleccion) =>
                                            actualizar({ estados: seleccion })
                                        }
                                    />
                                </TableHead>

                                <TableHead className="w-12 px-4">
                                    <span className="sr-only">Acciones</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {visibles.length === 0 && (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell colSpan={5}>
                                        <Empty>
                                            <EmptyHeader>
                                                <EmptyMedia variant="icon">
                                                    {filtrando ? (
                                                        <SearchX />
                                                    ) : (
                                                        <Bike />
                                                    )}
                                                </EmptyMedia>

                                                <EmptyTitle>
                                                    {filtrando
                                                        ? 'Ningún producto coincide'
                                                        : 'Todavía no hay productos cargados'}
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
                                                                href={create({
                                                                    query: {
                                                                        seccion,
                                                                    },
                                                                })}
                                                            >
                                                                <Plus />
                                                                Nuevo producto
                                                            </Link>
                                                        </Button>
                                                    )
                                                )}
                                            </EmptyContent>
                                        </Empty>
                                    </TableCell>
                                </TableRow>
                            )}

                            {visibles.map((producto) => (
                                <TableRow key={producto.id}>
                                    <TableCell className="px-4">
                                        {producto.portada ? (
                                            <img
                                                src={producto.portada}
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
                                        {producto.nombre}
                                        <span className="block text-xs text-muted-foreground">
                                            {familiaLegible(producto.familia)}
                                            {producto.codigo &&
                                                ` · ${producto.codigo}`}{' '}
                                            · {producto.slug}
                                        </span>
                                    </TableCell>

                                    <TableCell>
                                        {producto.precio === null ? (
                                            <span className="text-muted-foreground">
                                                Consultar
                                            </span>
                                        ) : (
                                            fmtPrecio(
                                                producto.precio,
                                                producto.moneda,
                                            )
                                        )}
                                    </TableCell>

                                    <TableCell>
                                        <ProductoEstadoBadge
                                            estado={producto.estado}
                                        />
                                    </TableCell>

                                    <TableCell className="px-4 text-right">
                                        <ProductoRowActions
                                            producto={producto}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}
