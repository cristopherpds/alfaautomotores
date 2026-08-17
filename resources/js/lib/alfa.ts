import type { Vehiculo } from '@/types';

/** Enlace a WhatsApp, opcionalmente con un mensaje ya redactado. */
export function whatsapp(numero: string, mensaje?: string): string {
    const base = `https://wa.me/${numero}`;

    return mensaje ? `${base}?text=${encodeURIComponent(mensaje)}` : base;
}

export function fmtPrecio(precio: number, moneda: string): string {
    return `${moneda} ${precio.toLocaleString('es-UY')}`;
}

export function fmtKm(km: number): string {
    return `${km.toLocaleString('es-UY')} km`;
}

export function titulo(vehiculo: Vehiculo): string {
    return [vehiculo.marca, vehiculo.modelo, vehiculo.version]
        .filter(Boolean)
        .join(' ');
}

/** Línea de resumen usada en las tarjetas: año · km · combustible. */
export function resumen(vehiculo: Vehiculo): string {
    return `${vehiculo.anio} · ${fmtKm(vehiculo.km)} · ${vehiculo.comb}`;
}
