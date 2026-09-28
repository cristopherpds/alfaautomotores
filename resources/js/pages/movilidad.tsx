import { Head } from '@inertiajs/react';
import { ProductoCatalogo } from '@/components/alfa/producto-catalogo';
import type { Producto } from '@/types';

export default function Movilidad({ productos }: { productos: Producto[] }) {
    return (
        <>
            <Head title="Movilidad eléctrica">
                <meta
                    name="description"
                    content={`Motos y scooters eléctricos, monopatines, hoverboards y bicicletas con pedaleo asistido en Rivera: ${productos.length} modelos con ficha técnica y precio en pesos.`}
                />
            </Head>

            <section className="shell catalog-intro">
                <p className="eyebrow">Motos, scooters y monopatines</p>
                <h1>Movilidad eléctrica</h1>
                <p>
                    Motos y scooters eléctricos, monopatines, hoverboards,
                    triciclos y bicicletas con pedaleo asistido. Precios en
                    pesos con IVA incluido.
                </p>
            </section>

            <ProductoCatalogo productos={productos} />
        </>
    );
}
