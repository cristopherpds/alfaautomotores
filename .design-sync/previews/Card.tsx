import {
    Badge,
    Button,
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
    Clock,
} from 'alfa-ui';

const turnos = [
    { hora: '08:30', cliente: 'Martín Cabrera', servicio: 'Service completo', estado: 'Confirmado', pendiente: false },
    { hora: '10:00', cliente: 'Lucía Ferreira', servicio: 'Pastillas de freno', estado: 'Pendiente', pendiente: true },
    { hora: '14:30', cliente: 'Diego Rossi', servicio: 'Alineación y balanceo', estado: 'Confirmado', pendiente: false },
];

export const TurnosDeHoy = () => (
    <Card className="w-96">
        <CardHeader>
            <CardTitle>Taller</CardTitle>
            <CardDescription>3 turnos hoy</CardDescription>
        </CardHeader>
        <CardContent>
            <ul className="grid gap-2 text-sm">
                {turnos.map((turno) => (
                    <li key={turno.hora} className="flex items-center gap-3">
                        <span className="w-12 font-medium tabular-nums">{turno.hora}</span>
                        <span className="min-w-0 flex-1 truncate">
                            {turno.cliente}
                            <span className="block truncate text-xs text-muted-foreground">{turno.servicio}</span>
                        </span>
                        <Badge variant={turno.pendiente ? 'outline' : 'secondary'}>{turno.estado}</Badge>
                    </li>
                ))}
            </ul>
        </CardContent>
    </Card>
);

export const Metrica = () => (
    <div className="grid w-[36rem] grid-cols-3 gap-4">
        {[
            { label: 'Vehículos publicados', valor: '24', nota: '3 reservados' },
            { label: 'Turnos esta semana', valor: '41' },
            { label: 'Consultas por WhatsApp', valor: '128', nota: '+12% vs. mes pasado' },
        ].map((m) => (
            <Card key={m.label} className="gap-1 py-4">
                <CardContent className="grid gap-1 px-4">
                    <p className="text-sm text-muted-foreground">{m.label}</p>
                    <p className="text-3xl font-semibold">{m.valor}</p>
                    {m.nota && <p className="text-xs text-muted-foreground">{m.nota}</p>}
                </CardContent>
            </Card>
        ))}
    </div>
);

export const ConAccionYPie = () => (
    <Card className="w-96">
        <CardHeader>
            <CardTitle className="flex items-center gap-2">
                <Clock className="size-4" />
                Pendientes de confirmar
                <Badge variant="outline" className="ml-auto">2</Badge>
            </CardTitle>
            <CardDescription>Reservas de la web que todavía no se confirmaron por WhatsApp.</CardDescription>
        </CardHeader>
        <CardContent className="grid gap-2 text-sm">
            <p>
                <span className="font-medium">Sofía Méndez</span> · Lavado completo
                <span className="block text-xs text-muted-foreground">Lavadero · jue 2 oct, 09:00</span>
            </p>
            <p>
                <span className="font-medium">Pablo Suárez</span> · Cambio de aceite
                <span className="block text-xs text-muted-foreground">Taller · jue 2 oct, 11:30</span>
            </p>
        </CardContent>
        <CardFooter className="justify-end gap-2">
            <Button variant="ghost" size="sm">Ver agenda</Button>
            <Button size="sm">Confirmar todos</Button>
        </CardFooter>
    </Card>
);
