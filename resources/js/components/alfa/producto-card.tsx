import { Link } from '@inertiajs/react';
import { Photo } from '@/components/alfa/photo';
import { familiaLegible, precioLegible } from '@/lib/productos';
import { show } from '@/routes/productos';
import type { Producto } from '@/types';

/**
 * Tarjeta del catálogo de movilidad.
 *
 * Es la misma pieza que `VehicleCard` con dos diferencias que vienen del
 * producto y no del diseño: la foto llega recortada sobre blanco del catálogo
 * del proveedor —de ahí `card--producto`, que la encaja entera en vez de
 * recortarla—, y el pie muestra «Consultar precio» cuando no hay precio
 * publicado, que es el caso de todas las bicicletas.
 */
export function ProductoCard({ producto }: { producto: Producto }) {
    const sinPrecio = producto.precio === null;

    return (
        <Link href={show(producto.slug)} className="card card--producto">
            <div className="card__media">
                {producto.estado === 'sin_stock' && (
                    <span className="badge badge--vendido">Sin stock</span>
                )}

                {producto.estado === 'por_encargue' && (
                    <span className="badge badge--reservado">Por encargue</span>
                )}

                <Photo
                    src={producto.imagenes[0]}
                    alt={producto.nombre}
                    placeholder={producto.nombre}
                    detalle={familiaLegible(producto.familia)}
                />
            </div>

            <div className="card__body">
                <h3 className="card__title">{producto.nombre}</h3>
                <p className="card__meta">{producto.resumen}</p>

                <div className="card__foot">
                    <span
                        className={
                            sinPrecio ? 'card__consultar' : 'card__price'
                        }
                    >
                        {precioLegible(producto)}
                    </span>
                    <span className="card__cta">Ver ficha</span>
                </div>
            </div>
        </Link>
    );
}

export function ProductoGrid({ productos }: { productos: Producto[] }) {
    return (
        <div className="grid">
            {productos.map((producto) => (
                <ProductoCard key={producto.slug} producto={producto} />
            ))}
        </div>
    );
}
