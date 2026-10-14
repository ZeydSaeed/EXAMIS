import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Outer frame for data tables — SIS-style organization via `.examis-data-table`.
 * Pass a native <table> as children; do not add border/bg on the table itself.
 */
export function DataTableFrame({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('examis-data-table', className)} data-allow-x-scroll>
            {children}
        </div>
    );
}
