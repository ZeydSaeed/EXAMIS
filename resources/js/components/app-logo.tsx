import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name, organization } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-[var(--sis-button-radius)] bg-sidebar-primary text-sidebar-primary-foreground">
                <AppLogoIcon className="size-5 fill-current" />
            </div>
            <div className="ms-1 grid flex-1 text-start text-sm">
                <span className="truncate leading-tight font-semibold text-sidebar-foreground">
                    {name}
                </span>
                <span className="truncate text-xs text-sidebar-foreground/70">
                    {organization}
                </span>
            </div>
        </>
    );
}
