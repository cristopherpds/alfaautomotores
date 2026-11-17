import { Head, useForm } from '@inertiajs/react';
import AjusteController from '@/actions/App/Http/Controllers/Panel/AjusteController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { avisos as rutaAvisos } from '@/routes/panel/ajustes';

type Avisos = {
    confirmacion_turno: boolean;
};

type Props = {
    avisos: Avisos;
};

/**
 * Los emails que salen hacia los clientes. Los avisos al equipo (campana y
 * email de cada cotización o turno nuevo) no se apagan: llegan a admins y
 * vendedores.
 */
export default function AjusteAvisos({ avisos }: Props) {
    const form = useForm<Avisos>(avisos);

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();
        form.submit(AjusteController.guardarAvisos(), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Avisos por email" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Avisos por email"
                    description="Qué emails reciben los clientes. Cada cotización y cada turno que entra desde la web o WhatsApp les llega siempre a admins y vendedores, en la campana y por email."
                />

                <form
                    onSubmit={guardar}
                    className="flex max-w-2xl flex-col gap-6"
                >
                    <div className="flex items-center justify-between gap-4 rounded-lg border p-4">
                        <div className="grid gap-1">
                            <Label htmlFor="confirmacion_turno">
                                Confirmación de turno
                            </Label>
                            <p className="text-sm text-muted-foreground">
                                Cuando alguien del equipo confirma un turno (o
                                lo carga desde el panel), el cliente recibe un
                                email con el servicio, el día, la hora y la
                                dirección. Sólo si dejó su email.
                            </p>
                        </div>
                        <Switch
                            id="confirmacion_turno"
                            checked={form.data.confirmacion_turno}
                            onCheckedChange={(activo) =>
                                form.setData('confirmacion_turno', activo)
                            }
                        />
                    </div>

                    <div>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            data-test="guardar-avisos-button"
                        >
                            Guardar
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

AjusteAvisos.layout = {
    breadcrumbs: [{ title: 'Avisos por email', href: rutaAvisos() }],
};
