import { Link, usePage } from '@inertiajs/react';
import {
    Bike,
    BookOpen,
    CalendarDays,
    Car,
    Contact,
    FolderGit2,
    GalleryHorizontal,
    History,
    Images,
    LayoutGrid,
    MessageCircle,
    Users,
    Wrench,
    Zap,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { indiceDeSeccion } from '@/lib/productos-panel';
import { dashboard } from '@/routes';
import { whatsapp as ajusteWhatsapp } from '@/routes/panel/ajustes';
import { index as auditoriaIndex } from '@/routes/panel/auditoria';
import { index as clientesIndex } from '@/routes/panel/clientes';
import { index as entregasIndex } from '@/routes/panel/entregas';
import { index as heroIndex } from '@/routes/panel/hero';
import { index as serviciosIndex } from '@/routes/panel/servicios';
import { index as turnosIndex } from '@/routes/panel/turnos';
import { index as vehiculosIndex } from '@/routes/panel/vehiculos';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const catalogoNavItems: NavItem[] = [
    {
        title: 'Vehículos',
        href: vehiculosIndex(),
        icon: Car,
    },
    /* Un solo ABM de productos; cada entrada abre su sección. */
    {
        title: 'Movilidad',
        href: indiceDeSeccion('movilidad'),
        icon: Zap,
    },
    {
        title: 'Bicicletas',
        href: indiceDeSeccion('bicicletas'),
        icon: Bike,
    },
    {
        title: 'Entregas',
        href: entregasIndex(),
        icon: Images,
    },
    {
        title: 'Portada',
        href: heroIndex(),
        icon: GalleryHorizontal,
    },
];

/* Una sola agenda y un solo ABM para los dos negocios: el turno filtra por
   rubro y el servicio lo lleva como columna. */
const agendaNavItems: NavItem[] = [
    {
        title: 'Turnos',
        href: turnosIndex(),
        icon: CalendarDays,
    },
    {
        title: 'Servicios',
        href: serviciosIndex(),
        icon: Wrench,
    },
    {
        title: 'Clientes',
        href: clientesIndex(),
        icon: Contact,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Usuarios',
        href: usersIndex(),
        icon: Users,
    },
    {
        title: 'Auditoría',
        href: auditoriaIndex(),
        icon: History,
    },
    {
        title: 'Botón de WhatsApp',
        href: ajusteWhatsapp(),
        icon: MessageCircle,
    },
];

/* Los links del starter kit de Laravel. Ocultos, no borrados: con `true`
   vuelven a aparecer al pie del sidebar. */
const mostrarEnlacesDelKit = false;

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;
    const isAdmin = auth.user?.role === 'admin';

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />

                <NavMain items={catalogoNavItems} label="Catálogo" />
                <NavMain items={agendaNavItems} label="Taller y lavadero" />

                {isAdmin && (
                    <NavMain items={adminNavItems} label="Administración" />
                )}
            </SidebarContent>

            <SidebarFooter>
                {mostrarEnlacesDelKit && (
                    <NavFooter items={footerNavItems} className="mt-auto" />
                )}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
