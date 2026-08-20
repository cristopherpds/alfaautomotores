import { Link } from '@inertiajs/react';
import { Photo } from '@/components/alfa/photo';
import { fmtPrecio, resumen, titulo } from '@/lib/alfa';
import { show } from '@/routes/vehiculos';
import type { Vehiculo } from '@/types';

export function VehicleCard({ vehiculo }: { vehiculo: Vehiculo }) {
    const nombre = titulo(vehiculo);

    return (
        <Link href={show(vehiculo.slug)} className="card">
            <div className="card__media">
                {vehiculo.estado === 'reservado' && (
                    <span className="badge badge--reservado">Reservado</span>
                )}

                {vehiculo.estado === 'vendido' && (
                    <span className="badge badge--vendido">Vendido</span>
                )}

                <Photo
                    src={vehiculo.imagenes[0]}
                    alt={nombre}
                    placeholder={nombre}
                    detalle={String(vehiculo.anio)}
                />
            </div>

            <div className="card__body">
                <h3 className="card__title">{nombre}</h3>
                <p className="card__meta">{resumen(vehiculo)}</p>

                <div className="card__foot">
                    <span className="card__price">
                        {fmtPrecio(vehiculo.precio, vehiculo.moneda)}
                    </span>
                    <span className="card__cta">Ver ficha</span>
                </div>
            </div>
        </Link>
    );
}

export function VehicleGrid({ vehiculos }: { vehiculos: Vehiculo[] }) {
    return (
        <div className="grid">
            {vehiculos.map((vehiculo) => (
                <VehicleCard key={vehiculo.slug} vehiculo={vehiculo} />
            ))}
        </div>
    );
}
