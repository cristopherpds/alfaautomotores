import { Head, Link } from '@inertiajs/react';
import { Entregas } from '@/components/alfa/entregas';
import { Hero } from '@/components/alfa/hero';
import { VehicleGrid } from '@/components/alfa/vehicle-card';
import { whatsapp } from '@/lib/alfa';
import { catalogo } from '@/routes';
import type { Entrega, HeroSlide, SiteInfo, Vehiculo } from '@/types';

type Props = {
    site: SiteInfo;
    slides: HeroSlide[];
    destacados: Vehiculo[];
    totalStock: number;
    entregas: Entrega[];
};

const ARGUMENTOS = [
    ['Financiación', 'Planes en pesos, sin salir de Rivera.'],
    ['Recibimos tu usado', 'Tasación en el día como parte de pago.'],
    ['Revisados', 'Mecánica y documentación al día.'],
];

export default function Welcome({
    site,
    slides,
    destacados,
    totalStock,
    entregas,
}: Props) {
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

            <Hero slides={slides} />

            <div className="strip">
                <dl className="shell strip__grid">
                    {ARGUMENTOS.map(([titulo, detalle]) => (
                        <div className="strip__item" key={titulo}>
                            <dt>{titulo}</dt>
                            <dd>{detalle}</dd>
                        </div>
                    ))}

                    {/* Los horarios salen de `config/alfa.php`, igual que en el
                        pie: acá no se repite el texto a mano. */}
                    <div className="strip__item">
                        <dt>Horarios</dt>
                        <dd>
                            Lunes a viernes
                            <br />
                            {site.horarios.semana}
                            <br />
                            Sábados
                            <br />
                            {site.horarios.sabado}
                        </dd>
                    </div>
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

            <Entregas entregas={entregas} />

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
