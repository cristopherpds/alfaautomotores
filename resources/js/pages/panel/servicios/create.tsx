import { Form, Head, Link } from '@inertiajs/react';
import ServicioController from '@/actions/App/Http/Controllers/Panel/ServicioController';
import Heading from '@/components/heading';
import ServicioFormFields from '@/components/servicio-form-fields';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/panel/servicios';
import type { OpcionSelect } from '@/types';

type Props = {
    areas: OpcionSelect[];
};

/** Alta de un servicio del taller. */
export default function CreateServicio({ areas }: Props) {
    return (
        <>
            <Head title="Nuevo servicio" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Nuevo servicio"
                    description="Se muestra en la página del taller apenas quede visible."
                />

                <Form
                    {...ServicioController.store.form()}
                    className="max-w-3xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <ServicioFormFields areas={areas} errors={errors} />

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
