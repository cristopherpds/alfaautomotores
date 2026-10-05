import { Button, Plus, Download, Trash2, MoreHorizontal, Loader2 } from 'alfa-ui';

export const Variants = () => (
    <div className="flex flex-wrap items-center gap-3">
        <Button>Guardar cambios</Button>
        <Button variant="secondary">Exportar</Button>
        <Button variant="outline">Cancelar</Button>
        <Button variant="ghost">Ver todos</Button>
        <Button variant="destructive">Eliminar</Button>
        <Button variant="link">Ir al catálogo</Button>
    </div>
);

export const Sizes = () => (
    <div className="flex flex-wrap items-center gap-3">
        <Button size="sm">Chico</Button>
        <Button>Mediano</Button>
        <Button size="lg">Grande</Button>
        <Button size="icon" variant="outline" aria-label="Más acciones">
            <MoreHorizontal />
        </Button>
    </div>
);

export const WithIcon = () => (
    <div className="flex flex-wrap items-center gap-3">
        <Button>
            <Plus />
            Nuevo vehículo
        </Button>
        <Button variant="secondary">
            <Download />
            Exportar CSV
        </Button>
        <Button variant="destructive" size="sm">
            <Trash2 />
            Borrar
        </Button>
    </div>
);

export const States = () => (
    <div className="flex flex-wrap items-center gap-3">
        <Button disabled>Guardar cambios</Button>
        <Button disabled>
            <Loader2 className="animate-spin" />
            Guardando…
        </Button>
        <Button variant="outline" aria-invalid>
            Revisar datos
        </Button>
    </div>
);
