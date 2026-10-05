import { Button, CarFront, Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle, History, Plus } from 'alfa-ui';

export const SinResultados = () => (
    <Empty className="w-[28rem] border">
        <EmptyHeader>
            <EmptyMedia variant="icon">
                <History />
            </EmptyMedia>
            <EmptyTitle>Sin movimientos</EmptyTitle>
            <EmptyDescription>No hay cambios registrados con estos filtros.</EmptyDescription>
        </EmptyHeader>
    </Empty>
);

export const ConAccion = () => (
    <Empty className="w-[28rem] border">
        <EmptyHeader>
            <EmptyMedia variant="icon">
                <CarFront />
            </EmptyMedia>
            <EmptyTitle>Todavía no cargaste vehículos</EmptyTitle>
            <EmptyDescription>Los que publiques aparecen en el catálogo del sitio.</EmptyDescription>
        </EmptyHeader>
        <EmptyContent>
            <Button>
                <Plus />
                Nuevo vehículo
            </Button>
        </EmptyContent>
    </Empty>
);
