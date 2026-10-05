import { Avatar, AvatarFallback, AvatarImage } from 'alfa-ui';

export const Iniciales = () => (
    <div className="flex items-center gap-3">
        <Avatar>
            <AvatarFallback>MC</AvatarFallback>
        </Avatar>
        <Avatar>
            <AvatarFallback>LF</AvatarFallback>
        </Avatar>
        <Avatar className="size-10">
            <AvatarImage src="" alt="Diego Rossi" />
            <AvatarFallback>DR</AvatarFallback>
        </Avatar>
    </div>
);

export const ConNombre = () => (
    <div className="flex items-center gap-2">
        <Avatar className="h-8 w-8 overflow-hidden rounded-lg">
            <AvatarFallback className="rounded-lg bg-neutral-200 text-black">CP</AvatarFallback>
        </Avatar>
        <div className="grid text-left text-sm leading-tight">
            <span className="truncate font-medium">Cristopher Paiva</span>
            <span className="truncate text-xs text-muted-foreground">admin@alfaautomotores.uy</span>
        </div>
    </div>
);
