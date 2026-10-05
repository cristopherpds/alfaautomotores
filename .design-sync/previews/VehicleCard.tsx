import { VehicleCard } from 'alfa-ui';

const strada = {
    slug: 'strada-freedom-24',
    marca: 'Fiat',
    modelo: 'Strada',
    version: 'Freedom',
    anio: 2024,
    km: 18000,
    precio: 23900,
    moneda: 'USD',
    comb: 'Nafta',
    trans: 'Manual',
    tipo: 'Pick-up',
    estado: 'publicado' as const,
    desc: 'Cabina doble, único dueño, service oficial al día.',
    imagenes: [],
};

const tiggo = {
    ...strada,
    slug: 'tiggo2-23',
    marca: 'Chery',
    modelo: 'Tiggo 2',
    version: 'Pro',
    anio: 2023,
    km: 24000,
    precio: 20900,
    trans: 'Automática',
    tipo: 'SUV',
    estado: 'reservado' as const,
};

const onix = {
    ...strada,
    slug: 'onix-24',
    marca: 'Chevrolet',
    modelo: 'Onix',
    version: null,
    anio: 2024,
    km: 9500,
    precio: 21500,
    trans: 'Automática',
    tipo: 'Sedán',
    estado: 'vendido' as const,
};

export const Publicado = () => (
    <div className="alfa" style={{ width: 340 }}>
        <VehicleCard vehiculo={strada} />
    </div>
);

export const Reservado = () => (
    <div className="alfa" style={{ width: 340 }}>
        <VehicleCard vehiculo={tiggo} />
    </div>
);

export const Vendido = () => (
    <div className="alfa" style={{ width: 340 }}>
        <VehicleCard vehiculo={onix} />
    </div>
);
