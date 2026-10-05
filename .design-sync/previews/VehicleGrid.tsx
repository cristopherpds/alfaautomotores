import { VehicleGrid } from 'alfa-ui';

const base = { comb: 'Nafta', desc: '', imagenes: [], estado: 'publicado' as const, moneda: 'USD' };

const vehiculos = [
    { ...base, slug: 'strada-freedom-24', marca: 'Fiat', modelo: 'Strada', version: 'Freedom', anio: 2024, km: 18000, precio: 23900, trans: 'Manual', tipo: 'Pick-up' },
    { ...base, slug: 'onix-24', marca: 'Chevrolet', modelo: 'Onix', version: null, anio: 2024, km: 9500, precio: 21500, trans: 'Automática', tipo: 'Sedán' },
    { ...base, slug: 'tiggo2-23', marca: 'Chery', modelo: 'Tiggo 2', version: 'Pro', anio: 2023, km: 24000, precio: 20900, trans: 'Automática', tipo: 'SUV', estado: 'reservado' as const },
    { ...base, slug: 'cs55-plus-23', marca: 'Changan', modelo: 'CS55', version: 'Plus', anio: 2023, km: 31000, precio: 24500, trans: 'Automática', tipo: 'SUV' },
    { ...base, slug: 'gol-trend-19', marca: 'Volkswagen', modelo: 'Gol', version: 'Trend', anio: 2019, km: 64000, precio: 12900, trans: 'Manual', tipo: 'Hatchback', estado: 'vendido' as const },
    { ...base, slug: 'cronos-drive-22', marca: 'Fiat', modelo: 'Cronos', version: 'Drive', anio: 2022, km: 38000, precio: 17500, trans: 'Manual', tipo: 'Sedán' },
];

export const Catalogo = () => (
    <div className="alfa">
        <div className="shell">
            <VehicleGrid vehiculos={vehiculos} />
        </div>
    </div>
);
