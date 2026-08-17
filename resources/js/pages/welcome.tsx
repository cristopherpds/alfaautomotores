import { Head, Link } from '@inertiajs/react';
import { Photo } from '@/components/alfa/photo';
import { VehicleGrid } from '@/components/alfa/vehicle-card';
import { whatsapp } from '@/lib/alfa';
import { catalogo } from '@/routes';
import type { SiteInfo, Vehiculo } from '@/types';

type Props = {
    site: SiteInfo;
    destacados: Vehiculo[];
    totalStock: number;
};

const ARGUMENTOS = [
    ['Financiación', 'Planes en pesos, sin salir de Rivera.'],
    ['Recibimos tu usado', 'Tasación en el día como parte de pago.'],
    ['Revisados', 'Mecánica y documentación al día.'],
    ['Lun a Vie', '08:30 a 12:00 · 14:00 a 18:00'],
];

export default function Welcome({ site, destacados, totalStock }: Props) {
    const cotizar = whatsapp(
        site.whatsapp,
        'Hola, quiero cotizar mi auto con Alfa Automotores.',
    );

    return (
        <>
            <Head title="Autos usados en Rivera">
                <meta
                    name="description"
                    content="Vehículos seleccionados, revisados y listos para entregar en Rivera. Financiación disponible y recibimos tu usado como parte de pago."
                />
            </Head>

            <section className="shell hero">
                <div>
                    <p className="eyebrow">
                        {site.ciudad} · {site.pais}
                    </p>

                    <h1>
                        Tu elegís el destino,
                        <br />
                        nosotros ponemos
                        <br />
                        el vehículo.
                    </h1>

                    <p className="lede">
                        Vehículos seleccionados, revisados y listos para
                        entregar. Financiación disponible y recibimos tu usado
                        como parte de pago.
                    </p>

                    <div className="hero__actions">
                        <Link href={catalogo()} className="btn">
                            Ver catálogo
                        </Link>
                        <a
                            href={cotizar}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="btn btn--ghost"
                        >
                            Cotizá tu auto
                        </a>
                    </div>
                </div>

                <div className="hero__media">
                    <Photo
                        alt={`Salón de ${site.nombre} en ${site.ciudad}`}
                        placeholder={`Salón ${site.nombre}`}
                        detalle={`${site.ciudad} · ${site.pais}`}
                    />
                </div>
            </section>

            <div className="strip">
                <dl className="shell strip__grid">
                    {ARGUMENTOS.map(([titulo, detalle]) => (
                        <div className="strip__item" key={titulo}>
                            <dt>{titulo}</dt>
                            <dd>{detalle}</dd>
                        </div>
                    ))}
                </dl>
            </div>

            <section className="shell section">
                <div className="section__head">
                    <div>
                        <p className="eyebrow">Ingresos recientes</p>
                        <h2>Destacados de la semana</h2>
                    </div>

                    <Link href={catalogo()} className="section__link">
                        Ver los {totalStock} vehículos
                    </Link>
                </div>

                <VehicleGrid vehiculos={destacados} />
            </section>

            <section className="pitch">
                <div className="shell pitch__inner">
                    <div>
                        <p className="eyebrow">Tasación sin cargo</p>
                        <h2>¿Querés cotizar tu auto?</h2>
                        <p className="lede">
                            Cargá los datos de tu vehículo y te damos un rango
                            estimativo al instante. La oferta final se confirma
                            tras la inspección en el local.
                        </p>
                    </div>

                    <div className="pitch__actions">
                        <a
                            href={cotizar}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="btn btn--light"
                        >
                            Cotizar mi auto
                        </a>
                        <a
                            href={whatsapp(site.whatsapp)}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="btn btn--outline-dark"
                        >
                            Consultar por WhatsApp
                        </a>
                    </div>
                </div>
            </section>
        </>
    );
}
