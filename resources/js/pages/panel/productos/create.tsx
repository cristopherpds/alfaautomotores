import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import ProductoController from '@/actions/App/Http/Controllers/Panel/ProductoController';
import Heading from '@/components/heading';
import ProductoFormFields from '@/components/producto-form-fields';
import { Button } from '@/components/ui/button';
import { indiceDeSeccion, TITULO_SECCION } from '@/lib/productos-panel';
import { create } from '@/routes/panel/productos';
import type { OpcionesProducto, SeccionProducto } from '@/types';

type Props = {
    seccion: SeccionProducto;
    familiaInicial: string | null;
    opciones: OpcionesProducto;
};

export default function CreateProducto({
    seccion,
    familiaInicial,
    opciones,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: TITULO_SECCION[seccion], href: indiceDeSeccion(seccion) },
            {
                title: 'Nuevo producto',
                href: create({ query: { seccion } }),
            },
        ],
    });

    return (
        <>
            <Head title="Nuevo producto" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Nuevo producto"
                    description="Cargá la ficha; las fotos se agregan en el paso siguiente"
                />

                <Form
                    {...ProductoController.store.form()}
                    className="max-w-3xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <ProductoFormFields
                                opciones={opciones}
                                errors={errors}
                                familiaInicial={familiaInicial}
                            />

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="store-producto-button"
                                >
                                    Crear producto
                                </Button>

                                <Button variant="ghost" asChild>
                                    <Link href={indiceDeSeccion(seccion)}>
                                        Cancelar
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
