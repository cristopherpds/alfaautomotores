import { Head, usePage } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { AvisosWhatsappAjustes } from '@/components/whatsapp/avisos-whatsapp-ajustes';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    // Sólo quien ve la bandeja tiene avisos que configurar.
    const { whatsapp } = usePage().props;

    return (
        <>
            <Head title="Apariencia" />

            <h1 className="sr-only">Apariencia</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Apariencia"
                    description="Elegí cómo se ve el panel en este navegador"
                />
                <AppearanceTabs />
            </div>

            {whatsapp && <AvisosWhatsappAjustes />}
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Apariencia',
            href: editAppearance(),
        },
    ],
};
