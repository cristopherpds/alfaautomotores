import { Form, Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Wrench } from 'lucide-react';
import { useState } from 'react';
import ServicioController from '@/actions/App/Http/Controllers/Panel/ServicioController';
import Heading from '@/components/heading';
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
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, index } from '@/routes/panel/servicios';
import type { ManagedServicio } from '@/types';

type Props = {
    servicios: ManagedServicio[];
    puedeGestionar: boolean;
};

/**
 * Los trabajos del taller.
 *
 * Un servicio con turnos no se borra: la clave foránea lo impide y el panel
 * ofrece desactivarlo, que lo saca de la web sin perder la historia.
 */
export default function ServiciosIndex({ servicios, puedeGestionar }: Props) {
    const [borrando, setBorrando] = useState<ManagedServicio | null>(null);

    return (
        <>
            <Head title="Servicios" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Servicios"
                        description="Lo que ofrece el taller. La duración es el largo del turno."
                    />

                    {puedeGestionar && (
                        <Button asChild data-test="create-servicio-button">
                            <Link href={create()}>
                                <Plus />
                                Nuevo servicio
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Servicio</TableHead>
                                <TableHead>Área</TableHead>
                                <TableHead>Duración</TableHead>
                                <TableHead>Turnos</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead className="w-24" />
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {servicios.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6}>
                                        <Empty>
                                            <EmptyHeader>
                                                <EmptyMedia variant="icon">
                                                    <Wrench />
                                                </EmptyMedia>
                                                <EmptyTitle>
                                                    Todavía no hay servicios
                                                </EmptyTitle>
                                                <EmptyDescription>
                                                    Sin servicios cargados, la
                                                    página del taller queda sin
                                                    nada para reservar.
                                                </EmptyDescription>
                                            </EmptyHeader>
                                        </Empty>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                servicios.map((servicio) => (
                                    <TableRow key={servicio.id}>
                                        <TableCell className="font-medium">
                                            {servicio.nombre}
                                        </TableCell>

                                        <TableCell className="text-muted-foreground">
                                            {servicio.areaLabel}
                                        </TableCell>

                                        <TableCell>
                                            {servicio.duracionLegible}
                                        </TableCell>

                                        <TableCell className="text-muted-foreground">
                                            {servicio.turnos_count}
                                        </TableCell>

                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                <Badge
                                                    variant={
                                                        servicio.activo
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                >
                                                    {servicio.activo
                                                        ? 'Visible'
                                                        : 'Oculto'}
                                                </Badge>

                                                {!servicio.agendable && (
                                                    <Badge variant="secondary">
                                                        Sin reserva
                                                    </Badge>
                                                )}
                                            </div>
                                        </TableCell>

                                        <TableCell>
                                            {puedeGestionar && (
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Editar ${servicio.nombre}`}
                                                        data-test={`edit-servicio-${servicio.id}-button`}
                                                    >
                                                        <Link
                                                            href={ServicioController.edit(
                                                                servicio.id,
                                                            )}
                                                        >
                                                            <Pencil />
                                                        </Link>
                                                    </Button>

                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Eliminar ${servicio.nombre}`}
                                                        onClick={() =>
                                                            servicio.turnos_count >
                                                            0
                                                                ? router.visit(
                                                                      ServicioController.edit(
                                                                          servicio.id,
                                                                      ),
                                                                  )
                                                                : setBorrando(
                                                                      servicio,
                                                                  )
                                                        }
                                                        disabled={
                                                            servicio.turnos_count >
                                                            0
                                                        }
                                                        data-test={`delete-servicio-${servicio.id}-button`}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </div>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            {/* El diálogo vive fuera de la tabla: montado una sola vez. */}
            <Dialog
                open={borrando !== null}
                onOpenChange={(abierto) => !abierto && setBorrando(null)}
            >
                <DialogContent>
                    <DialogTitle>Eliminar servicio</DialogTitle>

                    <DialogDescription>
                        Se va «{borrando?.nombre}» y no se puede deshacer. Si
                        alguna vez tuvo turnos, mejor desactivalo.
                    </DialogDescription>

                    {borrando && (
                        <Form
                            {...ServicioController.destroy.form(borrando.id)}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setBorrando(null)}
                        >
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>

                                <Button
                                    variant="destructive"
                                    data-test={`confirm-delete-servicio-${borrando.id}-button`}
                                >
                                    Eliminar
                                </Button>
                            </DialogFooter>
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

ServiciosIndex.layout = {
    breadcrumbs: [{ title: 'Servicios', href: index() }],
};
