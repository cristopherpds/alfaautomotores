import { ProductoGrid } from 'alfa-ui';

const base = { desc: '', specs: [] as [string, string][], colores: [], imagenes: [], moneda: 'UYU', estado: 'publicado' as const };

const productos = [
    { ...base, slug: 'moto-electrica-max-350', nombre: 'Moto eléctrica MAX 350', familia: 'moto' as const, codigo: null, precio: 33900, resumen: '350 W · 30 km/h · 25–30 km · rodado 14' },
    { ...base, slug: 'scooter-electrico-enjoy-k-hemei', nombre: 'Scooter eléctrico Enjoy K-Hemei', familia: 'scooter' as const, codigo: 'SCEJ-800W', precio: 51900, resumen: '800 W · 35–55 km/h · 20–40 km · batería 48V 20H' },
    { ...base, slug: 'bici-electrica-queen-rodado-26', nombre: 'Bici eléctrica Queen rodado 26', familia: 'bici_electrica' as const, codigo: 'ETC01', precio: 31900, resumen: '250 W · 15–20 km · rodado 26' },
];

export const Movilidad = () => (
    <div className="alfa">
        <div className="shell">
            <ProductoGrid productos={productos} />
        </div>
    </div>
);
