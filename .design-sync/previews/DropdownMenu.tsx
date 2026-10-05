import {
    Button,
    Copy,
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
    MoreHorizontal,
    Pencil,
    Trash2,
} from 'alfa-ui';

export const AccionesDeFila = () => (
    <div className="flex h-72 w-80 justify-end p-4">
        <DropdownMenu defaultOpen modal={false}>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" aria-label="Acciones">
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuLabel>Acciones</DropdownMenuLabel>
                <DropdownMenuGroup>
                    <DropdownMenuItem>
                        <Copy />
                        Copiar email
                    </DropdownMenuItem>
                    <DropdownMenuItem>
                        <Pencil />
                        Editar
                    </DropdownMenuItem>
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuItem variant="destructive">
                        <Trash2 />
                        Eliminar usuario
                    </DropdownMenuItem>
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
);
