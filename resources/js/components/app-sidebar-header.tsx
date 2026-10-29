import { Breadcrumbs } from '@/components/breadcrumbs';
import { ExamisMark } from '@/components/examis/examis-mark';
import { HeaderSlotTarget } from '@/components/header-slots';
import { HistoryNav } from '@/components/history-nav';
import { LanguageSwitcher } from '@/components/language-switcher';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="no-print grid h-16 shrink-0 grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2 border-b bg-[var(--examis-green)] px-3 text-white transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 sm:gap-3 sm:px-6 md:px-4 [&_[data-slot=breadcrumb-list]]:text-white/80 [&_[data-slot=breadcrumb-page]]:text-white [&_[data-slot=breadcrumb-separator]]:text-white/50 [&_a]:text-white/80 [&_a:hover]:text-white">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ms-1 text-white hover:bg-white/10 hover:text-white" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
                <HeaderSlotTarget
                    name="start"
                    className="flex min-w-0 items-center gap-2 empty:hidden"
                />
            </div>
            <HeaderSlotTarget
                name="center"
                className="flex min-w-0 justify-center empty:hidden"
            />
            <div className="col-start-3 flex min-w-0 items-center justify-end gap-2">
                <HeaderSlotTarget
                    name="end"
                    className="flex shrink-0 items-center gap-2 empty:hidden"
                />
                <LanguageSwitcher className="text-white hover:bg-white/10 hover:text-white" />
                <div className="hidden sm:block">
                    <HistoryNav className="border-s border-white/20 ps-2" />
                </div>
                <ExamisMark className="h-8 w-auto shrink-0 border-s border-white/20 ps-2 sm:h-9 sm:ps-3" />
            </div>
        </header>
    );
}
