import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    badges?: NavBadge[];
};

/** Un contador al costado del ítem del menú. Con 0 no se muestra. */
export type NavBadge = {
    valor: number;
    etiqueta: string;
    variant?: 'default' | 'secondary' | 'destructive' | 'outline';
};
