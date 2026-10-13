import { Form, Link, router } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    Bot,
    Contact,
    Trash2,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import ClienteController from '@/actions/App/Http/Controllers/Panel/ClienteController';
import ConversacionController from '@/actions/App/Http/Controllers/Panel/ConversacionController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import type { ConversacionAbierta } from '@/types';

const fechaHora = new Intl.DateTimeFormat('es-UY', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

/** La columna de la derecha: quién es, quién la atiende y qué hacer con ella. */
export function PanelContacto({
    conversacion,
}: {
    conversacion: ConversacionAbierta;
}) {
    const [confirmando, setConfirmando] = useState(false);
    const atiendeLaIa = conversacion.iaActiva && !conversacion.derivada;
    const ficha = Object.entries(conversacion.ficha).filter(
        ([, valor]) => valor !== null && valor !== '',
    );

    const cambiarIa = (activa: boolean) =>
        router.patch(
            ConversacionController.ia.url(conversacion.id),
            { activa },
            { preserveScroll: true, preserveState: true },
        );

    return (
        <aside className="hidden min-h-0 min-w-0 flex-col gap-5 overflow-y-auto border-l border-sidebar-border/70 p-4 xl:flex">
            <div>
                <h2 className="font-semibold">{conversacion.nombre}</h2>
                <p className="text-sm text-muted-foreground">
                    {conversacion.telefono}
                </p>
                {conversacion.nombrePerfil &&
                    conversacion.nombrePerfil !== conversacion.nombre && (
                        <p className="text-xs text-muted-foreground">
                            En WhatsApp: {conversacion.nombrePerfil}
                        </p>
                    )}
            </div>

            {conversacion.derivada && (
                <Alert variant="destructive">
                    <UserRound />
                    <AlertTitle>Atención humana</AlertTitle>
                    <AlertDescription>
                        {conversacion.motivoDerivacion ?? 'La IA no contesta.'}
                    </AlertDescription>
                </Alert>
            )}

            <div className="flex items-start justify-between gap-3">
                <div className="grid gap-1">
                    <Label
                        htmlFor="ia-activa"
                        className="flex items-center gap-2"
                    >
                        <Bot className="size-4" />
                        Respuesta automática
                    </Label>
                    <p className="text-xs text-muted-foreground">
                        {atiendeLaIa
                            ? 'La IA contesta esta conversación.'
                            : 'La atiende el equipo: la IA no contesta.'}
                    </p>
                </div>
                <Switch
                    id="ia-activa"
                    checked={atiendeLaIa}
                    onCheckedChange={cambiarIa}
                    data-test="ia-switch"
                />
            </div>

            {conversacion.ventanaCierraAt && (
                <p className="text-xs text-muted-foreground">
                    Se puede escribir libre hasta el{' '}
                    {fechaHora.format(new Date(conversacion.ventanaCierraAt))}.
                </p>
            )}

            <Separator />

            <section className="grid gap-2">
                <h3 className="text-sm font-medium">Ficha de cliente</h3>
                {conversacion.cliente ? (
                    <>
                        <Link
                            href={ClienteController.show(
                                conversacion.cliente.id,
                            )}
                            className="flex items-center gap-2 text-sm underline-offset-4 hover:underline"
                        >
                            <Contact className="size-4" />
                            {conversacion.cliente.nombre}
                        </Link>
                        {conversacion.cliente.email && (
                            <p className="text-xs text-muted-foreground">
                                {conversacion.cliente.email}
                            </p>
                        )}
                        {conversacion.cliente.turnos.length > 0 && (
                            <ul className="grid gap-1 text-xs">
                                {conversacion.cliente.turnos.map((turno) => (
                                    <li
                                        key={turno.id}
                                        className="text-muted-foreground"
                                    >
                                        {fechaHora.format(
                                            new Date(turno.iniciaAt),
                                        )}{' '}
                                        · {turno.servicio} · {turno.estado}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </>
                ) : (
                    <p className="text-xs text-muted-foreground">
                        Sin ficha todavía: se vincula sola cuando reserve un
                        turno o pida una cotización con este número.
                    </p>
                )}
            </section>

            {ficha.length > 0 && (
                <section className="grid gap-2">
                    <h3 className="text-sm font-medium">Lo que sabemos</h3>
                    <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
                        {ficha.map(([clave, valor]) => (
                            <div key={clave} className="contents">
                                <dt className="text-muted-foreground">
                                    {clave}
                                </dt>
                                <dd>{String(valor)}</dd>
                            </div>
                        ))}
                    </dl>
                </section>
            )}

            <Separator />

            <div className="grid gap-2">
                <Button
                    variant="outline"
                    onClick={() =>
                        router.patch(
                            ConversacionController.archivar.url(
                                conversacion.id,
                            ),
                            { archivada: !conversacion.archivada },
                            { preserveScroll: true, preserveState: true },
                        )
                    }
                >
                    {conversacion.archivada ? <ArchiveRestore /> : <Archive />}
                    {conversacion.archivada
                        ? 'Volver a la bandeja'
                        : 'Archivar'}
                </Button>

                {conversacion.can.delete && (
                    <Button
                        variant="ghost"
                        className="text-destructive"
                        onClick={() => setConfirmando(true)}
                    >
                        <Trash2 />
                        Eliminar conversación
                    </Button>
                )}
            </div>

            <Dialog open={confirmando} onOpenChange={setConfirmando}>
                <DialogContent>
                    <DialogTitle>¿Eliminar la conversación?</DialogTitle>
                    <DialogDescription>
                        Se borran todos los mensajes y archivos de{' '}
                        {conversacion.nombre} en el panel. En el celular del
                        cliente quedan. No se puede deshacer.
                    </DialogDescription>
                    <Form
                        {...ConversacionController.destroy.form(
                            conversacion.id,
                        )}
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
                                    <button type="submit">Eliminar</button>
                                </Button>
                            </DialogFooter>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </aside>
    );
}
