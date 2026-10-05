import { CalendarDays, CarFront, Droplets, Icon, Wrench } from 'alfa-ui';

export const Default = () => (
    <div className="flex items-center gap-4 text-muted-foreground">
        <Icon iconNode={CarFront} className="size-5" />
        <Icon iconNode={Wrench} className="size-5" />
        <Icon iconNode={Droplets} className="size-5" />
        <Icon iconNode={CalendarDays} className="size-5" />
    </div>
);

export const EnMenu = () => (
    <div className="grid w-56 gap-1 text-sm">
        {[
            { icon: CarFront, label: 'Vehículos' },
            { icon: CalendarDays, label: 'Turnos' },
            { icon: Wrench, label: 'Servicios del taller' },
        ].map((item) => (
            <span key={item.label} className="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-accent">
                <Icon iconNode={item.icon} className="size-4" />
                {item.label}
            </span>
        ))}
    </div>
);
