import { Form, Head, Link } from '@inertiajs/react';
import ClienteController from '@/actions/App/Http/Controllers/Panel/ClienteController';
import ClienteFormFields from '@/components/cliente-form-fields';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/panel/clientes';

export default function CreateCliente() {
    return (
        <>
            <Head title="Nuevo cliente" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Nuevo cliente"
                    description="Para los que llaman o pasan sin reservar. Los que reservan se cargan solos."
                />

                <Form
                    {...ClienteController.store.form()}
                    className="max-w-3xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <ClienteFormFields errors={errors} />

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="store-cliente-button"
                                >
                                    Agregar cliente
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

CreateCliente.layout = {
    breadcrumbs: [
        { title: 'Clientes', href: index() },
        { title: 'Nuevo cliente', href: create() },
    ],
};
