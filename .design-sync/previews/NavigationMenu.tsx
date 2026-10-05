import {
    CalendarDays,
    Car,
    LayoutGrid,
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuList,
    cn,
    navigationMenuTriggerStyle,
} from 'alfa-ui';

const items = [
    { title: 'Dashboard', icon: LayoutGrid, activo: true },
    { title: 'Vehículos', icon: Car, activo: false },
    { title: 'Turnos', icon: CalendarDays, activo: false },
];

export const Cabecera = () => (
    <div className="flex h-16 w-[40rem] items-stretch border-b px-4">
        <NavigationMenu className="flex h-full items-stretch">
            <NavigationMenuList className="flex h-full items-stretch space-x-2">
                {items.map((item) => (
                    <NavigationMenuItem key={item.title} className="relative flex h-full items-center">
                        <a
                            href="#"
                            className={cn(
                                navigationMenuTriggerStyle(),
                                item.activo && 'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100',
                                'h-9 cursor-pointer px-3',
                            )}
                        >
                            <item.icon className="mr-2 h-4 w-4" />
                            {item.title}
                        </a>
                        {item.activo && <div className="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-black dark:bg-white" />}
                    </NavigationMenuItem>
                ))}
            </NavigationMenuList>
        </NavigationMenu>
    </div>
);
