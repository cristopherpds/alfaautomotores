import { fmtPrecio } from '@/lib/alfa';
import type { Vehiculo } from '@/types';

export const ORDENES = [
    { value: 'recientes', label: 'Más recientes' },
    { value: 'precio-asc', label: 'Precio: menor a mayor' },
    { value: 'precio-desc', label: 'Precio: mayor a menor' },
    { value: 'anio-desc', label: 'Año: más nuevo' },
] as const;

export type Orden = (typeof ORDENES)[number]['value'];

export type Filtros = {
    marca: string;
    tipo: string;
    comb: string;
    anio: string;
    precio: number;
    orden: Orden;
};

/**
 * Opciones de los selectores y extremos del rango de precio. Salen del stock
 * real: si mañana entra una marca nueva, aparece sola en el filtro.
 */
export type Opciones = {
    marcas: string[];
    tipos: string[];
    combustibles: string[];
    anios: string[];
    precioMin: number;
    precioMax: number;
};

/** Paso del deslizador de precio; también redondea los extremos. */
export const PASO = 500;

const unicos = (valores: string[]) => Array.from(new Set(valores)).sort();

export function opcionesDe(lista: Vehiculo[]): Opciones {
    const precios = lista.map((v) => v.precio);

    return {
        marcas: ['Todas', ...unicos(lista.map((v) => v.marca))],
        tipos: ['Todos', ...unicos(lista.map((v) => v.tipo))],
        combustibles: ['Todos', ...unicos(lista.map((v) => v.comb))],
        // De más nuevo a más viejo: el filtro es "año desde".
        anios: ['Todos', ...unicos(lista.map((v) => String(v.anio))).reverse()],
        precioMin: precios.length
            ? Math.floor(Math.min(...precios) / PASO) * PASO
            : 0,
        precioMax: precios.length
            ? Math.ceil(Math.max(...precios) / PASO) * PASO
            : 0,
    };
}

/** Sin filtrar nada: el deslizador arranca en el precio más alto del stock. */
export function filtrosIniciales(opciones: Opciones): Filtros {
    return {
        marca: 'Todas',
        tipo: 'Todos',
        comb: 'Todos',
        anio: 'Todos',
        precio: opciones.precioMax,
        orden: 'recientes',
    };
}

export function filtrar(lista: Vehiculo[], f: Filtros): Vehiculo[] {
    const out = lista.filter(
        (v) =>
            (f.marca === 'Todas' || v.marca === f.marca) &&
            (f.tipo === 'Todos' || v.tipo === f.tipo) &&
            (f.comb === 'Todos' || v.comb === f.comb) &&
            (f.anio === 'Todos' || v.anio >= Number(f.anio)) &&
            v.precio <= f.precio,
    );

    switch (f.orden) {
        case 'precio-asc':
            return out.sort((a, b) => a.precio - b.precio);
        case 'precio-desc':
            return out.sort((a, b) => b.precio - a.precio);
        case 'anio-desc':
            return out.sort((a, b) => b.anio - a.anio);
        default:
            // "Más recientes": el orden en que vino del stock.
            return out;
    }
}

/** `publicado` es jerga interna: de cara al cliente el auto está disponible. */
export function estadoLegible(estado: Vehiculo['estado']): string {
    switch (estado) {
        case 'reservado':
            return 'Reservado';
        case 'vendido':
            return 'Vendido';
        default:
            return 'Disponible';
    }
}

/** Consulta de WhatsApp ya redactada para un vehículo concreto. */
export function mensajeConsulta(vehiculo: Vehiculo, nombre: string): string {
    return `Hola Alfa Automotores, me interesa el ${nombre} ${vehiculo.anio} publicado en la web (${fmtPrecio(vehiculo.precio, vehiculo.moneda)}). ¿Sigue disponible?`;
}
