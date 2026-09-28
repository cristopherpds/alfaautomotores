import { Head, Link } from '@inertiajs/react';
import { Galeria } from '@/components/alfa/galeria';
import { ProductoGrid } from '@/components/alfa/producto-card';
import { whatsapp } from '@/lib/alfa';
import {
    estadoLegible,
    familiaLegible,
    fondoDeMuestra,
    mensajeConsulta,
    precioLegible,
    tonosDe,
} from '@/lib/productos';
import { bicicletas, movilidad } from '@/routes';
import type { Producto, SiteInfo } from '@/types';

type Props = {
    site: SiteInfo;
    producto: Producto;
    similares: Producto[];
};

export default function ProductoShow({ site, producto, similares }: Props) {
    const esBicicleta = producto.familia === 'bicicleta';
    const sinPrecio = producto.precio === null;

    /* La ficha es una sola para las dos secciones, así que el enlace de vuelta
       sale de la familia y no de la ruta anterior. */
    const seccion = esBicicleta
        ? { href: bicicletas(), label: 'Volver a bicicletas' }
        : { href: movilidad(), label: 'Volver a movilidad' };

    return (
        <>
            <Head title={producto.nombre}>
                <meta
                    name="description"
                    content={`${producto.nombre} · ${producto.resumen}. ${sinPrecio ? 'Consultá el precio por WhatsApp.' : precioLegible(producto)}`}
                />
            </Head>

            <div className="shell">
                <Link href={seccion.href} className="back-link">
                    ← {seccion.label}
                </Link>
            </div>

            <article className="shell detail detail--producto">
                <Galeria
                    fotos={producto.imagenes}
                    nombre={producto.nombre}
                    detalle={familiaLegible(producto.familia)}
                />

                <div>
                    <p className="eyebrow">
                        {familiaLegible(producto.familia)}
                    </p>
                    <h1>{producto.nombre}</h1>
                    <p className="detail__summary">
                        {producto.resumen}
                        {producto.codigo && ` · Cód. ${producto.codigo}`}
                    </p>

                    <p className="detail__price">
                        <strong>{precioLegible(producto)}</strong>
                        <span>
                            {sinPrecio
                                ? 'Se cotiza por WhatsApp según rodado y color'
                                : `IVA incluido · ${estadoLegible(producto.estado)}`}
                        </span>
                    </p>

                    {producto.specs.length > 0 && (
                        <dl className="specs">
                            {producto.specs.map(([clave, valor]) => (
                                <div key={clave}>
                                    <dt>{clave}</dt>
                                    <dd>{valor}</dd>
                                </div>
                            ))}
                        </dl>
                    )}

                    {producto.desc && (
                        <p className="detail__desc">{producto.desc}</p>
                    )}

                    {producto.colores.length > 0 && (
                        <div className="detail__colores">
                            <p className="field-label">Colores</p>
                            <ul className="detail__muestras">
                                {producto.colores.map((color) => {
                                    const tonos = tonosDe(color);

                                    return (
                                        <li key={color}>
                                            {tonos && (
                                                <span
                                                    className="detail__muestra"
                                                    style={{
                                                        background:
                                                            fondoDeMuestra(
                                                                tonos,
                                                            ),
                                                    }}
                                                    aria-hidden="true"
                                                />
                                            )}
                                            {color}
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    )}

                    <div className="detail__actions">
                        <a
                            href={whatsapp(
                                site.whatsapp,
                                mensajeConsulta(producto),
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
                        Otros modelos de la sección
                    </h2>
                    <ProductoGrid productos={similares} />
                </section>
            )}
        </>
    );
}
