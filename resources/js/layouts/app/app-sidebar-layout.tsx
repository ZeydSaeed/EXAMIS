import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { NetworkStatus } from '@/components/network-status';
import { useDocumentLocale } from '@/hooks/use-document-locale';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    useDocumentLocale();

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <div id="main-content" className="flex flex-1 flex-col">
                    {children}
                </div>
            </AppContent>
            <NetworkStatus />
        </AppShell>
    );
}
