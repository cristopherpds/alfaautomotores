import { Head, Link, router } from '@inertiajs/react';
import { HandCoins, SearchX } from 'lucide-react';
import CotizacionController from '@/actions/App/Http/Controllers/Panel/CotizacionController';
import CotizacionEstadoBadge from '@/components/cotizacion-estado-badge';
import Heading from '@/components/heading';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
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
import { index } from '@/routes/panel/cotizaciones';
import type { CotizacionDeLista, OpcionSelect, Paginado } from '@/types';

type Props = {
    cotizaciones: Paginado<CotizacionDeLista>;
    filtros: { estado: string };
    estados: OpcionSelect[];
};

const fecha = new Intl.DateTimeFormat('es-UY', {
    dateStyle: 'short',
    timeStyle: 'short',
});
const numero = new Intl.NumberFormat('es-UY');

/**
 * Las solicitudes de «Cotizá tu auto», de la más nueva a la más vieja.
 */
export default function CotizacionesIndex({
    cotizaciones,
    filtros,
    estados,
}: Props) {
    const filtrando = filtros.estado !== '';

    const filtrar = (estado: string) => {
        router.get(
            index.url({ query: estado === '' ? {} : { estado } }),
            undefined,
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const irA = (url: string | null) => {
        if (url) {
            router.get(url, undefined, { preserveState: true });
        }
    };

    return (
        <>
            <Head title="Cotizaciones" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Cotizaciones"
                    description="Los usados que ofrecen desde «Cotizá tu auto» en el sitio"
                />

                <div className="flex flex-wrap items-center gap-4">
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={filtrando ? filtros.estado : 'todas'}
                        onValueChange={(valor) =>
                            filtrar(valor === 'todas' || !valor ? '' : valor)
                        }
                        aria-label="Filtrar por estado"
                    >
                        <ToggleGroupItem value="todas">Todas</ToggleGroupItem>
                        {estados.map((estado) => (
                            <ToggleGroupItem
                                key={estado.value}
                                value={estado.value}
                            >
                                {estado.label}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>

                    <p className="ml-auto text-sm text-muted-foreground">
                        {cotizaciones.total}{' '}
                        {cotizaciones.total === 1
                            ? 'cotización'
                            : 'cotizaciones'}
                    </p>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="px-4">Vehículo</TableHead>
                                <TableHead>Kilómetros</TableHead>
                                <TableHead>Contacto</TableHead>
                                <TableHead>Fotos</TableHead>
                                <TableHead>Recibida</TableHead>
                                <TableHead>Estado</TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {cotizaciones.data.length === 0 && (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell colSpan={6}>
                                        <Empty>
                                            <EmptyHeader>
                                                <EmptyMedia variant="icon">
                                                    {filtrando ? (
                                                        <SearchX />
                                                    ) : (
                                                        <HandCoins />
                                                    )}
                                                </EmptyMedia>
                                                <EmptyTitle>
                                                    {filtrando
                                                        ? 'Ninguna cotización en ese estado'
                                                        : 'Todavía no llegaron cotizaciones'}
                                                </EmptyTitle>
                                                <EmptyDescription>
                                                    {filtrando
                                                        ? 'Probá con otro estado.'
                                                        : 'Aparecen acá cuando alguien completa «Cotizá tu auto» en el sitio.'}
                                                </EmptyDescription>
                                            </EmptyHeader>
                                        </Empty>
                                    </TableCell>
                                </TableRow>
                            )}

                            {cotizaciones.data.map((cotizacion) => (
                                <TableRow key={cotizacion.id}>
                                    <TableCell className="px-4 font-medium">
                                        <Link
                                            href={CotizacionController.show(
                                                cotizacion.id,
                                            )}
                                            className="underline-offset-4 hover:underline"
                                            data-test={`cotizacion-${cotizacion.id}-link`}
                                        >
                                            {cotizacion.vehiculo}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {numero.format(cotizacion.kilometros)}{' '}
                                        km
                                    </TableCell>
                                    <TableCell>
                                        {cotizacion.nombre}
                                        <span className="block text-xs text-muted-foreground">
                                            {cotizacion.celular}
                                        </span>
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {cotizacion.fotos}
                                    </TableCell>
                                    <TableCell>
                                        {cotizacion.creada &&
                                            fecha.format(
                                                new Date(cotizacion.creada),
                                            )}
                                    </TableCell>
                                    <TableCell>
                                        <CotizacionEstadoBadge
                                            estado={cotizacion.estado}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                {cotizaciones.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-sm text-muted-foreground">
                            Mostrando {cotizaciones.from}–{cotizaciones.to} de{' '}
                            {cotizaciones.total}
                        </p>
                        <Pagination className="mx-0 w-auto">
                            <PaginationContent>
                                <PaginationItem>
                                    <PaginationPrevious
                                        onClick={() =>
                                            irA(cotizaciones.prev_page_url)
                                        }
                                        disabled={!cotizaciones.prev_page_url}
                                    />
                                </PaginationItem>
                                <PaginationItem>
                                    <PaginationNext
                                        onClick={() =>
                                            irA(cotizaciones.next_page_url)
                                        }
                                        disabled={!cotizaciones.next_page_url}
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

CotizacionesIndex.layout = {
    breadcrumbs: [{ title: 'Cotizaciones', href: index() }],
};
