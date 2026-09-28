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
 * `estado` también admite `borrador` en la base: nunca sale al público.
 *
 * Los otros tres sí llegan y dicen cosas distintas: `publicado` está en el
 * local, `sin_stock` es lo que el catálogo del proveedor marca agotado y
 * `por_encargue` es el modelo que ya no publica pero sigue consiguiendo.
 */
export type EstadoProducto = 'publicado' | 'sin_stock' | 'por_encargue';

/** `bicicleta` es la única que va en `/bicicletas`; el resto, en `/movilidad`. */
export type FamiliaProducto =
    | 'moto'
    | 'scooter'
    | 'monopatin'
    | 'hoverboard'
    | 'bici_electrica'
    | 'triciclo'
    | 'bicicleta';

/** Un rodado del catálogo de movilidad, servido por `ProductoController`. */
export type Producto = {
    slug: string;
    nombre: string;
    /** En palabras, con `familiaLegible()` de `lib/productos.ts`. */
    familia: FamiliaProducto;
    /** Código del proveedor (ETB-122, KDS-DC49); no todos lo traen. */
    codigo: string | null;
    /** Nulo en las bicicletas, que se cotizan por WhatsApp. */
    precio: number | null;
    moneda: string;
    /** En palabras, con `estadoLegible()` de `lib/productos.ts`. */
    estado: EstadoProducto;
    /** La línea de la tarjeta: potencia · velocidad · autonomía. */
    resumen: string;
    desc: string;
    /** Ficha técnica como pares etiqueta/valor, en el orden del proveedor. */
    specs: [string, string][];
    colores: string[];
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
export type FotoGaleria = {
    id: number;
    url: string;
};

export type FotoVehiculo = FotoGaleria;

/** En el panel también se ven los borradores, que nunca salen al público. */
export type EstadoProductoAdmin = EstadoProducto | 'borrador';

/** Qué página pública lista el producto; el panel filtra por lo mismo. */
export type SeccionProducto = 'movilidad' | 'bicicletas';

/** Las opciones de los selectores de la ficha de un producto. */
export type OpcionesProducto = {
    familias: OpcionSelect[];
    estados: OpcionSelect[];
    monedas: OpcionSelect[];
};

/** La fila que arma `Panel\ProductoController::toListItem()`. */
export type ManagedProducto = {
    id: number;
    slug: string;
    nombre: string;
    familia: FamiliaProducto;
    codigo: string | null;
    precio: number | null;
    moneda: string;
    estado: EstadoProductoAdmin;
    portada: string | null;
    imagenes_count: number;
    can: { update: boolean; delete: boolean };
};

/** El producto que consume el formulario de edición. */
export type ProductoEditable = {
    id: number;
    slug: string;
    nombre: string;
    familia: FamiliaProducto;
    codigo: string | null;
    precio: number | null;
    moneda: string;
    estado: EstadoProductoAdmin;
    resumen: string;
    desc: string;
    specs: [string, string][];
    colores: string[];
    fotos: FotoGaleria[];
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

/** Qué le pasó al registro, con los valores de `App\Enums\AccionAuditoria`. */
export type AccionAuditoria = 'creado' | 'modificado' | 'eliminado';

/** Una entrada de la auditoría, tal como la arma `Panel\AuditoriaController`. */
export type EntradaAuditoria = {
    id: number;
    /** ISO 8601. */
    fecha: string;
    usuario: string;
    accion: AccionAuditoria;
    /** `vehiculo`, `foto_producto`, `turno`…: se traduce con `TIPOS_AUDITORIA`. */
    tipo: string;
    etiqueta: string;
    /** Campo → [antes, después]. En un alta «antes» es null; en una baja, «después». */
    cambios: Record<string, [unknown, unknown]>;
    ip: string | null;
};

/** Una página de un paginador de Laravel, con los campos que usa el panel. */
export type Paginado<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};
