import { Breadcrumbs } from '@/components/breadcrumbs';
import { CampanaNotificaciones } from '@/components/campana-notificaciones';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { Notificaciones } from '@/hooks/use-notificaciones';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
    notificaciones,
}: {
    breadcrumbs?: BreadcrumbItemType[];
    notificaciones?: Notificaciones;
}) {
    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            {notificaciones && (
                <CampanaNotificaciones notificaciones={notificaciones} />
            )}
        </header>
    );
}
