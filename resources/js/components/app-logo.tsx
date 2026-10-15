import { usePage } from '@inertiajs/react';

import { ExamisMark } from '@/components/examis/examis-mark';

/**
 * Sidebar / header brand: the EXAMIS mark on its own. The application name
 * is kept as the SVG title for assistive technology.
 */
export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <ExamisMark
            title={name}
            className="mx-auto h-11! w-auto! shrink-0 text-sidebar-foreground group-data-[collapsible=icon]:h-6!"
        />
    );
}
