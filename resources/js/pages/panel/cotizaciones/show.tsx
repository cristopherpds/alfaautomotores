import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ImageOff, MessageCircle, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ClienteController from '@/actions/App/Http/Controllers/Panel/ClienteController';
import CotizacionController from '@/actions/App/Http/Controllers/Panel/CotizacionController';
import CotizacionEstadoBadge from '@/components/cotizacion-estado-badge';
import Heading from '@/components/heading';
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
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { whatsapp } from '@/lib/alfa';
import { index } from '@/routes/panel/cotizaciones';
import type { CotizacionDetalle, OpcionSelect } from '@/types';

type Props = {
    cotizacion: CotizacionDetalle;
    estados: OpcionSelect[];
    can: { update: boolean; delete: boolean };
};

const fecha = new Intl.DateTimeFormat('es-UY', {
    dateStyle: 'long',
    timeStyle: 'short',
});
const numero = new Intl.NumberFormat('es-UY');

/** El celular como lo espera `wa.me`: sólo dígitos, con el 598 adelante. */
function celularParaWhatsapp(celular: string): string {
    const digitos = celular.replace(/\D+/g, '');

    if (digitos.startsWith('598')) {
        return digitos;
    }

    return '598' + digitos.replace(/^0/, '');
}

/**
 * Una solicitud de «Cotizá tu auto»: el auto, las fotos y quién lo ofrece.
 */
export default function ShowCotizacion({ cotizacion, estados, can }: Props) {
    const [confirmando, setConfirmando] = useState(false);
    const vehiculo = `${cotizacion.marca} ${cotizacion.modelo} ${cotizacion.anio}`;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Cotizaciones', href: index() },
            {
                title: vehiculo,
                href: CotizacionController.show(cotizacion.id),
            },
        ],
    });

    const cambiarEstado = (estado: string) => {
        router.patch(
            CotizacionController.estado.url(cotizacion.id),
            { estado },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={vehiculo} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={vehiculo}
                        description={
                            cotizacion.creada
                                ? `Recibida el ${fecha.format(new Date(cotizacion.creada))}`
                                : undefined
                        }
                    />

                    <div className="flex flex-wrap items-center gap-2">
                        {can.update ? (
                            <Select
                                value={cotizacion.estado}
                                onValueChange={cambiarEstado}
                            >
                                <SelectTrigger
                                    className="w-44"
                                    aria-label="Estado"
                                    data-test="estado-cotizacion-select"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {estados.map((estado) => (
                                            <SelectItem
                                                key={estado.value}
                                                value={estado.value}
                                            >
                                                {estado.label}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        ) : (
                            <CotizacionEstadoBadge estado={cotizacion.estado} />
                        )}

                        {can.delete && (
                            <Button
                                variant="outline"
                                onClick={() => setConfirmando(true)}
                                data-test="delete-cotizacion-button"
                            >
                                <Trash2 />
                                Eliminar
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Fotos</CardTitle>
                            <CardDescription>
                                {cotizacion.fotos.length} de 4
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {cotizacion.fotos.length === 0 ? (
                                <Empty>
                                    <EmptyHeader>
                                        <EmptyMedia variant="icon">
                                            <ImageOff />
                                        </EmptyMedia>
                                        <EmptyTitle>Sin fotos</EmptyTitle>
                                        <EmptyDescription>
                                            Las fotos son opcionales: se pueden
                                            pedir por WhatsApp.
                                        </EmptyDescription>
                                    </EmptyHeader>
                                </Empty>
                            ) : (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {cotizacion.fotos.map((foto) => (
                                        <figure
                                            key={foto.lado}
                                            className="grid gap-2"
                                        >
                                            <a
                                                href={foto.url}
                                                target="_blank"
                                                rel="noopener"
                                                className="block overflow-hidden rounded-md border"
                                            >
                                                <img
                                                    src={foto.url}
                                                    alt={`${foto.label} del ${vehiculo}`}
                                                    className="aspect-[4/3] w-full object-cover"
                                                    loading="lazy"
                                                />
                                            </a>
                                            <figcaption className="text-sm text-muted-foreground">
                                                {foto.label}
                                            </figcaption>
                                        </figure>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Vehículo</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                                    <dt className="text-muted-foreground">
                                        Marca
                                    </dt>
                                    <dd>{cotizacion.marca}</dd>
                                    <dt className="text-muted-foreground">
                                        Modelo
                                    </dt>
                                    <dd>{cotizacion.modelo}</dd>
                                    <dt className="text-muted-foreground">
                                        Año
                                    </dt>
                                    <dd>{cotizacion.anio}</dd>
                                    <dt className="text-muted-foreground">
                                        Kilómetros
                                    </dt>
                                    <dd className="tabular-nums">
                                        {numero.format(cotizacion.kilometros)}{' '}
                                        km
                                    </dd>
                                </dl>

                                {cotizacion.detalles && (
                                    <p className="mt-4 text-sm whitespace-pre-line">
                                        {cotizacion.detalles}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Contacto</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="text-sm">
                                    {cotizacion.clienteId ? (
                                        <Link
                                            href={ClienteController.show(
                                                cotizacion.clienteId,
                                            )}
                                            className="font-medium underline-offset-4 hover:underline"
                                        >
                                            {cotizacion.nombre}
                                        </Link>
                                    ) : (
                                        <span className="font-medium">
                                            {cotizacion.nombre}
                                        </span>
                                    )}
                                    <span className="block text-muted-foreground">
                                        {cotizacion.celular}
                                    </span>
                                </div>

                                <Button variant="outline" asChild>
                                    <a
                                        href={whatsapp(
                                            celularParaWhatsapp(
                                                cotizacion.celular,
                                            ),
                                            `Hola ${cotizacion.nombre}, te escribimos de Alfa Automotores por la cotización de tu ${vehiculo}.`,
                                        )}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <MessageCircle />
                                        Escribir por WhatsApp
                                    </a>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <Dialog open={confirmando} onOpenChange={setConfirmando}>
                <DialogContent>
                    <DialogTitle>¿Eliminar la cotización?</DialogTitle>
                    <DialogDescription>
                        Se borran los datos del {vehiculo} y sus fotos. La ficha
                        del cliente queda. No se puede deshacer.
                    </DialogDescription>

                    <Form {...CotizacionController.destroy.form(cotizacion.id)}>
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
                                        data-test="confirm-delete-cotizacion-button"
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
