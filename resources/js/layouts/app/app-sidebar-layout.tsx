import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { HeaderSlotsProvider } from '@/components/header-slots';
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
            <HeaderSlotsProvider>
                <AppSidebar />
                {/* The shell is pinned to the viewport: the window never scrolls and
                the header stays put; only an over-long page scrolls inside. */}
                <AppContent
                    variant="sidebar"
                    className="h-svh min-w-0 overflow-hidden md:h-[calc(100svh-1rem)]"
                >
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                    <div
                        id="main-content"
                        className="flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-contain"
                    >
                        {children}
                    </div>
                </AppContent>
                <NetworkStatus />
            </HeaderSlotsProvider>
        </AppShell>
    );
}
