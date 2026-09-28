import type { EstadoProducto, FamiliaProducto, Producto } from '@/types';

/**
 * Filtros y formato del catálogo de movilidad.
 *
 * Es el hermano de `lib/catalogo.ts`, que hace lo mismo para los autos. Va
 * aparte y no extendido porque los dos catálogos filtran por ejes distintos:
 * allá marca, año y kilometraje; acá familia, rodado y disponibilidad, y con
 * la mitad del catálogo sin precio.
 */

/**
 * Las etiquetas de los dos enums, del lado del cliente.
 *
 * Los valores viajan crudos desde `App\Enums\FamiliaProducto` y
 * `App\Enums\EstadoProducto`, igual que el estado del vehículo en
 * `lib/catalogo.ts`: el front los traduce y el servidor no manda texto.
 * Si se agrega un caso allá, el `Record` no compila hasta agregarlo acá.
 */
const FAMILIAS: Record<FamiliaProducto, string> = {
    moto: 'Motos eléctricas',
    scooter: 'Scooters',
    monopatin: 'Monopatines',
    hoverboard: 'Hoverboards',
    bici_electrica: 'Bicicletas eléctricas',
    triciclo: 'Triciclos',
    bicicleta: 'Bicicletas',
};

const ESTADOS: Record<EstadoProducto, string> = {
    publicado: 'Disponible',
    sin_stock: 'Sin stock',
    por_encargue: 'Por encargue',
};

export function familiaLegible(familia: FamiliaProducto): string {
    return FAMILIAS[familia];
}

export function estadoLegible(estado: EstadoProducto): string {
    return ESTADOS[estado];
}

export const ORDENES = [
    { value: 'recientes', label: 'Más recientes' },
    { value: 'precio-asc', label: 'Precio: menor a mayor' },
    { value: 'precio-desc', label: 'Precio: mayor a menor' },
] as const;

export type Orden = (typeof ORDENES)[number]['value'];

export type Filtros = {
    /** Valor del enum `FamiliaProducto`, o `Todo` para no filtrar. */
    familia: string;
    disponibilidad: string;
    rodado: string;
    precio: number;
    orden: Orden;
};

export type Opciones = {
    /** `[valor, etiqueta]`: el valor es del enum y la etiqueta lo que se ve. */
    familias: [string, string][];
    disponibilidades: [string, string][];
    rodados: string[];
    precioMin: number;
    precioMax: number;
    /** Falso cuando ningún producto de la sección publica precio. */
    hayPrecios: boolean;
};

/** Paso del deslizador de precio; también redondea los extremos. */
export const PASO = 1000;

const TODO = 'Todo';

const unicos = <T extends string>(valores: T[]): T[] =>
    Array.from(new Set(valores));

/** El valor de una fila de la ficha técnica, si el producto la trae. */
export function specDe(producto: Producto, etiqueta: string): string | null {
    const fila = producto.specs.find(
        ([clave]) => clave.toLowerCase() === etiqueta.toLowerCase(),
    );

    return fila ? fila[1] : null;
}

/**
 * Los rodados de un producto, como números sueltos.
 *
 * El proveedor escribe el rodado en prosa y de seis maneras distintas: «20»,
 * «20 y 24», «26, 27.5 y 29», «20 × 4.0». Tomados en crudo cada variante sería
 * un filtro aparte —la página llegó a mostrar trece chips, varios repetidos—,
 * así que se parte en números: un modelo que viene en tres rodados aparece
 * bajo los tres.
 *
 * Lo que va después de la «×» es el ancho de la cubierta, no un rodado.
 */
export function rodadosDe(producto: Producto): string[] {
    const valor = specDe(producto, 'Rodado');

    if (valor === null) {
        return [];
    }

    return Array.from(
        valor.split(/[x×]/i)[0].matchAll(/\d+(?:[.,]\d+)?/g),
        (coincidencia) => coincidencia[0].replace(',', '.'),
    );
}

/**
 * Opciones de los selectores, sacadas del catálogo real: si mañana entra una
 * familia nueva o un rodado nuevo, aparece solo en el filtro.
 */
export function opcionesDe(lista: Producto[]): Opciones {
    const precios = lista
        .map((p) => p.precio)
        .filter((precio): precio is number => precio !== null);

    return {
        familias: unicos(lista.map((p) => p.familia)).map(
            (familia): [string, string] => [familia, familiaLegible(familia)],
        ),
        disponibilidades: unicos(lista.map((p) => p.estado)).map(
            (estado): [string, string] => [estado, estadoLegible(estado)],
        ),
        /* El rodado sale de la ficha técnica y no de una columna: sólo lo
           traen las bicis, y escrito en prosa (ver `rodadosDe`). */
        rodados: unicos(lista.flatMap(rodadosDe)).sort(
            (a, b) => parseFloat(a) - parseFloat(b),
        ),
        precioMin: precios.length
            ? Math.floor(Math.min(...precios) / PASO) * PASO
            : 0,
        precioMax: precios.length
            ? Math.ceil(Math.max(...precios) / PASO) * PASO
            : 0,
        hayPrecios: precios.length > 0,
    };
}

/** Sin filtrar nada: el deslizador arranca en el precio más alto. */
export function filtrosIniciales(opciones: Opciones): Filtros {
    return {
        familia: TODO,
        disponibilidad: TODO,
        rodado: TODO,
        precio: opciones.precioMax,
        orden: 'recientes',
    };
}

export function filtrar(lista: Producto[], f: Filtros): Producto[] {
    const out = lista.filter(
        (p) =>
            (f.familia === TODO || p.familia === f.familia) &&
            (f.disponibilidad === TODO || p.estado === f.disponibilidad) &&
            (f.rodado === TODO || rodadosDe(p).includes(f.rodado)) &&
            /* Un producto sin precio no lo filtra el deslizador: se cotiza,
               así que no tiene por qué caerse del listado. */
            (p.precio === null || p.precio <= f.precio),
    );

    switch (f.orden) {
        case 'precio-asc':
            return ordenarPorPrecio(out, 1);
        case 'precio-desc':
            return ordenarPorPrecio(out, -1);
        default:
            // "Más recientes": el orden en que vino del catálogo.
            return out;
    }
}

/** Los que se cotizan van siempre al final, ordene como ordene. */
function ordenarPorPrecio(lista: Producto[], signo: number): Producto[] {
    return [...lista].sort((a, b) => {
        if (a.precio === null || b.precio === null) {
            return a.precio === b.precio ? 0 : a.precio === null ? 1 : -1;
        }

        return (a.precio - b.precio) * signo;
    });
}

/**
 * El precio como se imprime en la tarjeta y en la ficha.
 *
 * `fmtPrecio()` de `lib/alfa.ts` escribe el código de la moneda («UYU 33.900»),
 * que es lo que quiere el catálogo de autos porque ahí conviven dos monedas en
 * la misma grilla. Acá el precio es plata de mostrador y va con símbolo.
 */
export function precioLegible(producto: Producto): string {
    return producto.precio === null
        ? 'Consultar precio'
        : montoLegible(producto.precio, producto.moneda);
}

/** El mismo formato para un monto suelto: el tope del deslizador de precio. */
export function montoLegible(monto: number, moneda = 'UYU'): string {
    return `${moneda === 'USD' ? 'U$S' : '$'} ${monto.toLocaleString('es-UY')}`;
}

/** Consulta de WhatsApp ya redactada para un producto concreto. */
export function mensajeConsulta(producto: Producto): string {
    const precio = producto.precio
        ? ` (${precioLegible(producto)})`
        : '. ¿Qué precio tiene?';

    return `Hola Alfa Automotores, me interesa el ${producto.nombre} publicado en la web${precio}`;
}

/**
 * El tono de cada nombre de color que usa el catálogo, sin tildes y en
 * minúsculas, en masculino y femenino porque la semilla trae los dos
 * («Negro» y «Negra»). Los compuestos que no se arman de sus partes
 * («azul galaxia», «verde agua») van enteros.
 */
const TONOS: Record<string, string> = {
    negro: '#1c1c1e',
    negra: '#1c1c1e',
    blanco: '#ffffff',
    blanca: '#ffffff',
    gris: '#8e8e93',
    'gris oscuro': '#48484a',
    'gris mate': '#6e6e73',
    azul: '#1f4fd1',
    'azul galaxia': '#2a2f7a',
    'azul francia': '#2f5bd3',
    celeste: '#7cc4f0',
    turquesa: '#1fb5b0',
    rojo: '#d42b2b',
    roja: '#d42b2b',
    'roja oscura': '#8e1b1b',
    'rojo oscuro': '#8e1b1b',
    rosa: '#f08bb4',
    rosada: '#f08bb4',
    rosado: '#f08bb4',
    verde: '#2e9e4a',
    'verde agua': '#6fd6c0',
    amarillo: '#f5c518',
    amarilla: '#f5c518',
    naranja: '#f47c20',
    violeta: '#7b3fbf',
    lila: '#b89be0',
    marfil: '#f3ecd9',
    crema: '#f1e4c6',
};

/**
 * Los nombres de `TONOS` para las sugerencias del panel, con mayúscula
 * inicial. Se deja una sola forma por color: si existe la masculina
 * («Negro»), la femenina («Negra») no se sugiere, aunque se sigue
 * reconociendo si alguien la escribe.
 */
export const NOMBRES_DE_COLOR: string[] = Object.keys(TONOS)
    .filter((nombre) => {
        const masculino = nombre.replace(/a\b/g, 'o');

        return masculino === nombre || !(masculino in TONOS);
    })
    .map((nombre) => nombre.charAt(0).toUpperCase() + nombre.slice(1));

/**
 * Los tonos de un color del catálogo, para dibujar su muestra.
 *
 * Un color puede ser combinado: «Azul/Blanco/Amarillo» o «Negra y roja» dan
 * un tono por parte y la muestra se reparte entre todos. Devuelve null si
 * alguna parte no está en `TONOS`: ahí la ficha muestra sólo el nombre, que
 * es mejor que una muestra equivocada.
 */
export function tonosDe(color: string): string[] | null {
    const partes = color
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .split(/\s*\/\s*|\s+y\s+/)
        .map((parte) => parte.trim())
        .filter(Boolean);

    const tonos = partes.map((parte) => TONOS[parte]);

    return tonos.length > 0 && tonos.every(Boolean) ? tonos : null;
}

/**
 * El fondo CSS de una muestra: el tono liso, o una torta con una porción
 * igual por tono si el color es combinado.
 */
export function fondoDeMuestra(tonos: string[]): string {
    if (tonos.length === 1) {
        return tonos[0];
    }

    const porcion = 360 / tonos.length;
    const cortes = tonos.map(
        (tono, indice) =>
            `${tono} ${indice * porcion}deg ${(indice + 1) * porcion}deg`,
    );

    return `conic-gradient(${cortes.join(', ')})`;
}
