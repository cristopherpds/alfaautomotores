import { Form, router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, ImagePlus, Star, Trash2 } from 'lucide-react';
import { useRef } from 'react';
import VehiculoImagenController from '@/actions/App/Http/Controllers/Panel/VehiculoImagenController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import type { FotoVehiculo } from '@/types';

type VehiculoGaleriaProps = {
    vehiculoId: number;
    fotos: FotoVehiculo[];
    maxImagenes: number;
};

/**
 * Galería del vehículo. La portada es siempre la primera, así que "hacer
 * portada" y reordenar son la misma petición: se manda la lista completa de
 * ids en el orden nuevo.
 */
export default function VehiculoGaleria({
    vehiculoId,
    fotos,
    maxImagenes,
}: VehiculoGaleriaProps) {
    const archivos = useRef<HTMLInputElement>(null);

    const reordenar = (imagenes: number[]) => {
        router.patch(
            VehiculoImagenController.orden.url(vehiculoId),
            { imagenes },
            { preserveScroll: true },
        );
    };

    const mover = (desde: number, hasta: number) => {
        const orden = fotos.map((foto) => foto.id);
        const [movida] = orden.splice(desde, 1);
        orden.splice(hasta, 0, movida);

        reordenar(orden);
    };

    const hacerPortada = (indice: number) => mover(indice, 0);

    const eliminar = (fotoId: number) => {
        router.delete(
            VehiculoImagenController.destroy.url([vehiculoId, fotoId]),
            {
                preserveScroll: true,
            },
        );
    };

    const lleno = fotos.length >= maxImagenes;

    return (
        <section className="flex flex-col gap-4">
            <div>
                <h2 className="text-base font-medium">Galería</h2>
                <p className="text-sm text-muted-foreground">
                    La primera foto es la portada del catálogo. Hasta{' '}
                    {maxImagenes} fotos por vehículo.
                </p>
            </div>

            <Separator />

            {fotos.length === 0 ? (
                <p className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                    Todavía no hay fotos cargadas. Mientras tanto el sitio
                    público muestra el marcador "Foto pendiente".
                </p>
            ) : (
                <ul className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    {fotos.map((foto, indice) => (
                        <li
                            key={foto.id}
                            className="flex flex-col gap-2 rounded-lg border p-2"
                        >
                            <div className="relative overflow-hidden rounded-md bg-muted">
                                <img
                                    src={foto.url}
                                    alt={`Foto ${indice + 1}`}
                                    className="aspect-4/3 w-full object-cover"
                                />

                                {indice === 0 && (
                                    <Badge className="absolute top-2 left-2">
                                        <Star />
                                        Portada
                                    </Badge>
                                )}
                            </div>

                            <div className="flex items-center justify-between gap-1">
                                <div className="flex items-center gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        disabled={indice === 0}
                                        onClick={() =>
                                            mover(indice, indice - 1)
                                        }
                                        aria-label={`Mover la foto ${indice + 1} hacia atrás`}
                                    >
                                        <ArrowLeft />
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        disabled={indice === fotos.length - 1}
                                        onClick={() =>
                                            mover(indice, indice + 1)
                                        }
                                        aria-label={`Mover la foto ${indice + 1} hacia adelante`}
                                    >
                                        <ArrowRight />
                                    </Button>

                                    {indice !== 0 && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => hacerPortada(indice)}
                                            aria-label={`Usar la foto ${indice + 1} como portada`}
                                        >
                                            <Star />
                                        </Button>
                                    )}
                                </div>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => eliminar(foto.id)}
                                    aria-label={`Eliminar la foto ${indice + 1}`}
                                    data-test={`delete-foto-${foto.id}-button`}
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <Form
                {...VehiculoImagenController.store.form(vehiculoId)}
                options={{ preserveScroll: true }}
                resetOnSuccess
                onSuccess={() => {
                    if (archivos.current) {
                        archivos.current.value = '';
                    }
                }}
                className="grid gap-2"
            >
                {({ processing, errors }) => (
                    <>
                        <Label htmlFor="imagenes">Agregar fotos</Label>

                        <div className="flex flex-wrap items-center gap-2">
                            <Input
                                ref={archivos}
                                id="imagenes"
                                name="imagenes[]"
                                type="file"
                                multiple
                                accept="image/jpeg,image/png,image/webp"
                                disabled={lleno}
                                className="max-w-md"
                            />

                            <Button
                                disabled={processing || lleno}
                                data-test="upload-fotos-button"
                            >
                                <ImagePlus />
                                Subir
                            </Button>
                        </div>

                        {lleno && (
                            <p className="text-sm text-muted-foreground">
                                La galería ya tiene el máximo de {maxImagenes}{' '}
                                fotos.
                            </p>
                        )}

                        <InputError message={errors.imagenes} />
                    </>
                )}
            </Form>
        </section>
    );
}
