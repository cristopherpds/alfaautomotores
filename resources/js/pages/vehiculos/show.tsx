import { Head, Link } from '@inertiajs/react';
import { Photo } from '@/components/alfa/photo';
import { VehicleGrid } from '@/components/alfa/vehicle-card';
import { fmtKm, fmtPrecio, titulo, whatsapp } from '@/lib/alfa';
import { estadoLegible, mensajeConsulta } from '@/lib/catalogo';
import { catalogo } from '@/routes';
import type { SiteInfo, Vehiculo } from '@/types';

type Props = {
    site: SiteInfo;
    vehiculo: Vehiculo;
    similares: Vehiculo[];
};

/** Las tres fotos secundarias de la galería, todavía sin archivo cargado. */
const VISTAS = ['Interior', 'Trasera', 'Motor'];

export default function VehiculoShow({ site, vehiculo, similares }: Props) {
    const nombre = titulo(vehiculo);

    const specs: [string, string][] = [
        ['Año', String(vehiculo.anio)],
        ['Kilometraje', fmtKm(vehiculo.km)],
        ['Combustible', vehiculo.comb],
        ['Transmisión', vehiculo.trans],
        ['Tipo', vehiculo.tipo],
        ['Estado', estadoLegible(vehiculo.estado)],
    ];

    return (
        <>
            <Head title={`${nombre} ${vehiculo.anio}`}>
                <meta
                    name="description"
                    content={`${nombre} ${vehiculo.anio} · ${fmtKm(vehiculo.km)} · ${fmtPrecio(vehiculo.precio, vehiculo.moneda)}. ${vehiculo.desc}`}
                />
            </Head>

            <div className="shell">
                <Link href={catalogo()} className="back-link">
                    ← Volver al catálogo
                </Link>
            </div>

            <article className="shell detail">
                <div className="detail__gallery">
                    <div className="detail__frame">
                        <Photo
                            alt={nombre}
                            placeholder={nombre}
                            detalle={String(vehiculo.anio)}
                        />
                    </div>

                    <div className="detail__thumbs">
                        {VISTAS.map((vista) => (
                            <div className="detail__frame" key={vista}>
                                <Photo
                                    alt={`${nombre} — ${vista.toLowerCase()}`}
                                    placeholder={vista}
                                />
                            </div>
                        ))}
                    </div>
                </div>

                <div>
                    <p className="eyebrow">{estadoLegible(vehiculo.estado)}</p>
                    <h1>{nombre}</h1>
                    <p className="detail__summary">
                        {vehiculo.anio} · {fmtKm(vehiculo.km)} · {vehiculo.tipo}
                    </p>

                    <p className="detail__price">
                        <strong>
                            {fmtPrecio(vehiculo.precio, vehiculo.moneda)}
                        </strong>
                        <span>Permuta y financiación disponibles</span>
                    </p>

                    <dl className="specs">
                        {specs.map(([clave, valor]) => (
                            <div key={clave}>
                                <dt>{clave}</dt>
                                <dd>{valor}</dd>
                            </div>
                        ))}
                    </dl>

                    <p className="detail__desc">{vehiculo.desc}</p>

                    <div className="detail__actions">
                        <a
                            href={whatsapp(
                                site.whatsapp,
                                mensajeConsulta(vehiculo, nombre),
                            )}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="btn"
                            style={{ padding: '18px 30px' }}
                        >
                            <span className="dot" />
                            Consultar por WhatsApp
                        </a>

                        <a
                            href={`tel:${site.telefono}`}
                            className="btn btn--ghost"
                            style={{ padding: '18px 30px' }}
                        >
                            Llamar al local
                        </a>
                    </div>

                    <p className="detail__note">
                        {site.direccion}, {site.ciudad} · {site.horarios.corto}
                    </p>
                </div>
            </article>

            {similares.length > 0 && (
                <section className="shell related">
                    <h2 className="related__title">
                        También te puede interesar
                    </h2>
                    <VehicleGrid vehiculos={similares} />
                </section>
            )}
        </>
    );
}
