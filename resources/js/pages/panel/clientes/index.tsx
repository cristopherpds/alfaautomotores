import { Head, Link, router } from '@inertiajs/react';
import { Contact, Download, Plus, Search, SearchX, X } from 'lucide-react';
import { useState } from 'react';
import ClienteController from '@/actions/App/Http/Controllers/Panel/ClienteController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Pagination,
    PaginationContent,
    PaginationItem,
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
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { create, exportar, index } from '@/routes/panel/clientes';
import type { ClienteDeLista, OpcionSelect, Paginado } from '@/types';

type Filtros = {
    busqueda: string;
    rubro: string;
    novedades: boolean;
};

type Props = {
    clientes: Paginado<ClienteDeLista>;
    filtros: Filtros;
    rubros: OpcionSelect[];
    puedeCrear: boolean;
    puedeExportar: boolean;
};

const fecha = new Intl.DateTimeFormat('es-UY', { dateStyle: 'short' });

/** Los filtros como query string, sin los vacíos: la URL queda limpia. */
function comoQuery(filtros: Filtros): Record<string, string> {
    const query: Record<string, string> = {};

    if (filtros.busqueda !== '') {
        query.busqueda = filtros.busqueda;
    }

    if (filtros.rubro !== '') {
        query.rubro = filtros.rubro;
    }

    if (filtros.novedades) {
        query.novedades = '1';
    }

    return query;
}

/**
 * Los clientes del taller y el lavadero. Filtros y paginación los resuelve el
 * servidor; el CSV baja exactamente lo que se está viendo.
 */
export default function ClientesIndex({
    clientes,
    filtros,
    rubros,
    puedeCrear,
    puedeExportar,
}: Props) {
    const [busqueda, setBusqueda] = useState(filtros.busqueda);

    const filtrar = (cambio: Partial<Filtros>) => {
        router.get(
            index.url({ query: comoQuery({ ...filtros, ...cambio }) }),
            undefined,
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const filtrando =
        filtros.busqueda !== '' || filtros.rubro !== '' || filtros.novedades;

    const irA = (url: string | null) => {
        if (url) {
            router.get(url, undefined, { preserveState: true });
        }
    };

    return (
        <>
            <Head title="Clientes" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Clientes"
                        description="Todos los que reservaron en el taller o el lavadero, más los que cargaste a mano"
                    />

                    <div className="flex flex-wrap gap-2">
                        {puedeExportar && (
                            <Button variant="outline" asChild>
                                {/* Descarga de archivo: un <a> común, no una
                                    visita de Inertia. */}
                                <a
                                    href={exportar.url({
                                        query: comoQuery(filtros),
                                    })}
                                    data-test="exportar-clientes-link"
                                >
                                    <Download />
                                    Exportar CSV
                                </a>
                            </Button>
                        )}
                        {puedeCrear && (
                            <Button asChild data-test="create-cliente-button">
                                <Link href={create()}>
                                    <Plus />
                                    Nuevo cliente
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-4">
                    <form
                        className="relative w-full sm:max-w-xs"
                        onSubmit={(evento) => {
                            evento.preventDefault();
                            filtrar({ busqueda: busqueda.trim() });
                        }}
                    >
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={busqueda}
                            onChange={(evento) => {
                                setBusqueda(evento.target.value);

                                // Borrar la búsqueda con la «x» del campo
                                // vuelve a la lista entera sin apretar Enter.
                                if (evento.target.value === '') {
                                    filtrar({ busqueda: '' });
                                }
                            }}
                            placeholder="Nombre, celular, email o matrícula"
                            aria-label="Buscar clientes"
                            className="pl-9"
                            data-test="buscar-clientes-input"
                        />
                    </form>

                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={filtros.rubro === '' ? 'todos' : filtros.rubro}
                        onValueChange={(valor) =>
                            filtrar({
                                rubro: valor === 'todos' || !valor ? '' : valor,
                            })
                        }
                        aria-label="Filtrar por rubro"
                    >
                        <ToggleGroupItem value="todos">Todos</ToggleGroupItem>
                        {rubros.map((rubro) => (
                            <ToggleGroupItem
                                key={rubro.value}
                                value={rubro.value}
                            >
                                {rubro.label}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="filtro-novedades"
                            checked={filtros.novedades}
                            onCheckedChange={(tildado) =>
                                filtrar({ novedades: tildado === true })
                            }
                        />
                        <Label htmlFor="filtro-novedades">
                            Sólo los que aceptan novedades
                        </Label>
                    </div>

                    {filtrando && (
                        <Button
                            variant="ghost"
                            onClick={() => {
                                setBusqueda('');
                                filtrar({
                                    busqueda: '',
                                    rubro: '',
                                    novedades: false,
                                });
                            }}
                        >
                            <X />
                            Limpiar
                        </Button>
                    )}

                    <p className="ml-auto text-sm text-muted-foreground">
                        {clientes.total}{' '}
                        {clientes.total === 1 ? 'cliente' : 'clientes'}
                    </p>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="px-4">Cliente</TableHead>
                                <TableHead>Contacto</TableHead>
                                <TableHead>Turnos</TableHead>
                                <TableHead>Último turno</TableHead>
                                <TableHead>
                                    <span className="sr-only">Novedades</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {clientes.data.length === 0 && (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell colSpan={5}>
                                        <Empty>
                                            <EmptyHeader>
                                                <EmptyMedia variant="icon">
                                                    {filtrando ? (
                                                        <SearchX />
                                                    ) : (
                                                        <Contact />
                                                    )}
                                                </EmptyMedia>
                                                <EmptyTitle>
                                                    {filtrando
                                                        ? 'Ningún cliente coincide'
                                                        : 'Todavía no hay clientes'}
                                                </EmptyTitle>
                                                <EmptyDescription>
                                                    {filtrando
                                                        ? 'Probá con otra búsqueda o sacá algún filtro.'
                                                        : 'Se cargan solos con cada reserva del taller o el lavadero.'}
                                                </EmptyDescription>
                                            </EmptyHeader>
                                        </Empty>
                                    </TableCell>
                                </TableRow>
                            )}

                            {clientes.data.map((cliente) => (
                                <TableRow key={cliente.id}>
                                    <TableCell className="px-4 font-medium">
                                        <Link
                                            href={ClienteController.show(
                                                cliente.id,
                                            )}
                                            className="underline-offset-4 hover:underline"
                                            data-test={`cliente-${cliente.id}-link`}
                                        >
                                            {cliente.nombre}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        {cliente.celular}
                                        {cliente.email && (
                                            <span className="block text-xs text-muted-foreground">
                                                {cliente.email}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {cliente.turnos_count}
                                    </TableCell>
                                    <TableCell>
                                        {cliente.ultimo ? (
                                            <>
                                                {fecha.format(
                                                    new Date(
                                                        cliente.ultimo.fecha,
                                                    ),
                                                )}
                                                <span className="block text-xs text-muted-foreground">
                                                    {cliente.ultimo.servicio} ·{' '}
                                                    {cliente.ultimo.rubroLabel}
                                                </span>
                                            </>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {cliente.acepta_novedades && (
                                            <Badge variant="secondary">
                                                Acepta novedades
                                            </Badge>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                {clientes.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-sm text-muted-foreground">
                            Mostrando {clientes.from}–{clientes.to} de{' '}
                            {clientes.total}
                        </p>
                        <Pagination className="mx-0 w-auto">
                            <PaginationContent>
                                <PaginationItem>
                                    <PaginationPrevious
                                        onClick={() =>
                                            irA(clientes.prev_page_url)
                                        }
                                        disabled={!clientes.prev_page_url}
                                    />
                                </PaginationItem>
                                <PaginationItem>
                                    <PaginationNext
                                        onClick={() =>
                                            irA(clientes.next_page_url)
                                        }
                                        disabled={!clientes.next_page_url}
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

ClientesIndex.layout = {
    breadcrumbs: [{ title: 'Clientes', href: index() }],
};
