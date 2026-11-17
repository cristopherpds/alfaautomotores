import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export function NavMain({
    items = [],
    label = 'General',
}: {
    items: NavItem[];
    label?: string;
}) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => {
                    const badges = (item.badges ?? []).filter(
                        (badge) => badge.valor > 0,
                    );

                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentUrl(item.href)}
                                tooltip={{ children: item.title }}
                            >
                                <Link href={item.href} prefetch>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                            {badges.length > 0 && (
                                <SidebarMenuBadge className="gap-1">
                                    {badges.map((badge) => (
                                        <Badge
                                            key={badge.etiqueta}
                                            variant={badge.variant}
                                            className="h-5 min-w-5 px-1 tabular-nums"
                                            title={badge.etiqueta}
                                            aria-label={`${badge.valor} ${badge.etiqueta}`}
                                        >
                                            {badge.valor > 99
                                                ? '99+'
                                                : badge.valor}
                                        </Badge>
                                    ))}
                                </SidebarMenuBadge>
                            )}
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
