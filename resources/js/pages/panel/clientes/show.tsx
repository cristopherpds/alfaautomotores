import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { Car, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ClienteController from '@/actions/App/Http/Controllers/Panel/ClienteController';
import ClienteFormFields from '@/components/cliente-form-fields';
import Heading from '@/components/heading';
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
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/panel/clientes';
import type {
    ClienteEditable,
    TurnoDeCliente,
    VehiculoDeCliente,
} from '@/types';

type Props = {
    cliente: ClienteEditable;
    vehiculos: VehiculoDeCliente[];
    turnos: TurnoDeCliente[];
    can: { update: boolean; delete: boolean };
};

const fechaHora = new Intl.DateTimeFormat('es-UY', {
    dateStyle: 'short',
    timeStyle: 'short',
});

function EliminarCliente({ cliente }: { cliente: ClienteEditable }) {
    const [confirmando, setConfirmando] = useState(false);

    return (
        <>
            <Button
                variant="destructive"
                onClick={() => setConfirmando(true)}
                data-test="delete-cliente-button"
            >
                <Trash2 />
                Eliminar cliente
            </Button>

            <Dialog open={confirmando} onOpenChange={setConfirmando}>
                <DialogContent>
                    <DialogTitle>
                        ¿Eliminar a {cliente.nombre} {cliente.apellido}?
                    </DialogTitle>
                    <DialogDescription>
                        Se borran sus datos y sus notas. Sus turnos quedan en la
                        agenda con los datos de cada reserva. No se puede
                        deshacer.
                    </DialogDescription>

                    <Form {...ClienteController.destroy.form(cliente.id)}>
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
                                        data-test="confirm-delete-cliente-button"
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
 * La ficha de un cliente: sus datos (editables por quien gestiona), los
 * vehículos que trajo y el historial de turnos.
 */
export default function ShowCliente({
    cliente,
    vehiculos,
    turnos,
    can,
}: Props) {
    const nombre = `${cliente.nombre} ${cliente.apellido}`;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Clientes', href: index() },
            { title: nombre, href: ClienteController.show(cliente.id) },
        ],
    });

    return (
        <>
            <Head title={nombre} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={nombre}
                        description={`${turnos.length} ${turnos.length === 1 ? 'turno' : 'turnos'}`}
                    />
                    {cliente.acepta_novedades && (
                        <Badge>Acepta novedades</Badge>
                    )}
                </div>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Datos</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...ClienteController.update.form(cliente.id)}
                                options={{ preserveScroll: true }}
                                className="space-y-6"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <ClienteFormFields
                                            errors={errors}
                                            cliente={cliente}
                                            soloLectura={!can.update}
                                        />

                                        {can.update && (
                                            <Button
                                                disabled={processing}
                                                data-test="update-cliente-button"
                                            >
                                                Guardar cambios
                                            </Button>
                                        )}
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Vehículos</CardTitle>
                            <CardDescription>
                                Los que trajo a sus turnos.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {vehiculos.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Ningún vehículo cargado.
                                </p>
                            ) : (
                                <ul className="grid gap-2 text-sm">
                                    {vehiculos.map((vehiculo, indice) => (
                                        <li
                                            key={vehiculo.matricula ?? indice}
                                            className="flex items-center gap-2"
                                        >
                                            <Car className="size-4 text-muted-foreground" />
                                            <span>
                                                {[
                                                    vehiculo.marca,
                                                    vehiculo.modelo,
                                                    vehiculo.anio,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' ') || 'Sin datos'}
                                            </span>
                                            {vehiculo.matricula && (
                                                <Badge variant="outline">
                                                    {vehiculo.matricula}
                                                </Badge>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <section className="grid gap-3">
                    <h2 className="text-base font-medium">Historial</h2>
                    <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="px-4">
                                        Fecha
                                    </TableHead>
                                    <TableHead>Servicio</TableHead>
                                    <TableHead>Vehículo</TableHead>
                                    <TableHead>Estado</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {turnos.length === 0 && (
                                    <TableRow className="hover:bg-transparent">
                                        <TableCell
                                            colSpan={4}
                                            className="px-4 text-muted-foreground"
                                        >
                                            Todavía no reservó ningún turno.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {turnos.map((turno) => (
                                    <TableRow key={turno.id}>
                                        <TableCell className="px-4 whitespace-nowrap">
                                            {fechaHora.format(
                                                new Date(turno.inicia_at),
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {turno.servicio}
                                            <span className="block text-xs text-muted-foreground">
                                                {turno.rubroLabel}
                                                {turno.origen === 'web' &&
                                                    ' · por la web'}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {turno.vehiculo ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary">
                                                {turno.estadoLabel}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </section>

                {can.delete && (
                    <div>
                        <EliminarCliente cliente={cliente} />
                    </div>
                )}
            </div>
        </>
    );
}
