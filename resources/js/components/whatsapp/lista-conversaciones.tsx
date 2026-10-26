import { Link, router } from '@inertiajs/react';
import { Bot, Check, MessagesSquare, Search, UserRound } from 'lucide-react';
import { useState } from 'react';
import ConversacionController from '@/actions/App/Http/Controllers/Panel/ConversacionController';
import { Badge } from '@/components/ui/badge';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import type { ConversacionDeLista } from '@/types';

export type FiltrosBandeja = { busqueda: string; filtro: string };

const hora = new Intl.DateTimeFormat('es-UY', { timeStyle: 'short' });
const dia = new Intl.DateTimeFormat('es-UY', { dateStyle: 'short' });

/** La hora si fue hoy; si no, la fecha. Como en WhatsApp. */
export function cuandoCorto(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const fecha = new Date(iso);

    return fecha.toDateString() === new Date().toDateString()
        ? hora.format(fecha)
        : dia.format(fecha);
}

/** Los filtros como query string, sin los vacíos. */
export function queryDeFiltros(
    filtros: FiltrosBandeja,
): Record<string, string> {
    const query: Record<string, string> = {};

    if (filtros.busqueda !== '') {
        query.busqueda = filtros.busqueda;
    }

    if (filtros.filtro !== '') {
        query.filtro = filtros.filtro;
    }

    return query;
}

type Props = {
    conversaciones: ConversacionDeLista[];
    /** Las que esperan a una persona: el contador de «Atención humana». */
    atencionHumana: number;
    filtros: FiltrosBandeja;
    abiertaId: number | null;
    onFiltrar: (cambio: Partial<FiltrosBandeja>) => void;
};

/**
 * La columna de la izquierda: las conversaciones, la más reciente arriba.
 * «Todas» es lo que atiende la IA; lo derivado va a «Atención humana».
 */
export function ListaConversaciones({
    conversaciones,
    atencionHumana,
    filtros,
    abiertaId,
    onFiltrar,
}: Props) {
    const [busqueda, setBusqueda] = useState(filtros.busqueda);

    return (
        /* `min-w-0` en toda la cadena: sin eso un item de grid no se achica por
           debajo de su contenido y una vista previa larga empuja la columna
           por encima del hilo en vez de recortarse. */
        <div className="flex min-h-0 min-w-0 flex-col border-r border-sidebar-border/70">
            <div className="grid gap-3 border-b border-sidebar-border/70 p-3">
                <form
                    className="relative"
                    onSubmit={(evento) => {
                        evento.preventDefault();
                        onFiltrar({ busqueda: busqueda.trim() });
                    }}
                >
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        value={busqueda}
                        onChange={(evento) => {
                            setBusqueda(evento.target.value);

                            if (evento.target.value === '') {
                                onFiltrar({ busqueda: '' });
                            }
                        }}
                        placeholder="Nombre o número"
                        aria-label="Buscar conversaciones"
                        className="pl-9"
                    />
                </form>

                <ToggleGroup
                    type="single"
                    variant="outline"
                    size="sm"
                    value={filtros.filtro === '' ? 'todas' : filtros.filtro}
                    onValueChange={(valor) =>
                        onFiltrar({
                            filtro: valor === 'todas' || !valor ? '' : valor,
                        })
                    }
                    aria-label="Filtrar conversaciones"
                    className="w-full"
                >
                    <ToggleGroupItem value="todas" className="flex-1">
                        Todas
                    </ToggleGroupItem>
                    <ToggleGroupItem value="sin_leer" className="flex-1">
                        Sin leer
                    </ToggleGroupItem>
                    <ToggleGroupItem
                        value="humano"
                        className="flex-1 gap-1.5"
                        data-test="filtro-atencion-humana"
                    >
                        Atención humana
                        {atencionHumana > 0 && (
                            <Badge
                                variant="destructive"
                                className="h-5 min-w-5 justify-center px-1.5 tabular-nums"
                            >
                                {atencionHumana}
                            </Badge>
                        )}
                    </ToggleGroupItem>
                    <ToggleGroupItem value="archivadas" className="flex-1">
                        Archivo
                    </ToggleGroupItem>
                </ToggleGroup>
            </div>

            <div className="min-h-0 flex-1 overflow-x-hidden overflow-y-auto">
                {conversaciones.length === 0 ? (
                    <Empty className="h-full">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <MessagesSquare />
                            </EmptyMedia>
                            <EmptyTitle>Sin conversaciones</EmptyTitle>
                            <EmptyDescription>
                                {filtros.busqueda !== '' ||
                                filtros.filtro !== ''
                                    ? 'Ninguna coincide con el filtro.'
                                    : 'Aparecen solas cuando alguien le escribe al WhatsApp del local.'}
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <ul>
                        {conversaciones.map((conversacion) => (
                            <li key={conversacion.id}>
                                <Link
                                    href={ConversacionController.show(
                                        conversacion.id,
                                        { query: queryDeFiltros(filtros) },
                                    )}
                                    preserveScroll
                                    preserveState
                                    className={cn(
                                        'flex min-w-0 gap-3 border-b border-sidebar-border/40 px-3 py-3 transition-colors hover:bg-muted/60',
                                        abiertaId === conversacion.id &&
                                            'bg-muted',
                                    )}
                                    data-test={`conversacion-${conversacion.id}`}
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-baseline justify-between gap-2">
                                            <span
                                                className={cn(
                                                    'truncate text-sm',
                                                    conversacion.noLeidos > 0
                                                        ? 'font-semibold'
                                                        : 'font-medium',
                                                )}
                                            >
                                                {conversacion.nombre}
                                            </span>
                                            <span className="shrink-0 text-xs text-muted-foreground">
                                                {cuandoCorto(
                                                    conversacion.ultimoMensajeAt,
                                                )}
                                            </span>
                                        </div>

                                        <div className="mt-1 flex items-center gap-2">
                                            <span className="flex min-w-0 flex-1 items-center gap-1 truncate text-xs text-muted-foreground">
                                                {conversacion.ultimoSaliente && (
                                                    <Check className="size-3 shrink-0" />
                                                )}
                                                <span className="truncate">
                                                    {conversacion.vistaPrevia ??
                                                        conversacion.telefono}
                                                </span>
                                            </span>

                                            {conversacion.atiendeLaIa ? (
                                                <Bot
                                                    className="size-3.5 shrink-0 text-muted-foreground"
                                                    aria-label="Atiende la IA"
                                                />
                                            ) : (
                                                <UserRound
                                                    className={cn(
                                                        'size-3.5 shrink-0',
                                                        conversacion.derivada
                                                            ? 'text-destructive'
                                                            : 'text-muted-foreground',
                                                    )}
                                                    aria-label="Atiende una persona"
                                                />
                                            )}

                                            {conversacion.noLeidos > 0 && (
                                                <Badge className="h-5 min-w-5 justify-center px-1.5 tabular-nums">
                                                    {conversacion.noLeidos}
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}

/** Navegar la bandeja con otros filtros, sin perder la conversación abierta. */
export function filtrarBandeja(url: string, filtros: FiltrosBandeja): void {
    router.get(url, queryDeFiltros(filtros), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
