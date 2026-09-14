import { Form, Head, Link } from '@inertiajs/react';
import ServicioController from '@/actions/App/Http/Controllers/Panel/ServicioController';
import Heading from '@/components/heading';
import ServicioFormFields from '@/components/servicio-form-fields';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/panel/servicios';
import type { OpcionSelect, ServicioEditable } from '@/types';

type Props = {
    servicio: ServicioEditable;
    areas: OpcionSelect[];
};

/** Edición de un servicio. La foto sólo se reemplaza si se elige una nueva. */
export default function EditServicio({ servicio, areas }: Props) {
    return (
        <>
            <Head title={servicio.nombre} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title={servicio.nombre}
                    description={
                        servicio.turnos_count > 0
                            ? `Tiene ${servicio.turnos_count} turno(s): no se puede borrar, pero sí ocultar.`
                            : 'Todavía no tiene turnos.'
                    }
                />

                <Form
                    {...ServicioController.update.form(servicio.id)}
                    className="max-w-3xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <ServicioFormFields
                                areas={areas}
                                errors={errors}
                                servicio={servicio}
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

EditServicio.layout = {
    breadcrumbs: [{ title: 'Servicios', href: index() }],
};
