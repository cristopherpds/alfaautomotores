import { Form, Head, Link } from '@inertiajs/react';
import ServicioController from '@/actions/App/Http/Controllers/Panel/ServicioController';
import Heading from '@/components/heading';
import ServicioFormFields from '@/components/servicio-form-fields';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/panel/servicios';
import type { OpcionSelect } from '@/types';

type Props = {
    areas: OpcionSelect[];
    rubros: OpcionSelect[];
};

/** Alta de un servicio: el rubro decide en qué página aparece. */
export default function CreateServicio({ areas, rubros }: Props) {
    return (
        <>
            <Head title="Nuevo servicio" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Nuevo servicio"
                    description="Se muestra en la página de su rubro apenas quede visible."
                />

                <Form
                    {...ServicioController.store.form()}
                    className="max-w-3xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <ServicioFormFields
                                areas={areas}
                                rubros={rubros}
                                errors={errors}
                            />

                            <div className="flex gap-2">
                                <Button
                                    disabled={processing}
                                    data-test="save-servicio-button"
                                >
                                    Guardar
                                </Button>

                                <Button asChild variant="secondary">
                                    <Link href={index()}>Cancelar</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CreateServicio.layout = {
    breadcrumbs: [
        { title: 'Servicios', href: index() },
        { title: 'Nuevo servicio', href: create() },
    ],
};
