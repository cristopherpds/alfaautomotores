import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CalendarCheck, Clock } from 'lucide-react';
import VehiculoController from '@/actions/App/Http/Controllers/Panel/VehiculoController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { ACCIONES_AUDITORIA, tipoLegible } from '@/lib/auditoria';
import { indiceDeSeccion, TITULO_SECCION } from '@/lib/productos-panel';
import { dashboard } from '@/routes';
import { index as auditoriaIndex } from '@/routes/panel/auditoria';
import { index as turnosIndex } from '@/routes/panel/turnos';
import { index as vehiculosIndex } from '@/routes/panel/vehiculos';
import type {
    DatosDelDashboard,
    ListaParaRevisar,
    SeccionProducto,
    TurnoDeHoy,
} from '@/types';

const fechaHora = new Intl.DateTimeFormat('es-UY', {
    dateStyle: 'short',
    timeStyle: 'short',
});

const diaCorto = new Intl.DateTimeFormat('es-UY', {
    weekday: 'short',
    day: 'numeric',
});

const relativo = new Intl.RelativeTimeFormat('es-UY', { numeric: 'auto' });

/** «hace 2 días», «hace 3 horas»: cuánto lleva esperando una reserva. */
function haceCuanto(iso: string): string {
    const minutos = Math.round((new Date(iso).getTime() - Date.now()) / 60000);

    if (Math.abs(minutos) < 60) {
        return relativo.format(minutos, 'minute');
    }

    const horas = Math.round(minutos / 60);

    return Math.abs(horas) < 24
        ? relativo.format(horas, 'hour')
        : relativo.format(Math.round(horas / 24), 'day');
}

/** Una fecha `YYYY-MM-DD` como día local, sin corrimiento por zona horaria. */
function diaLocal(fecha: string): Date {
    return new Date(`${fecha}T00:00`);
}

function hoyISO(): string {
    const hoy = new Date();
    const mes = String(hoy.getMonth() + 1).padStart(2, '0');
    const dia = String(hoy.getDate()).padStart(2, '0');

    return `${hoy.getFullYear()}-${mes}-${dia}`;
}

/** Un número con su etiqueta. Sin color: el número es el dato. */
function StatTile({
    label,
    valor,
    nota,
}: {
    label: string;
    valor: string | number;
    nota?: string;
}) {
    return (
        <Card className="gap-1 py-4">
            <CardContent className="grid gap-1 px-4">
                <p className="text-sm text-muted-foreground">{label}</p>
                <p className="text-3xl font-semibold">{valor}</p>
                {nota && (
                    <p className="text-xs text-muted-foreground">{nota}</p>
                )}
            </CardContent>
        </Card>
    );
}

function TurnosDeHoy({
    titulo,
    turnos,
}: {
    titulo: string;
    turnos: TurnoDeHoy[];
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{titulo}</CardTitle>
                <CardDescription>
                    {turnos.length === 0
                        ? 'Sin turnos para hoy.'
                        : `${turnos.length} ${turnos.length === 1 ? 'turno' : 'turnos'} hoy`}
                </CardDescription>
            </CardHeader>
            {turnos.length > 0 && (
                <CardContent>
                    <ul className="grid gap-2 text-sm">
                        {turnos.map((turno) => (
                            <li
                                key={turno.id}
                                className="flex items-center gap-3"
                            >
                                <span className="w-12 font-medium tabular-nums">
                                    {turno.hora}
                                </span>
                                <span className="min-w-0 flex-1 truncate">
                                    {turno.cliente}
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {turno.servicio}
                                    </span>
                                </span>
                                <Badge
                                    variant={
                                        turno.estado === 'pendiente'
                                            ? 'outline'
                                            : 'secondary'
                                    }
                                >
                                    {turno.estadoLabel}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                </CardContent>
            )}
        </Card>
    );
}

function ListaDeRevision({
    titulo,
    detalle,
    datos,
    unidad,
}: {
    titulo: string;
    detalle: string;
    datos: ListaParaRevisar;
    unidad: (dias: number) => string;
}) {
    return (
        <div className="grid gap-2">
            <div>
                <p className="text-sm font-medium">
                    {titulo}{' '}
                    <span className="text-muted-foreground">
                        ({datos.total})
                    </span>
                </p>
                <p className="text-xs text-muted-foreground">{detalle}</p>
            </div>

            {datos.total === 0 ? (
                <p className="text-sm text-muted-foreground">Nada por ahora.</p>
            ) : (
                <ul className="grid gap-1 text-sm">
                    {datos.lista.map((vehiculo) => (
                        <li
                            key={vehiculo.id}
                            className="flex justify-between gap-2"
                        >
                            <Link
                                href={VehiculoController.edit(vehiculo.id)}
                                className="truncate underline-offset-4 hover:underline"
                            >
                                {vehiculo.titulo}
                            </Link>
                            <span className="shrink-0 text-muted-foreground">
                                {unidad(vehiculo.dias)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/**
 * Turnos por día de la semana, taller y lavadero sumados: una sola serie, en
 * el color primario. El detalle por rubro va en el tooltip y la cifra en el
 * extremo de cada columna.
 */
function SemanaEnColumnas({
    dias,
}: {
    dias: DatosDelDashboard['semana']['dias'];
}) {
    const totales = dias.map((dia) => dia.taller + dia.lavadero);
    const maximo = Math.max(1, ...totales);
    const ALTO = 96;

    return (
        <div className="grid grid-cols-7 gap-1 border-b">
            {dias.map((dia, indice) => {
                const total = totales[indice];

                return (
                    <Tooltip key={dia.fecha}>
                        <TooltipTrigger asChild>
                            <div
                                className="flex flex-col items-center justify-end gap-1 pt-2"
                                style={{ height: ALTO + 40 }}
                                tabIndex={0}
                                aria-label={`${diaCorto.format(diaLocal(dia.fecha))}: ${total} turnos`}
                            >
                                <span className="text-xs font-medium">
                                    {total}
                                </span>
                                <span
                                    className="w-6 rounded-t-[4px] bg-primary"
                                    style={{
                                        height: (total / maximo) * ALTO,
                                    }}
                                />
                            </div>
                        </TooltipTrigger>
                        <TooltipContent>
                            Taller {dia.taller} · Lavadero {dia.lavadero}
                        </TooltipContent>
                    </Tooltip>
                );
            })}
            {dias.map((dia) => (
                <span
                    key={`etiqueta-${dia.fecha}`}
                    className="pb-1 text-center text-xs text-muted-foreground capitalize"
                >
                    {diaCorto.format(diaLocal(dia.fecha))}
                </span>
            ))}
        </div>
    );
}

function SeccionDelCatalogo({
    seccion,
    datos,
}: {
    seccion: SeccionProducto;
    datos: DatosDelDashboard['catalogo'][SeccionProducto];
}) {
    return (
        <div className="grid gap-2">
            <Link
                href={indiceDeSeccion(seccion)}
                className="text-sm font-medium underline-offset-4 hover:underline"
            >
                {TITULO_SECCION[seccion]}
            </Link>
            <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                <dt className="text-muted-foreground">Publicados</dt>
                <dd className="text-right font-medium">{datos.publicados}</dd>
                <dt className="text-muted-foreground">Sin stock</dt>
                <dd className="text-right font-medium">{datos.sinStock}</dd>
                <dt className="text-muted-foreground">Por encargue</dt>
                <dd className="text-right font-medium">{datos.porEncargue}</dd>
                <dt className="text-muted-foreground">Sin fotos</dt>
                <dd className="text-right font-medium">{datos.sinFotos}</dd>
            </dl>
        </div>
    );
}

/**
 * La primera pantalla del panel: qué hay que hacer hoy, arriba; cómo está el
 * stock, abajo. Todo lo calcula `DashboardController`.
 */
export default function Dashboard({
    hoy,
    pendientes,
    stock,
    revisar,
    catalogo,
    semana,
    actividad,
}: DatosDelDashboard) {
    const turnosDeHoy = turnosIndex({
        query: { desde: hoyISO(), hasta: hoyISO() },
    });
    const totalOrigen = semana.origen.web + semana.origen.panel;

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-8 p-4">
                {/* 1. Para hoy */}
                <section className="grid gap-4">
                    <div className="flex items-center justify-between gap-2">
                        <h2 className="text-lg font-semibold">Para hoy</h2>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={turnosDeHoy}>
                                Ver la agenda
                                <ArrowRight />
                            </Link>
                        </Button>
                    </div>

                    <div className="grid items-start gap-4 lg:grid-cols-3">
                        <TurnosDeHoy titulo="Taller" turnos={hoy.taller} />
                        <TurnosDeHoy titulo="Lavadero" turnos={hoy.lavadero} />

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Clock className="size-4" />
                                    Pendientes de confirmar
                                </CardTitle>
                                <CardDescription>
                                    Reservas de la web que todavía no se
                                    confirmaron por WhatsApp.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                <p className="text-4xl font-semibold">
                                    {pendientes.total}
                                </p>
                                {pendientes.lista.length > 0 ? (
                                    <ul className="grid gap-2 text-sm">
                                        {pendientes.lista.map((turno) => (
                                            <li key={turno.id}>
                                                <span className="font-medium">
                                                    {turno.cliente}
                                                </span>{' '}
                                                · {turno.servicio}
                                                <span className="block text-xs text-muted-foreground">
                                                    {turno.rubroLabel} ·{' '}
                                                    {fechaHora.format(
                                                        new Date(turno.cuando),
                                                    )}
                                                    {turno.recibido &&
                                                        ` · reservó ${haceCuanto(turno.recibido)}`}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <CalendarCheck className="size-4" />
                                        Todo confirmado.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </section>

                {/* 2. Stock de autos */}
                <section className="grid gap-4">
                    <div className="flex items-center justify-between gap-2">
                        <h2 className="text-lg font-semibold">
                            Stock de autos
                        </h2>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={vehiculosIndex()}>
                                Ver el stock
                                <ArrowRight />
                            </Link>
                        </Button>
                    </div>

                    <div className="grid grid-cols-2 gap-4 md:grid-cols-5">
                        <StatTile label="Publicados" valor={stock.publicados} />
                        <StatTile label="Reservados" valor={stock.reservados} />
                        <StatTile
                            label="Vendidos este mes"
                            valor={stock.vendidosMes}
                        />
                        <StatTile label="Borradores" valor={stock.borradores} />
                        <StatTile
                            label="Destacados en la portada"
                            valor={`${stock.destacados} de ${stock.maxDestacados}`}
                        />
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Para revisar</CardTitle>
                            <CardDescription>
                                Fichas que piden una mano, de la más vieja a la
                                más nueva.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-6 md:grid-cols-3">
                            <ListaDeRevision
                                titulo="Sin fotos"
                                detalle="Salen en el sitio con «Foto pendiente»."
                                datos={revisar.sinFotos}
                                unidad={(dias) => `${dias} d`}
                            />
                            <ListaDeRevision
                                titulo="Borradores olvidados"
                                detalle="Cargados hace más de 7 días y sin publicar."
                                datos={revisar.borradoresViejos}
                                unidad={(dias) => `${dias} d`}
                            />
                            <ListaDeRevision
                                titulo="Publicados que no rotan"
                                detalle="Más de 60 días publicados: ¿revisar el precio?"
                                datos={revisar.estancados}
                                unidad={(dias) => `${dias} d`}
                            />
                        </CardContent>
                    </Card>
                </section>

                {/* 3 y 4. Catálogo y semana */}
                <section className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Movilidad y bicicletas</CardTitle>
                            <CardDescription>
                                Cómo está el catálogo de cada sección.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-6 sm:grid-cols-2">
                            <SeccionDelCatalogo
                                seccion="movilidad"
                                datos={catalogo.movilidad}
                            />
                            <SeccionDelCatalogo
                                seccion="bicicletas"
                                datos={catalogo.bicicletas}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Turnos de los próximos 7 días</CardTitle>
                            <CardDescription>
                                Taller y lavadero juntos; pasá el mouse por un
                                día para ver el detalle.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <SemanaEnColumnas dias={semana.dias} />
                            <div className="flex flex-wrap gap-x-6 gap-y-1 text-sm text-muted-foreground">
                                <span>
                                    Cancelados esta semana:{' '}
                                    <span className="font-medium text-foreground">
                                        {semana.cancelados}
                                    </span>
                                </span>
                                <span>
                                    Últimos 30 días:{' '}
                                    <span className="font-medium text-foreground">
                                        {semana.origen.web}
                                    </span>{' '}
                                    por la web ·{' '}
                                    <span className="font-medium text-foreground">
                                        {semana.origen.panel}
                                    </span>{' '}
                                    desde el panel
                                    {totalOrigen > 0 &&
                                        ` (${Math.round((semana.origen.web / totalOrigen) * 100)}% web)`}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </section>

                {/* 5. Actividad reciente, sólo admins */}
                {actividad && (
                    <section className="grid gap-4">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="text-lg font-semibold">
                                Actividad reciente
                            </h2>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={auditoriaIndex()}>
                                    Ver la auditoría
                                    <ArrowRight />
                                </Link>
                            </Button>
                        </div>

                        <Card className="py-2">
                            <CardContent className="px-4">
                                {actividad.length === 0 ? (
                                    <p className="py-4 text-sm text-muted-foreground">
                                        Todavía no hay movimientos.
                                    </p>
                                ) : (
                                    <ul className="divide-y text-sm">
                                        {actividad.map((entrada) => {
                                            const accion =
                                                ACCIONES_AUDITORIA[
                                                    entrada.accion
                                                ];

                                            return (
                                                <li
                                                    key={entrada.id}
                                                    className="flex flex-wrap items-center gap-x-3 gap-y-1 py-2"
                                                >
                                                    <span className="w-28 text-muted-foreground">
                                                        {fechaHora.format(
                                                            new Date(
                                                                entrada.fecha,
                                                            ),
                                                        )}
                                                    </span>
                                                    <span className="font-medium">
                                                        {entrada.usuario}
                                                    </span>
                                                    <Badge
                                                        variant={accion.variant}
                                                    >
                                                        {accion.label}
                                                    </Badge>
                                                    <span className="min-w-0 flex-1 truncate">
                                                        {entrada.etiqueta}{' '}
                                                        <span className="text-muted-foreground">
                                                            ·{' '}
                                                            {tipoLegible(
                                                                entrada.tipo,
                                                            )}
                                                        </span>
                                                    </span>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </section>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
