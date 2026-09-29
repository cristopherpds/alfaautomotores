import { Head, router } from '@inertiajs/react';
import { ChevronDown, ChevronRight, History, X } from 'lucide-react';
import { Fragment, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ACCIONES_AUDITORIA, tipoLegible } from '@/lib/auditoria';
import { index } from '@/routes/panel/auditoria';
import type { EntradaAuditoria, OpcionSelect, Paginado } from '@/types';

type Filtros = {
    usuario: string;
    tipo: string;
    accion: string;
    desde: string;
    hasta: string;
};

type Props = {
    entradas: Paginado<EntradaAuditoria>;
    filtros: Filtros;
    usuarios: string[];
    tipos: string[];
    acciones: OpcionSelect[];
};

/** El valor de los selects que significa «sin filtrar»: Radix no admite ''. */
const TODOS = 'todos';

const fechaHora = new Intl.DateTimeFormat('es-UY', {
    dateStyle: 'short',
    timeStyle: 'short',
});

/** Un valor guardado, en texto: lo que no hay se muestra como raya. */
function valorLegible(valor: unknown): string {
    if (valor === null || valor === undefined || valor === '') {
        return '—';
    }

    if (typeof valor === 'boolean') {
        return valor ? 'Sí' : 'No';
    }

    if (Array.isArray(valor)) {
        return valor
            .map((item) =>
                Array.isArray(item) ? item.join(': ') : String(item),
            )
            .join(' · ');
    }

    if (typeof valor === 'object') {
        return JSON.stringify(valor);
    }

    return String(valor);
}

function FiltroSelect({
    id,
    label,
    valor,
    opciones,
    onChange,
}: {
    id: string;
    label: string;
    valor: string;
    opciones: OpcionSelect[];
    onChange: (valor: string) => void;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            <Select
                value={valor === '' ? TODOS : valor}
                onValueChange={(nuevo) =>
                    onChange(nuevo === TODOS ? '' : nuevo)
                }
            >
                <SelectTrigger id={id} className="w-48">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectItem value={TODOS}>Todos</SelectItem>
                        {opciones.map((opcion) => (
                            <SelectItem key={opcion.value} value={opcion.value}>
                                {opcion.label}
                            </SelectItem>
                        ))}
                    </SelectGroup>
                </SelectContent>
            </Select>
        </div>
    );
}

function DetalleDeCambios({ entrada }: { entrada: EntradaAuditoria }) {
    const campos = Object.entries(entrada.cambios);

    if (campos.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Sin detalle de campos.
            </p>
        );
    }

    return (
        <dl className="grid gap-1 text-sm sm:grid-cols-[minmax(8rem,auto)_1fr]">
            {campos.map(([campo, [antes, despues]]) => (
                <Fragment key={campo}>
                    <dt className="font-medium">{campo.replace(/_/g, ' ')}</dt>
                    <dd className="text-muted-foreground">
                        {entrada.accion === 'creado' && valorLegible(despues)}
                        {entrada.accion === 'eliminado' && valorLegible(antes)}
                        {entrada.accion === 'modificado' && (
                            <>
                                <span className="line-through">
                                    {valorLegible(antes)}
                                </span>{' '}
                                →{' '}
                                <span className="text-foreground">
                                    {valorLegible(despues)}
                                </span>
                            </>
                        )}
                    </dd>
                </Fragment>
            ))}
        </dl>
    );
}

/**
 * Quién creó, modificó o eliminó qué. Los filtros van en la URL y los
 * resuelve el servidor, igual que la paginación: la tabla crece sin tope.
 */
export default function AuditoriaIndex({
    entradas,
    filtros,
    usuarios,
    tipos,
    acciones,
}: Props) {
    const [abiertas, setAbiertas] = useState<number[]>([]);

    const filtrar = (cambio: Partial<Filtros>) => {
        const query = Object.fromEntries(
            Object.entries({ ...filtros, ...cambio }).filter(
                ([, valor]) => valor !== '',
            ),
        );

        router.get(index.url({ query }), undefined, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const filtrando = Object.values(filtros).some((valor) => valor !== '');

    const alternar = (id: number) =>
        setAbiertas((previas) =>
            previas.includes(id)
                ? previas.filter((otra) => otra !== id)
                : [...previas, id],
        );

    const irA = (url: string | null) => {
        if (url) {
            router.get(url, undefined, { preserveState: true });
        }
    };

    return (
        <>
            <Head title="Auditoría" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Auditoría"
                    description="Quién creó, modificó o eliminó cada cosa del panel"
                />

                <div className="flex flex-wrap items-end gap-4">
                    <FiltroSelect
                        id="filtro-usuario"
                        label="Usuario"
                        valor={filtros.usuario}
                        opciones={usuarios.map((usuario) => ({
                            value: usuario,
                            label: usuario,
                        }))}
                        onChange={(usuario) => filtrar({ usuario })}
                    />

                    <FiltroSelect
                        id="filtro-tipo"
                        label="Qué"
                        valor={filtros.tipo}
                        opciones={tipos.map((tipo) => ({
                            value: tipo,
                            label: tipoLegible(tipo),
                        }))}
                        onChange={(tipo) => filtrar({ tipo })}
                    />

                    <FiltroSelect
                        id="filtro-accion"
                        label="Acción"
                        valor={filtros.accion}
                        opciones={acciones}
                        onChange={(accion) => filtrar({ accion })}
                    />

                    <div className="grid gap-1.5">
                        <Label htmlFor="filtro-desde">Desde</Label>
                        <Input
                            id="filtro-desde"
                            type="date"
                            value={filtros.desde}
                            onChange={(evento) =>
                                filtrar({ desde: evento.target.value })
                            }
                            className="w-40"
                        />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="filtro-hasta">Hasta</Label>
                        <Input
                            id="filtro-hasta"
                            type="date"
                            value={filtros.hasta}
                            onChange={(evento) =>
                                filtrar({ hasta: evento.target.value })
                            }
                            className="w-40"
                        />
                    </div>

                    {filtrando && (
                        <Button
                            variant="ghost"
                            onClick={() =>
                                filtrar({
                                    usuario: '',
                                    tipo: '',
                                    accion: '',
                                    desde: '',
                                    hasta: '',
                                })
                            }
                        >
                            <X />
                            Limpiar
                        </Button>
                    )}

                    <p className="ml-auto text-sm text-muted-foreground">
                        {entradas.total} entradas
                    </p>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-10 px-4">
                                    <span className="sr-only">Detalle</span>
                                </TableHead>
                                <TableHead>Fecha</TableHead>
                                <TableHead>Usuario</TableHead>
                                <TableHead>Acción</TableHead>
                                <TableHead>Qué</TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {entradas.data.length === 0 && (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell colSpan={5}>
                                        <Empty>
                                            <EmptyHeader>
                                                <EmptyMedia variant="icon">
                                                    <History />
                                                </EmptyMedia>
                                                <EmptyTitle>
                                                    {filtrando
                                                        ? 'Nada coincide con los filtros'
                                                        : 'Todavía no hay movimientos'}
                                                </EmptyTitle>
                                                <EmptyDescription>
                                                    {filtrando
                                                        ? 'Probá con otro usuario, tipo o rango de fechas.'
                                                        : 'Cada alta, cambio o baja del panel va a aparecer acá.'}
                                                </EmptyDescription>
                                            </EmptyHeader>
                                        </Empty>
                                    </TableCell>
                                </TableRow>
                            )}

                            {entradas.data.map((entrada) => {
                                const abierta = abiertas.includes(entrada.id);
                                const accion =
                                    ACCIONES_AUDITORIA[entrada.accion];

                                return (
                                    <Fragment key={entrada.id}>
                                        <TableRow>
                                            <TableCell className="px-4">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        alternar(entrada.id)
                                                    }
                                                    aria-expanded={abierta}
                                                    aria-label={`${abierta ? 'Ocultar' : 'Ver'} el detalle`}
                                                >
                                                    {abierta ? (
                                                        <ChevronDown />
                                                    ) : (
                                                        <ChevronRight />
                                                    )}
                                                </Button>
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap text-muted-foreground">
                                                {fechaHora.format(
                                                    new Date(entrada.fecha),
                                                )}
                                            </TableCell>
                                            <TableCell className="font-medium">
                                                {entrada.usuario}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={accion.variant}>
                                                    {accion.label}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {entrada.etiqueta}
                                                <span className="block text-xs text-muted-foreground">
                                                    {tipoLegible(entrada.tipo)}
                                                    {entrada.ip &&
                                                        ` · IP ${entrada.ip}`}
                                                </span>
                                            </TableCell>
                                        </TableRow>

                                        {abierta && (
                                            <TableRow className="hover:bg-transparent">
                                                <TableCell />
                                                <TableCell colSpan={4}>
                                                    <DetalleDeCambios
                                                        entrada={entrada}
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </Fragment>
                                );
                            })}
                        </TableBody>
                    </Table>
                </div>

                {entradas.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-sm text-muted-foreground">
                            Mostrando {entradas.from}–{entradas.to} de{' '}
                            {entradas.total} · página {entradas.current_page} de{' '}
                            {entradas.last_page}
                        </p>

                        <Pagination className="mx-0 w-auto">
                            <PaginationContent>
                                <PaginationItem>
                                    <PaginationPrevious
                                        onClick={() =>
                                            irA(entradas.prev_page_url)
                                        }
                                        disabled={!entradas.prev_page_url}
                                    />
                                </PaginationItem>
                                <PaginationItem>
                                    <PaginationNext
                                        onClick={() =>
                                            irA(entradas.next_page_url)
                                        }
                                        disabled={!entradas.next_page_url}
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

AuditoriaIndex.layout = {
    breadcrumbs: [{ title: 'Auditoría', href: index() }],
};
