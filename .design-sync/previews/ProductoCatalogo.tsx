import { ProductoCatalogo } from 'alfa-ui';

const base = { desc: '', specs: [] as [string, string][], colores: [], imagenes: [], moneda: 'UYU' };

const productos = [
    { ...base, slug: 'moto-electrica-max-350', nombre: 'Moto eléctrica MAX 350', familia: 'moto' as const, codigo: null, precio: 33900, estado: 'publicado' as const, resumen: '350 W · 30 km/h · 25–30 km · rodado 14' },
    { ...base, slug: 'scooter-electrico-enjoy-k-hemei', nombre: 'Scooter eléctrico Enjoy K-Hemei', familia: 'scooter' as const, codigo: 'SCEJ-800W', precio: 51900, estado: 'publicado' as const, resumen: '800 W · 35–55 km/h · 20–40 km · batería 48V 20H' },
    { ...base, slug: 'monopatin-electrico-kds-dc03', nombre: 'Monopatín eléctrico KDS-DC03', familia: 'monopatin' as const, codigo: 'KDS-DC03', precio: 8320, estado: 'sin_stock' as const, resumen: '100 W · 12 km/h · 8–12 km · batería 12V/4.5 Ah x 2' },
    { ...base, slug: 'bici-electrica-queen-rodado-26', nombre: 'Bici eléctrica Queen rodado 26', familia: 'bici_electrica' as const, codigo: 'ETC01', precio: 31900, estado: 'publicado' as const, resumen: '250 W · 15–20 km · rodado 26' },
    { ...base, slug: 'triciclo-electrico-250-w', nombre: 'Triciclo eléctrico 250 W', familia: 'triciclo' as const, codigo: 'TR250W', precio: 45900, estado: 'por_encargue' as const, resumen: '250 W · 36V/12A · 15–25 km' },
    { ...base, slug: 'moto-electrica-city-500', nombre: 'Moto eléctrica City 500', familia: 'moto' as const, codigo: null, precio: 42900, estado: 'publicado' as const, resumen: '500 W · 45 km/h · 40 km · rodado 12' },
];

export const Movilidad = () => (
    <div className="alfa">
        <div className="shell">
            <ProductoCatalogo productos={productos} />
        </div>
    </div>
);
