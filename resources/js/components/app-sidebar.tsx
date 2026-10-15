import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bell,
    CalendarRange,
    ClipboardList,
    FileText,
    HelpCircle,
    House,
    LayoutGrid,
    ScrollText,
    ShieldCheck,
    Users,
    Activity,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useTrans } from '@/hooks/use-trans';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import notifications from '@/routes/notifications';
import pages from '@/routes/pages';
import type { NavItem } from '@/types';

/**
 * Navigation is derived from the permissions the server shares. This only
 * hides links; every request is authorized again on the server.
 */
export function AppSidebar() {
    const { auth } = usePage().props;
    const { t, isRtl } = useTrans();
    const can = (permission: string) =>
        auth.user.permissions.includes(permission);

    const applicantItems: NavItem[] = [
        {
            title: t('nav.my_applications'),
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: t('nav.notifications'),
            href: notifications.index(),
            icon: Bell,
        },
    ];

    const staffItems: NavItem[] = [
        {
            title: t('nav.staff_home'),
            href: admin.home(),
            icon: House,
        },
        {
            title: t('nav.admin_dashboard'),
            href: admin.dashboard(),
            icon: LayoutGrid,
        },
        ...(can('applications.view')
            ? [
                  {
                      title: t('nav.applications'),
                      href: admin.applications.index(),
                      icon: ClipboardList,
                  },
              ]
            : []),
        ...(can('reports.view')
            ? [
                  {
                      title: t('nav.reports'),
                      href: admin.reports.index(),
                      icon: BarChart3,
                  },
              ]
            : []),
        ...(can('periods.manage')
            ? [
                  {
                      title: t('nav.periods'),
                      href: admin.periods.index(),
                      icon: CalendarRange,
                  },
              ]
            : []),
        ...(can('content.manage')
            ? [
                  {
                      title: t('nav.content'),
                      href: admin.content.index(),
                      icon: FileText,
                  },
              ]
            : []),
        ...(can('users.manage')
            ? [
                  {
                      title: t('nav.users'),
                      href: admin.users.index(),
                      icon: Users,
                  },
              ]
            : []),
        ...(can('audit.view')
            ? [
                  {
                      title: t('nav.audit'),
                      href: admin.audit.index(),
                      icon: ScrollText,
                  },
              ]
            : []),
        ...(can('operations.view')
            ? [
                  {
                      title: t('nav.operations'),
                      href: admin.operations(),
                      icon: Activity,
                  },
              ]
            : []),
    ];

    const isStaff = auth.user.account_type === 'staff';
    const homeHref = isStaff ? admin.home() : dashboard();

    const footerItems: NavItem[] = isStaff
        ? [
              {
                  title: t('nav.security'),
                  href: '/settings/security',
                  icon: ShieldCheck,
              },
          ]
        : [
              {
                  title: t('nav.help'),
                  href: pages.show('help'),
                  icon: HelpCircle,
              },
          ];

    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            side={isRtl ? 'right' : 'left'}
        >
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={homeHref} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain
                    label={
                        isStaff ? t('nav.administration') : t('nav.applicant')
                    }
                    items={isStaff ? staffItems : applicantItems}
                />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
