import { Form, Head, Link } from '@inertiajs/react';
import VehiculoController from '@/actions/App/Http/Controllers/Panel/VehiculoController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import VehiculoDestacadoSwitch from '@/components/vehiculo-destacado-switch';
import VehiculoFormFields from '@/components/vehiculo-form-fields';
import VehiculoGaleria from '@/components/vehiculo-galeria';
import { index } from '@/routes/panel/vehiculos';
import type { OpcionesVehiculo, VehiculoEditable } from '@/types';

type Props = {
    vehiculo: VehiculoEditable;
    opciones: OpcionesVehiculo;
    maxImagenes: number;
};

export default function EditVehiculo({
    vehiculo,
    opciones,
    maxImagenes,
}: Props) {
    return (
        <>
            <Head title={vehiculo.titulo} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title={vehiculo.titulo}
                    description="Editá la ficha y administrá las fotos del catálogo"
                />

                <div className="flex max-w-3xl flex-col gap-6">
                    <div className="flex items-center justify-between gap-4 rounded-lg border p-4">
                        <div className="grid gap-1">
                            <Label htmlFor="destacado">
                                Destacar en la portada
                            </Label>
                            <p className="text-sm text-muted-foreground">
                                {vehiculo.destacable
                                    ? 'Los destacados encabezan la home.'
                                    : 'Un borrador no puede ir a la portada. Publicalo primero.'}
                            </p>
                        </div>

                        <VehiculoDestacadoSwitch
                            vehiculoId={vehiculo.id}
                            destacado={vehiculo.destacado}
                            disabled={!vehiculo.destacable}
                            aria-label={`Destacar ${vehiculo.titulo} en la portada`}
                        />
                    </div>

                    <Form
                        {...VehiculoController.update.form(vehiculo.id)}
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <VehiculoFormFields
                                    opciones={opciones}
                                    errors={errors}
                                    vehiculo={vehiculo}
                                />

                                <div className="flex items-center gap-4">
                                    <Button
                                        disabled={processing}
                                        data-test="update-vehiculo-button"
                                    >
                                        Guardar cambios
                                    </Button>

                                    <Button variant="ghost" asChild>
                                        <Link href={index()}>Volver</Link>
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>

                    <Separator />

                    <VehiculoGaleria
                        vehiculoId={vehiculo.id}
                        fotos={vehiculo.fotos}
                        maxImagenes={maxImagenes}
                    />
                </div>
            </div>
        </>
    );
}

EditVehiculo.layout = {
    breadcrumbs: [{ title: 'Vehículos', href: index() }],
};
