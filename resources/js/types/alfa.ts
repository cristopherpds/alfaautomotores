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
    /** Carrocería: Hatchback, Sedán, SUV, Pick-up o Utilitario. */
    tipo: string;
    estado: EstadoVehiculo;
    desc: string;
    /** URLs de la galería, de la portada a la última. */
    imagenes: string[];
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

/** En el panel también se ven los borradores, que nunca salen al público. */
export type EstadoVehiculoAdmin = EstadoVehiculo | 'borrador';

/** Una opción de `<Select>`, servida por los enums de PHP. */
export type OpcionSelect = {
    value: string;
    label: string;
    description?: string;
};

/** Las opciones de todos los selectores de la ficha. */
export type OpcionesVehiculo = {
    estados: OpcionSelect[];
    tipos: OpcionSelect[];
    combustibles: OpcionSelect[];
    transmisiones: OpcionSelect[];
    monedas: OpcionSelect[];
};

/** La fila que arma `Panel\VehiculoController::toListItem()`. */
export type ManagedVehiculo = {
    id: number;
    slug: string;
    titulo: string;
    tipo: string;
    anio: number;
    km: number;
    precio: number;
    moneda: string;
    estado: EstadoVehiculoAdmin;
    destacado: boolean;
    /** Falso para los borradores: no llegan al público, no van a la portada. */
    destacable: boolean;
    portada: string | null;
    imagenes_count: number;
    can: { update: boolean; delete: boolean };
};

/** Una foto de la galería, tal como la manda el panel. */
export type FotoVehiculo = {
    id: number;
    url: string;
};

/** El vehículo que consume el formulario de edición. */
export type VehiculoEditable = {
    id: number;
    slug: string;
    titulo: string;
    marca: string;
    modelo: string;
    version: string | null;
    anio: number;
    km: number;
    precio: number;
    moneda: string;
    comb: string;
    trans: string;
    tipo: string;
    estado: EstadoVehiculoAdmin;
    destacado: boolean;
    destacable: boolean;
    desc: string;
    fotos: FotoVehiculo[];
};
