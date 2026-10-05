import {
    Badge,
    Button,
    DropdownMenu,
    DropdownMenuTrigger,
    MoreHorizontal,
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from 'alfa-ui';

const clientes = [
    { nombre: 'Martín Cabrera', celular: '099 412 336', email: 'martin.cabrera@gmail.com', turnos: 4, ultimo: '1 sep 2026', servicio: 'Service completo' },
    { nombre: 'Lucía Ferreira', celular: '098 776 210', email: null, turnos: 2, ultimo: '1 sep 2026', servicio: 'Pastillas de freno' },
    { nombre: 'Diego Rossi', celular: '091 305 884', email: 'drossi@adinet.com.uy', turnos: 7, ultimo: '2 sep 2026', servicio: 'Alineación y balanceo' },
    { nombre: 'Sofía Méndez', celular: '094 118 527', email: null, turnos: 1, ultimo: '28 ago 2026', servicio: 'Lavado completo' },
];

export const Clientes = () => (
    <div className="w-[48rem] rounded-xl border border-sidebar-border/70">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead className="px-4">Cliente</TableHead>
                    <TableHead>Contacto</TableHead>
                    <TableHead>Turnos</TableHead>
                    <TableHead>Último turno</TableHead>
                    <TableHead>
                        <span className="sr-only">Acciones</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {clientes.map((cliente) => (
                    <TableRow key={cliente.nombre}>
                        <TableCell className="px-4 font-medium">{cliente.nombre}</TableCell>
                        <TableCell>
                            {cliente.celular}
                            {cliente.email && <span className="block text-xs text-muted-foreground">{cliente.email}</span>}
                        </TableCell>
                        <TableCell className="tabular-nums">{cliente.turnos}</TableCell>
                        <TableCell>
                            {cliente.ultimo}
                            <span className="block text-xs text-muted-foreground">{cliente.servicio}</span>
                        </TableCell>
                        <TableCell className="text-right">
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" size="icon" aria-label="Acciones">
                                        <MoreHorizontal />
                                    </Button>
                                </DropdownMenuTrigger>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    </div>
);

export const ConEstados = () => (
    <div className="w-[40rem] rounded-xl border border-sidebar-border/70">
        <Table>
            <TableCaption>Turnos del jueves 2 de octubre</TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead className="px-4">Hora</TableHead>
                    <TableHead>Cliente</TableHead>
                    <TableHead>Servicio</TableHead>
                    <TableHead>Estado</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow>
                    <TableCell className="px-4 tabular-nums">08:30</TableCell>
                    <TableCell>Pablo Suárez</TableCell>
                    <TableCell>Cambio de aceite</TableCell>
                    <TableCell>
                        <Badge variant="secondary">Confirmado</Badge>
                    </TableCell>
                </TableRow>
                <TableRow>
                    <TableCell className="px-4 tabular-nums">09:00</TableCell>
                    <TableCell>Sofía Méndez</TableCell>
                    <TableCell>Lavado completo</TableCell>
                    <TableCell>
                        <Badge variant="outline">Pendiente</Badge>
                    </TableCell>
                </TableRow>
                <TableRow>
                    <TableCell className="px-4 tabular-nums">11:30</TableCell>
                    <TableCell className="text-muted-foreground line-through">Ana Pereira</TableCell>
                    <TableCell className="text-muted-foreground">Service completo</TableCell>
                    <TableCell>
                        <Badge variant="destructive">Cancelado</Badge>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
);
