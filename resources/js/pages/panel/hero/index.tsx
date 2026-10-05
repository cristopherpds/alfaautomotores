import { Form, Head, Link } from '@inertiajs/react';
import { GalleryHorizontal, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import HeroSlideController from '@/actions/App/Http/Controllers/Panel/HeroSlideController';
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
import { create, index } from '@/routes/panel/hero';
import type { ManagedHeroSlide } from '@/types';

type Props = {
    slides: ManagedHeroSlide[];
    puedeGestionar: boolean;
};

const ESTADOS: Record<
    ManagedHeroSlide['estado'],
    { label: string; variant: 'default' | 'secondary' | 'outline' }
> = {
    activo: { label: 'En la portada', variant: 'default' },
    programado: { label: 'Programado', variant: 'secondary' },
    vencido: { label: 'Vencido', variant: 'outline' },
    inactivo: { label: 'Apagado', variant: 'outline' },
};

/** `2026-10-05` → `05/10/2026`, sin pasar por la zona horaria. */
function fecha(valor: string): string {
    return valor.split('-').reverse().join('/');
}

function vigencia(slide: ManagedHeroSlide): string {
    if (slide.desde && slide.hasta) {
        return `${fecha(slide.desde)} – ${fecha(slide.hasta)}`;
    }

    if (slide.desde) {
        return `Desde ${fecha(slide.desde)}`;
    }

    if (slide.hasta) {
        return `Hasta ${fecha(slide.hasta)}`;
    }

    return 'Siempre';
}

/**
 * Los slides del hero de la portada.
 *
 * Sin ninguno en la portada, el sitio muestra el hero de siempre (el video de
 * la ruta), así la primera vista nunca queda vacía.
 */
export default function HeroIndex({ slides, puedeGestionar }: Props) {
    const [borrando, setBorrando] = useState<ManagedHeroSlide | null>(null);

    return (
        <>
            <Head title="Portada" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Portada"
                        description="Los slides del hero de la página de inicio. Pasan solos, en el orden de la tabla."
                    />

                    {puedeGestionar && (
                        <Button asChild data-test="create-hero-button">
                            <Link href={create()}>
                                <Plus />
                                Nuevo slide
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-24 sm:w-32">
                                    Fondo
                                </TableHead>
                                <TableHead>Título</TableHead>
                                <TableHead className="hidden md:table-cell">
                                    Vigencia
                                </TableHead>
                                <TableHead className="hidden md:table-cell">
                                    Orden
                                </TableHead>
                                <TableHead className="hidden sm:table-cell">
                                    Estado
                                </TableHead>
                                <TableHead className="w-20 sm:w-24" />
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {slides.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6}>
                                        <Empty>
                                            <EmptyHeader>
                                                <EmptyMedia variant="icon">
                                                    <GalleryHorizontal />
                                                </EmptyMedia>
                                                <EmptyTitle>
                                                    Todavía no hay slides
                                                </EmptyTitle>
                                                <EmptyDescription>
                                                    Mientras tanto, la portada
                                                    muestra el hero de siempre.
                                                </EmptyDescription>
                                            </EmptyHeader>
                                        </Empty>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                slides.map((slide) => (
                                    <TableRow key={slide.id}>
                                        <TableCell>
                                            <div className="aspect-video w-20 overflow-hidden rounded-md bg-muted sm:w-28">
                                                {slide.tipoFondo === 'video' ? (
                                                    <video
                                                        // El primer cuadro suele ser negro: la miniatura muestra el segundo 1.
                                                        src={`${slide.fondo}#t=1`}
                                                        muted
                                                        preload="metadata"
                                                        className="size-full object-cover"
                                                        aria-hidden="true"
                                                    />
                                                ) : (
                                                    <img
                                                        src={slide.fondo}
                                                        alt=""
                                                        className="size-full object-cover"
                                                    />
                                                )}
                                            </div>
                                        </TableCell>

                                        {/* En celular la fila deja sólo fondo,
                                            título y acciones: el estado baja
                                            acá para no empujar los botones
                                            fuera de la pantalla. */}
                                        <TableCell className="max-w-xs whitespace-normal">
                                            <p className="line-clamp-2 font-medium">
                                                {slide.titulo}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {slide.tipoFondo === 'video'
                                                    ? 'Video'
                                                    : 'Imagen'}
                                                {slide.botones.length > 0 &&
                                                    ` · ${slide.botones.length} ${slide.botones.length > 1 ? 'botones' : 'botón'}`}
                                            </p>
                                            <Badge
                                                variant={
                                                    ESTADOS[slide.estado]
                                                        .variant
                                                }
                                                className="mt-1 sm:hidden"
                                            >
                                                {ESTADOS[slide.estado].label}
                                            </Badge>
                                        </TableCell>

                                        <TableCell className="hidden text-muted-foreground md:table-cell">
                                            {vigencia(slide)}
                                        </TableCell>

                                        <TableCell className="hidden text-muted-foreground md:table-cell">
                                            {slide.orden}
                                        </TableCell>

                                        <TableCell className="hidden sm:table-cell">
                                            <Badge
                                                variant={
                                                    ESTADOS[slide.estado]
                                                        .variant
                                                }
                                            >
                                                {ESTADOS[slide.estado].label}
                                            </Badge>
                                        </TableCell>

                                        <TableCell>
                                            {puedeGestionar && (
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Editar «${slide.titulo}»`}
                                                        data-test={`edit-hero-${slide.id}-button`}
                                                    >
                                                        <Link
                                                            href={HeroSlideController.edit(
                                                                slide.id,
                                                            )}
                                                        >
                                                            <Pencil />
                                                        </Link>
                                                    </Button>

                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Eliminar «${slide.titulo}»`}
                                                        onClick={() =>
                                                            setBorrando(slide)
                                                        }
                                                        data-test={`delete-hero-${slide.id}-button`}
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
                    <DialogTitle>Eliminar slide</DialogTitle>

                    <DialogDescription>
                        Se va «{borrando?.titulo}» con su fondo y no se puede
                        deshacer. Si es por un tiempo, mejor apagalo.
                    </DialogDescription>

                    {borrando && (
                        <Form
                            {...HeroSlideController.destroy.form(borrando.id)}
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
                                    data-test={`confirm-delete-hero-${borrando.id}-button`}
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

HeroIndex.layout = {
    breadcrumbs: [{ title: 'Portada', href: index() }],
};
