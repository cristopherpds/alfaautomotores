/** `estado` también admite `borrador` en la base: nunca sale al público. */
export type EstadoVehiculo = 'publicado' | 'reservado' | 'vendido';

export type Vehiculo = {
    slug: string;
    marca: string;
    modelo: string;
    version: string | null;
    anio: number;
    km: number;
    precio: number;
    moneda: string;
    comb: string;
    trans: string;
    /** Carrocería: Hatchback, Sedán, SUV o Pick-up. */
    tipo: string;
    estado: EstadoVehiculo;
    desc: string;
};

/** Datos del local, servidos desde `config/alfa.php`. */
export type SiteInfo = {
    nombre: string;
    ciudad: string;
    pais: string;
    direccion: string;
    codigoPostal: string;
    horarios: {
        semana: string;
        sabado: string;
        corto: string;
    };
    instagram: string;
    /** Número en formato internacional, sin "+" ni separadores. */
    whatsapp: string;
    telefono: string;
};
