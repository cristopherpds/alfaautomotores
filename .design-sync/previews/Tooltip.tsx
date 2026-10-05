import { Button, Info, Tooltip, TooltipContent, TooltipTrigger } from 'alfa-ui';

export const Abierto = () => (
    <div className="flex h-40 w-72 items-end justify-center">
        <Tooltip defaultOpen>
            <TooltipTrigger asChild>
                <Button variant="outline" size="icon" aria-label="Qué significa">
                    <Info />
                </Button>
            </TooltipTrigger>
            <TooltipContent>Reservas de la web que todavía no se confirmaron</TooltipContent>
        </Tooltip>
    </div>
);
