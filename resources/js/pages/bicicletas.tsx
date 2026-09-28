import { Head } from '@inertiajs/react';
import { ProductoCatalogo } from '@/components/alfa/producto-catalogo';
import type { Producto } from '@/types';

export default function Bicicletas({ productos }: { productos: Producto[] }) {
    return (
        <>
            <Head title="Bicicletas">
                <meta
                    name="description"
                    content={`Bicicletas en Rivera, del rodado 12 al 29: ${productos.length} modelos con ficha técnica. El precio se cotiza por WhatsApp.`}
                />
            </Head>

            <section className="shell catalog-intro">
                <p className="eyebrow">Rodado 12 a 29</p>
                <h1>Bicicletas</h1>
                <p>
                    De la primera bici sin pedales a la MTB de aluminio. El
                    precio se cotiza por WhatsApp según rodado y color.
                </p>
            </section>

            <ProductoCatalogo productos={productos} />
        </>
    );
}
