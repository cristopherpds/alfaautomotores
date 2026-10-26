import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { useAvisosWhatsapp } from '@/hooks/use-avisos-whatsapp';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    /* El layout persiste entre visitas: los avisos siguen corriendo en
       cualquier página del panel. */
    const whatsapp = useAvisosWhatsapp();

    return (
        <AppShell variant="sidebar">
            <AppSidebar whatsapp={whatsapp} />
            <AppContent variant="sidebar" className="overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
