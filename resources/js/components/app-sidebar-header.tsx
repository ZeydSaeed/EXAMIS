import { Breadcrumbs } from '@/components/breadcrumbs';
import { LanguageSwitcher } from '@/components/language-switcher';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="no-print flex h-16 shrink-0 items-center gap-2 border-b bg-[var(--examis-sage)] px-6 text-white transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4 [&_[data-slot=breadcrumb-list]]:text-white/80 [&_[data-slot=breadcrumb-page]]:text-white [&_[data-slot=breadcrumb-separator]]:text-white/50 [&_a]:text-white/80 [&_a:hover]:text-white">
            <div className="flex min-w-0 flex-1 items-center gap-2">
                <SidebarTrigger className="-ms-1 text-white hover:bg-white/10 hover:text-white" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <LanguageSwitcher className="text-white hover:bg-white/10 hover:text-white" />
        </header>
    );
}
