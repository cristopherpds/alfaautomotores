import { Form, Head, router } from '@inertiajs/react';
import { CalendarDays, ImagePlus, Images, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import EntregaController from '@/actions/App/Http/Controllers/Panel/EntregaController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { index } from '@/routes/panel/entregas';
import type { ManagedEntrega } from '@/types';

type Props = {
    entregas: ManagedEntrega[];
    puedeGestionar: boolean;
    maxPorLote: number;
};

/**
 * Hoy en `aaaa-mm-dd`, que es lo que espera un `<input type="date">`.
 *
 * `en-CA` da ese formato en la zona horaria del navegador; `toISOString()`
 * pasaría por UTC y de madrugada devolvería el día siguiente.
 */
function hoy(): string {
    return new Date().toLocaleDateString('en-CA');
}

/**
 * Las fotos de la tira «Nuestros clientes» de la portada.
 *
 * Se sube un lote con una fecha común —lo habitual es cargar varias fotos de la
 * misma entrega— y después se corrige la de cualquiera. No hay orden que tocar:
 * la tira se arma sola, de la entrega más nueva a la más vieja.
 */
export default function EntregasIndex({
    entregas,
    puedeGestionar,
    maxPorLote,
}: Props) {
    const archivos = useRef<HTMLInputElement>(null);

    /* La fecha es estado controlado y no se resetea al subir: cargar una entrega
       vieja suele llevar más de una tanda, y volver a hoy cada vez obligaría a
       elegirla de nuevo. */
    const [fecha, setFecha] = useState(hoy);

    /* Un solo diálogo para toda la página: se abre con la entrega elegida. */
    const [editando, setEditando] = useState<ManagedEntrega | null>(null);

    const eliminar = (entregaId: number) => {
        router.delete(EntregaController.destroy.url(entregaId), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Entregas" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Entregas"
                    description="Las fotos que corren en «Nuestros clientes», en la portada."
                />

                {puedeGestionar && (
                    <Form
                        {...EntregaController.store.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={() => {
                            if (archivos.current) {
                                archivos.current.value = '';
                            }
                        }}
                        className="grid gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="fecha">
                                        Fecha de la entrega
                                    </Label>

                                    <Input
                                        id="fecha"
                                        name="fecha"
                                        type="date"
                                        value={fecha}
                                        max={hoy()}
                                        onChange={(evento) =>
                                            setFecha(evento.target.value)
                                        }
                                        className="max-w-48"
                                        data-test="fecha-lote-input"
                                    />

                                    <InputError message={errors.fecha} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="fotos">Fotos</Label>

                                    <Input
                                        ref={archivos}
                                        id="fotos"
                                        name="fotos[]"
                                        type="file"
                                        multiple
                                        accept="image/jpeg,image/png,image/webp"
                                        className="max-w-md"
                                        data-test="fotos-input"
                                    />

                                    <p className="text-sm text-muted-foreground">
                                        La misma fecha se aplica a todas;
                                        después se puede corregir foto por foto.
                                        Hasta {maxPorLote} por vez. Los
                                        recuadros son 9:16, como una historia:
                                        con 640px de ancho alcanza.
                                    </p>

                                    <InputError message={errors.fotos} />
                                </div>

                                <div>
                                    <Button
                                        disabled={processing}
                                        data-test="upload-entregas-button"
                                    >
                                        <ImagePlus />
                                        Subir
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}

                <Separator />

                {entregas.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Images />
                            </EmptyMedia>

                            <EmptyTitle>
                                Todavía no hay entregas cargadas
                            </EmptyTitle>

                            <EmptyDescription>
                                Hasta que subas la primera, la tira «Nuestros
                                clientes» no se dibuja en la portada.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <ul className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        {entregas.map((entrega) => (
                            <li
                                key={entrega.id}
                                className="flex flex-col gap-2 rounded-lg border p-2"
                            >
                                <img
                                    src={entrega.url}
                                    alt={`Entrega del ${entrega.legible}`}
                                    loading="lazy"
                                    className="aspect-9/16 w-full rounded-md bg-muted object-cover"
                                />

                                <div className="flex items-center justify-between gap-1">
                                    <time
                                        dateTime={entrega.fecha}
                                        className="pl-1 text-sm text-muted-foreground"
                                    >
                                        {entrega.etiqueta}
                                    </time>

                                    {puedeGestionar && (
                                        <div className="flex items-center gap-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    setEditando(entrega)
                                                }
                                                aria-label={`Corregir la fecha de la entrega del ${entrega.legible}`}
                                                data-test={`edit-entrega-${entrega.id}-button`}
                                            >
                                                <CalendarDays />
                                            </Button>

                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    eliminar(entrega.id)
                                                }
                                                aria-label={`Eliminar la entrega del ${entrega.legible}`}
                                                data-test={`delete-entrega-${entrega.id}-button`}
                                            >
                                                <Trash2 />
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {/* Fuera de la grilla y controlado por estado: montado una sola vez
                para toda la página. */}
            <Dialog
                open={editando !== null}
                onOpenChange={(abierto) => !abierto && setEditando(null)}
            >
                <DialogContent>
                    <DialogTitle>Corregir la fecha</DialogTitle>

                    <DialogDescription>
                        Es la fecha que se imprime sobre la foto y la que ordena
                        la tira.
                    </DialogDescription>

                    {editando && (
                        <Form
                            {...EntregaController.update.form(editando.id)}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setEditando(null)}
                            className="grid gap-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="fecha-entrega">
                                            Fecha de la entrega
                                        </Label>

                                        <Input
                                            id="fecha-entrega"
                                            name="fecha"
                                            type="date"
                                            defaultValue={editando.fecha}
                                            max={hoy()}
                                            className="max-w-48"
                                        />

                                        <InputError message={errors.fecha} />
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
                                            disabled={processing}
                                            data-test="update-entrega-button"
                                        >
                                            Guardar
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

EntregasIndex.layout = {
    breadcrumbs: [{ title: 'Entregas', href: index() }],
};
