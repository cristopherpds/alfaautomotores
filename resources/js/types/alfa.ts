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

/**
 * Una foto de la tira de «Nuestros clientes» de la portada, servida por
 * `App\Models\Entrega::paraLaTira()`.
 */
export type Entrega = {
    /** Ruta pública del archivo. */
    url: string;
    /** Fecha en ISO, para el `datetime` del `<time>`. */
    fecha: string;
    /** La fecha como se imprime sobre la foto: `14.11.25`. */
    etiqueta: string;
    /** La fecha en palabras, para el texto alternativo. */
    legible: string;
};

/** La fila que arma `Panel\EntregaController::index()`. */
export type ManagedEntrega = Entrega & { id: number };

/** Lo que comparten los servicios de los dos negocios en el sitio público. */
export type ServicioPublico = {
    slug: string;
    nombre: string;
    /** Minutos que lleva el trabajo: es el largo del turno. */
    duracion: number;
    duracionLegible: string;
    /** Sólo el lavadero tiene precio de lista; el taller cotiza. */
    precioLegible: string | null;
    descripcion: string;
    foto: string | null;
    /** Los trabajos mayores se cotizan en el taller: no se reservan online. */
    agendable: boolean;
};

/** Un servicio del taller, tal como lo manda `TallerController::index()`. */
export type ServicioTaller = ServicioPublico & {
    /** Valor del enum `AreaServicio`; `areaLabel` es lo que se muestra. */
    area: string;
    areaLabel: string;
};

/**
 * Un lavado, tal como lo manda `LavaderoController::index()`.
 *
 * Va sin área a propósito: los servicios del lavadero son tamaños de vehículo,
 * no especialidades, y lo que los diferencia es el precio.
 */
export type ServicioLavadero = ServicioPublico;

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

/** La fila que arma `Panel\ServicioController::toListItem()`. */
export type ManagedServicio = {
    id: number;
    slug: string;
    nombre: string;
    /** Valor del enum `Rubro`: de qué negocio es el servicio. */
    rubro: string;
    rubroLabel: string;
    /** Nulos en el lavadero, que no clasifica por área. */
    area: string | null;
    areaLabel: string | null;
    /** Minutos: es el largo del turno que genera este servicio. */
    duracion: number;
    duracionLegible: string;
    /** Sólo el lavadero tiene precio de lista. */
    precio: number | null;
    precioLegible: string | null;
    foto: string | null;
    activo: boolean;
    agendable: boolean;
    orden: number;
    turnos_count: number;
};

/** El servicio que consume el formulario de edición. */
export type ServicioEditable = ManagedServicio & { descripcion: string };

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
