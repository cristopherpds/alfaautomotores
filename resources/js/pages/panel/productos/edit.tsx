import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import ProductoController from '@/actions/App/Http/Controllers/Panel/ProductoController';
import ProductoImagenController from '@/actions/App/Http/Controllers/Panel/ProductoImagenController';
import GaleriaFotos from '@/components/galeria-fotos';
import Heading from '@/components/heading';
import ProductoFormFields from '@/components/producto-form-fields';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { indiceDeSeccion, TITULO_SECCION } from '@/lib/productos-panel';
import type {
    OpcionesProducto,
    ProductoEditable,
    SeccionProducto,
} from '@/types';

type Props = {
    seccion: SeccionProducto;
    producto: ProductoEditable;
    opciones: OpcionesProducto;
    maxImagenes: number;
};

export default function EditProducto({
    seccion,
    producto,
    opciones,
    maxImagenes,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: TITULO_SECCION[seccion], href: indiceDeSeccion(seccion) },
            {
                title: producto.nombre,
                href: ProductoController.edit(producto.id),
            },
        ],
    });

    return (
        <>
            <Head title={producto.nombre} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title={producto.nombre}
                    description="Editá la ficha y administrá las fotos del catálogo"
                />

                <div className="flex max-w-3xl flex-col gap-6">
                    <Form
                        {...ProductoController.update.form(producto.id)}
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <ProductoFormFields
                                    opciones={opciones}
                                    errors={errors}
                                    producto={producto}
                                />

                                <div className="flex items-center gap-4">
                                    <Button
                                        disabled={processing}
                                        data-test="update-producto-button"
                                    >
                                        Guardar cambios
                                    </Button>

                                    <Button variant="ghost" asChild>
                                        <Link href={indiceDeSeccion(seccion)}>
                                            Volver
                                        </Link>
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>

                    <Separator />

                    <GaleriaFotos
                        fotos={producto.fotos}
                        maxImagenes={maxImagenes}
                        subir={ProductoImagenController.store.form(producto.id)}
                        ordenUrl={ProductoImagenController.orden.url(
                            producto.id,
                        )}
                        eliminarUrl={(fotoId) =>
                            ProductoImagenController.destroy.url([
                                producto.id,
                                fotoId,
                            ])
                        }
                        sujeto="producto"
                    />
                </div>
            </div>
        </>
    );
}
