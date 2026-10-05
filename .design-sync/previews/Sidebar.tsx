import {
    Bike,
    CalendarDays,
    Car,
    Contact,
    History,
    Images,
    LayoutGrid,
    MessageCircle,
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
    SidebarTrigger,
    Users,
    Wrench,
    Zap,
} from 'alfa-ui';

const grupos = [
    { label: 'Inicio', items: [{ title: 'Dashboard', icon: LayoutGrid, activo: true }] },
    {
        label: 'Catálogo',
        items: [
            { title: 'Vehículos', icon: Car },
            { title: 'Movilidad', icon: Zap },
            { title: 'Bicicletas', icon: Bike },
            { title: 'Entregas', icon: Images },
        ],
    },
    {
        label: 'Agenda',
        items: [
            { title: 'Turnos', icon: CalendarDays },
            { title: 'Servicios', icon: Wrench },
            { title: 'Clientes', icon: Contact },
        ],
    },
    {
        label: 'Administración',
        items: [
            { title: 'Usuarios', icon: Users },
            { title: 'Auditoría', icon: History },
            { title: 'Botón de WhatsApp', icon: MessageCircle },
        ],
    },
];

export const Panel = () => (
    <SidebarProvider defaultOpen>
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg">
                            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                                <Car className="size-5" />
                            </div>
                            <div className="ml-1 grid flex-1 text-left text-sm">
                                <span className="truncate leading-tight font-semibold">Alfa Automotores</span>
                            </div>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                {grupos.map((grupo) => (
                    <SidebarGroup key={grupo.label} className="px-2 py-0">
                        <SidebarGroupLabel>{grupo.label}</SidebarGroupLabel>
                        <SidebarMenu>
                            {grupo.items.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton isActive={'activo' in item} tooltip={{ children: item.title }}>
                                        <item.icon />
                                        <span>{item.title}</span>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                ))}
            </SidebarContent>
        </Sidebar>
        <SidebarInset>
            <header className="flex h-16 shrink-0 items-center gap-2 border-b px-4">
                <SidebarTrigger />
                <span className="text-sm font-medium">Dashboard</span>
            </header>
            <div className="grid gap-4 p-4 md:grid-cols-3">
                <div className="aspect-video rounded-xl bg-muted/50" />
                <div className="aspect-video rounded-xl bg-muted/50" />
                <div className="aspect-video rounded-xl bg-muted/50" />
            </div>
        </SidebarInset>
    </SidebarProvider>
);
