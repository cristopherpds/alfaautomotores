import { Form, Head, Link } from '@inertiajs/react';
import VehiculoController from '@/actions/App/Http/Controllers/Panel/VehiculoController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import VehiculoFormFields from '@/components/vehiculo-form-fields';
import { create, index } from '@/routes/panel/vehiculos';
import type { OpcionesVehiculo } from '@/types';

export default function CreateVehiculo({
    opciones,
}: {
    opciones: OpcionesVehiculo;
}) {
    return (
        <>
            <Head title="Nuevo vehículo" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Nuevo vehículo"
                    description="Cargá la ficha; las fotos se agregan en el paso siguiente"
                />

                <Form
                    {...VehiculoController.store.form()}
                    className="max-w-3xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <VehiculoFormFields
                                opciones={opciones}
                                errors={errors}
                            />

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="store-vehiculo-button"
                                >
                                    Crear vehículo
                                </Button>

                                <Button variant="ghost" asChild>
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

CreateVehiculo.layout = {
    breadcrumbs: [
        { title: 'Vehículos', href: index() },
        { title: 'Nuevo vehículo', href: create() },
    ],
};
