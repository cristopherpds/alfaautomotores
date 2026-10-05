import { ProductoCard } from 'alfa-ui';

const base = { desc: '', specs: [] as [string, string][], colores: [], imagenes: [], moneda: 'UYU' };

const moto = { ...base, slug: 'moto-electrica-max-350', nombre: 'Moto eléctrica MAX 350', familia: 'moto' as const, codigo: null, precio: 33900, estado: 'publicado' as const, resumen: '350 W · 30 km/h · 25–30 km · rodado 14' };
const monopatin = { ...base, slug: 'monopatin-electrico-kds-dc03', nombre: 'Monopatín eléctrico KDS-DC03', familia: 'monopatin' as const, codigo: 'KDS-DC03', precio: 8320, estado: 'sin_stock' as const, resumen: '100 W · 12 km/h · 8–12 km · batería 12V/4.5 Ah x 2' };
const bicicleta = { ...base, slug: 'bicicleta-infantil-sin-pedales', nombre: 'Bicicleta infantil sin pedales', familia: 'bicicleta' as const, codigo: 'K-697', precio: null, estado: 'por_encargue' as const, resumen: 'Rodado 12 · acero · rueda de espuma EVA' };

export const ConPrecio = () => (
    <div className="alfa" style={{ width: 340 }}>
        <ProductoCard producto={moto} />
    </div>
);

export const SinStock = () => (
    <div className="alfa" style={{ width: 340 }}>
        <ProductoCard producto={monopatin} />
    </div>
);

export const ConsultarPrecio = () => (
    <div className="alfa" style={{ width: 340 }}>
        <ProductoCard producto={bicicleta} />
    </div>
);
