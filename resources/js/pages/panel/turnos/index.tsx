import type { EventClickArg, EventInput } from '@fullcalendar/core';
import esLocale from '@fullcalendar/core/locales/es';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import FullCalendar from '@fullcalendar/react';
import timeGridPlugin from '@fullcalendar/timegrid';
import { Form, Head, router } from '@inertiajs/react';
import { CalendarPlus } from 'lucide-react';
import { useRef, useState } from 'react';
import TurnoController from '@/actions/App/Http/Controllers/Panel/TurnoController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { index } from '@/routes/panel/turnos';
import type { OpcionSelect } from '@/types';

/** El turno tal como lo pinta el calendario. */
type EventoTurno = EventInput & {
    id: string;
    title: string;
    extendedProps: {
        estado: string;
        estadoLabel: string;
        origen: string;
        rubro: string;
        rubroLabel: string;
        servicio: string;
        duracionLegible: string;
        puesto: string;
        cliente: string;
        email: string;
        celular: string;
        vehiculo: string | null;
        matricula: string | null;
        comentario: string | null;
    };
};

type ServicioAgendable = {
    slug: string;
    nombre: string;
    rubro: string;
    rubroLabel: string;
    duracionLegible: string;
};

type Props = {
    turnos: EventoTurno[];
    servicios: ServicioAgendable[];
    estados: OpcionSelect[];
    rubros: OpcionSelect[];
    /** El filtro activo; `null` es «todos». */
    rubro: string | null;
    puedeGestionar: boolean;
    huecos: string[];
};

/** El valor del filtro que no filtra nada. */
const TODOS = 'todos';

/** El color de cada estado sale de estas clases; el CSS vive en `app.css`. */
const TONO: Record<
    string,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    pendiente: 'secondary',
    confirmado: 'default',
    cancelado: 'destructive',
    completado: 'outline',
};

/** Hoy en `aaaa-mm-dd`, sin el corrimiento de UTC que trae `toISOString()`. */
function hoy(): string {
    return new Date().toLocaleDateString('en-CA');
}

/**
 * La agenda del taller.
 *
 * Los turnos llegan ya con forma de evento de FullCalendar. Al cambiar de mes
 * o de vista se pide una recarga parcial con el rango nuevo, así el calendario
 * nunca trae más de lo que se está mirando.
 */
export default function TurnosIndex({
    turnos,
    servicios,
    estados,
    rubros,
    rubro,
    puedeGestionar,
    huecos,
}: Props) {
    const [filtro, setFiltro] = useState(rubro ?? TODOS);
    const [abierto, setAbierto] = useState<EventoTurno | null>(null);
    const [creando, setCreando] = useState(false);

    /* Del alta manual: el servidor calcula los horarios libres a partir de
       estos dos, igual que en el sitio público. */
    const [servicio, setServicio] = useState('');
    const [fecha, setFecha] = useState(hoy);
    const [hora, setHora] = useState('');

    const pedido = useRef<string>('');

    /* El rango que el calendario está mostrando. Lo guarda `pedirRango` para
       que el filtro de rubro pueda volver a pedir el mismo tramo sin esperar a
       que el usuario navegue. */
    const visible = useRef<{ desde: Date; hasta: Date } | null>(null);

    const pedirRango = (desde: Date, hasta: Date, deRubro: string) => {
        visible.current = { desde, hasta };

        /* El rubro va en la clave además del rango: si no, cambiar de filtro
           sobre el mismo mes no pediría nada. */
        const clave = `${desde.toISOString()}|${hasta.toISOString()}|${deRubro}`;

        /* `datesSet` se dispara también al montar y al abrir un diálogo: sin
           esta guarda el calendario pediría lo mismo una y otra vez. */
        if (pedido.current === clave) {
            return;
        }

        pedido.current = clave;

        router.reload({
            only: ['turnos'],
            data: {
                desde: desde.toLocaleDateString('en-CA'),
                hasta: hasta.toLocaleDateString('en-CA'),
                rubro: deRubro === TODOS ? '' : deRubro,
            },
        });
    };

    const filtrar = (valor: string) => {
        /* El ToggleGroup devuelve cadena vacía al desmarcar el activo: eso vale
           como «todos» en vez de dejar el grupo sin ninguno apretado. */
        const elegido = valor === '' ? TODOS : valor;

        setFiltro(elegido);

        if (visible.current) {
            pedirRango(visible.current.desde, visible.current.hasta, elegido);
        }
    };

    const pedirHuecos = (nuevoServicio: string, nuevaFecha: string) => {
        setHora('');

        if (!nuevoServicio || !nuevaFecha) {
            return;
        }

        router.reload({
            only: ['huecos'],
            data: { servicio: nuevoServicio, fecha: nuevaFecha },
        });
    };

    const cambiarEstado = (turno: EventoTurno, estado: string) => {
        router.patch(
            TurnoController.estado.url(Number(turno.id)),
            { estado },
            { preserveScroll: true, onSuccess: () => setAbierto(null) },
        );
    };

    const eliminar = (turno: EventoTurno) => {
        router.delete(TurnoController.destroy.url(Number(turno.id)), {
            preserveScroll: true,
            onSuccess: () => setAbierto(null),
        });
    };

    return (
        <>
            <Head title="Turnos" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Turnos"
                        description="La agenda del taller y del lavadero. Los que entran por la web llegan pendientes."
                    />

                    <div className="flex flex-wrap items-center gap-3">
                        {/* El color del evento ya dice el estado, así que el
                            rubro se filtra en vez de pintarse: en la vista de
                            todos lo marca la barra de la izquierda. */}
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={filtro}
                            onValueChange={filtrar}
                            aria-label="Filtrar por rubro"
                        >
                            <ToggleGroupItem
                                value={TODOS}
                                data-test="filtro-todos"
                            >
                                Todos
                            </ToggleGroupItem>

                            {rubros.map((uno) => (
                                <ToggleGroupItem
                                    key={uno.value}
                                    value={uno.value}
                                    data-test={`filtro-${uno.value}`}
                                >
                                    {uno.label}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>

                        {puedeGestionar && (
                            <Button
                                onClick={() => setCreando(true)}
                                data-test="nuevo-turno-button"
                            >
                                <CalendarPlus />
                                Nuevo turno
                            </Button>
                        )}
                    </div>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                    <FullCalendar
                        plugins={[
                            dayGridPlugin,
                            timeGridPlugin,
                            interactionPlugin,
                        ]}
                        initialView="dayGridMonth"
                        headerToolbar={{
                            left: 'prev,next hoy',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,timeGridDay',
                        }}
                        customButtons={{
                            hoy: {
                                text: 'Hoy',
                                click: () => router.reload(),
                            },
                        }}
                        locale={esLocale}
                        events={turnos}
                        /* El taller no atiende domingos ni fuera de horario:
                           mostrar esas franjas sólo agrega ruido. */
                        hiddenDays={[0]}
                        slotMinTime="08:00:00"
                        slotMaxTime="18:30:00"
                        allDaySlot={false}
                        nowIndicator
                        height="auto"
                        datesSet={(info) =>
                            pedirRango(info.start, info.end, filtro)
                        }
                        eventClick={(evento: EventClickArg) => {
                            const turno = turnos.find(
                                (uno) => uno.id === evento.event.id,
                            );

                            if (turno) {
                                setAbierto(turno);
                            }
                        }}
                    />
                </div>
            </div>

            {/* Los diálogos van fuera del calendario: montados una sola vez. */}
            <Dialog
                open={abierto !== null}
                onOpenChange={(estaAbierto) => !estaAbierto && setAbierto(null)}
            >
                <DialogContent className="max-h-[90dvh] overflow-y-auto">
                    {abierto && (
                        <>
                            <DialogTitle>
                                {abierto.extendedProps.servicio}
                            </DialogTitle>

                            <DialogDescription>
                                {abierto.extendedProps.cliente} ·{' '}
                                {abierto.extendedProps.duracionLegible} ·{' '}
                                {abierto.extendedProps.puesto}
                            </DialogDescription>

                            <div className="grid gap-2 text-sm">
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant={
                                            TONO[abierto.extendedProps.estado]
                                        }
                                    >
                                        {abierto.extendedProps.estadoLabel}
                                    </Badge>

                                    <Badge variant="outline">
                                        {abierto.extendedProps.rubroLabel}
                                    </Badge>

                                    <span className="text-muted-foreground">
                                        {abierto.extendedProps.origen === 'web'
                                            ? 'Reserva web'
                                            : 'Carga interna'}
                                    </span>
                                </div>

                                <Separator />

                                <p>
                                    <span className="text-muted-foreground">
                                        Celular:{' '}
                                    </span>
                                    {abierto.extendedProps.celular}
                                </p>

                                <p>
                                    <span className="text-muted-foreground">
                                        Correo:{' '}
                                    </span>
                                    {abierto.extendedProps.email}
                                </p>

                                {abierto.extendedProps.vehiculo && (
                                    <p>
                                        <span className="text-muted-foreground">
                                            Vehículo:{' '}
                                        </span>
                                        {abierto.extendedProps.vehiculo}
                                    </p>
                                )}

                                {abierto.extendedProps.matricula && (
                                    <p>
                                        <span className="text-muted-foreground">
                                            Matrícula:{' '}
                                        </span>
                                        {abierto.extendedProps.matricula}
                                    </p>
                                )}

                                {abierto.extendedProps.comentario && (
                                    <p className="rounded-md bg-muted p-2">
                                        {abierto.extendedProps.comentario}
                                    </p>
                                )}
                            </div>

                            {puedeGestionar && (
                                <DialogFooter className="flex-wrap gap-2">
                                    {estados
                                        .filter(
                                            (estado) =>
                                                estado.value !==
                                                abierto.extendedProps.estado,
                                        )
                                        .map((estado) => (
                                            <Button
                                                key={estado.value}
                                                variant={
                                                    estado.value === 'cancelado'
                                                        ? 'outline'
                                                        : 'default'
                                                }
                                                onClick={() =>
                                                    cambiarEstado(
                                                        abierto,
                                                        estado.value,
                                                    )
                                                }
                                                data-test={`estado-${estado.value}-button`}
                                            >
                                                {estado.label}
                                            </Button>
                                        ))}

                                    <Button
                                        variant="destructive"
                                        onClick={() => eliminar(abierto)}
                                        data-test="eliminar-turno-button"
                                    >
                                        Eliminar
                                    </Button>
                                </DialogFooter>
                            )}
                        </>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog open={creando} onOpenChange={setCreando}>
                <DialogContent className="grid max-h-[90dvh] grid-rows-[auto_auto_minmax(0,1fr)] overflow-hidden">
                    <DialogTitle>Nuevo turno</DialogTitle>

                    <DialogDescription>
                        Entra confirmado y puede ser para hoy mismo: la ventana
                        de la web no se aplica acá.
                    </DialogDescription>

                    <Form
                        {...TurnoController.store.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={() => {
                            setCreando(false);
                            setHora('');
                        }}
                        className="grid min-h-0 grid-rows-[minmax(0,1fr)_auto] gap-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                {/* Sólo los campos scrollean: el pie con los
                                    botones queda siempre a la vista. */}
                                <div className="grid min-h-0 content-start gap-4 overflow-y-auto pr-1">
                                    <div className="grid gap-2">
                                        <Label htmlFor="servicio">
                                            Servicio
                                        </Label>

                                        <select
                                            id="servicio"
                                            name="servicio"
                                            className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs"
                                            value={servicio}
                                            onChange={(evento) => {
                                                setServicio(
                                                    evento.target.value,
                                                );
                                                pedirHuecos(
                                                    evento.target.value,
                                                    fecha,
                                                );
                                            }}
                                            required
                                        >
                                            <option value="">Elegí uno</option>

                                            {/* Agrupados por rubro: la lista
                                                mezcla services y lavados, y el
                                                nombre solo no alcanza para
                                                saber cuál es cuál. */}
                                            {rubros.map((deRubro) => (
                                                <optgroup
                                                    key={deRubro.value}
                                                    label={deRubro.label}
                                                >
                                                    {servicios
                                                        .filter(
                                                            (uno) =>
                                                                uno.rubro ===
                                                                deRubro.value,
                                                        )
                                                        .map((uno) => (
                                                            <option
                                                                key={uno.slug}
                                                                value={uno.slug}
                                                            >
                                                                {uno.nombre} ·{' '}
                                                                {
                                                                    uno.duracionLegible
                                                                }
                                                            </option>
                                                        ))}
                                                </optgroup>
                                            ))}
                                        </select>

                                        <InputError message={errors.servicio} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="fecha">Día</Label>

                                        <Input
                                            id="fecha"
                                            name="fecha"
                                            type="date"
                                            value={fecha}
                                            min={hoy()}
                                            onChange={(evento) => {
                                                setFecha(evento.target.value);
                                                pedirHuecos(
                                                    servicio,
                                                    evento.target.value,
                                                );
                                            }}
                                            required
                                        />

                                        <InputError message={errors.fecha} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Horario</Label>

                                        {huecos.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                Elegí servicio y día para ver
                                                los horarios libres.
                                            </p>
                                        ) : (
                                            <div className="flex flex-wrap gap-2">
                                                {huecos.map((libre) => (
                                                    <Button
                                                        key={libre}
                                                        type="button"
                                                        size="sm"
                                                        variant={
                                                            hora === libre
                                                                ? 'default'
                                                                : 'outline'
                                                        }
                                                        onClick={() =>
                                                            setHora(libre)
                                                        }
                                                        data-test={`hueco-${libre}`}
                                                    >
                                                        {libre}
                                                    </Button>
                                                ))}
                                            </div>
                                        )}

                                        <input
                                            type="hidden"
                                            name="hora"
                                            value={hora}
                                        />

                                        <InputError message={errors.hora} />
                                    </div>

                                    <div className="grid gap-2 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="nombre">
                                                Nombre
                                            </Label>
                                            <Input
                                                id="nombre"
                                                name="nombre"
                                                required
                                            />
                                            <InputError
                                                message={errors.nombre}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="apellido">
                                                Apellido
                                            </Label>
                                            <Input
                                                id="apellido"
                                                name="apellido"
                                                required
                                            />
                                            <InputError
                                                message={errors.apellido}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="email">
                                                Correo
                                            </Label>
                                            <Input
                                                id="email"
                                                name="email"
                                                type="email"
                                                required
                                            />
                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="celular">
                                                Celular
                                            </Label>
                                            <Input
                                                id="celular"
                                                name="celular"
                                                required
                                            />
                                            <InputError
                                                message={errors.celular}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="vehiculo_marca">
                                                Marca
                                            </Label>
                                            <Input
                                                id="vehiculo_marca"
                                                name="vehiculo_marca"
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="vehiculo_modelo">
                                                Modelo
                                            </Label>
                                            <Input
                                                id="vehiculo_modelo"
                                                name="vehiculo_modelo"
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="matricula">
                                                Matrícula
                                            </Label>
                                            <Input
                                                id="matricula"
                                                name="matricula"
                                                placeholder="ABC 1234"
                                            />
                                            <InputError
                                                message={errors.matricula}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="comentario">
                                            Comentario
                                        </Label>
                                        <Textarea
                                            id="comentario"
                                            name="comentario"
                                            rows={2}
                                        />
                                        <InputError
                                            message={errors.comentario}
                                        />
                                    </div>
                                </div>

                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            Cancelar
                                        </Button>
                                    </DialogClose>

                                    <Button
                                        disabled={processing || !hora}
                                        data-test="guardar-turno-button"
                                    >
                                        Agendar
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}

TurnosIndex.layout = {
    breadcrumbs: [{ title: 'Turnos', href: index() }],
};
