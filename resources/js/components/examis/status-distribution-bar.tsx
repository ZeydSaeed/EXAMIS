import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTrans } from '@/hooks/use-trans';
import { formatPercent } from '@/lib/format';
import type { ApplicationStatus } from '@/types';

type Row = { status: ApplicationStatus; label: string; count: number };

/** Display order of the statuses, shared with the dashboard cards. */
export const statusOrder: ApplicationStatus[] = [
    'accepted',
    'submitted',
    'under_review',
    'requires_correction',
    'resubmitted',
    'rejected',
    'withdrawn',
];

/**
 * Share of applications in each status as a single segmented bar with a
 * legend underneath. Colours come from the caller (the dashboard passes
 * the status-card colours), each status keeps its colour regardless of rank,
 * and every segment has a labelled legend entry. Empty statuses are left out.
 */
export function StatusDistributionBar({
    rows,
    colors,
    className = '',
}: {
    rows: Row[];
    /** Fill classes per status, shared with the status cards. */
    colors: Partial<Record<ApplicationStatus, string>>;
    className?: string;
}) {
    const { t, locale } = useTrans();
    const segments = statusOrder
        .map((status) => rows.find((row) => row.status === status))
        .filter((row): row is Row => !!row && row.count > 0);
    const total = segments.reduce((sum, row) => sum + row.count, 0);

    if (total === 0) {
        return null;
    }

    return (
        <figure
            className={`grid gap-3 ${className}`}
            aria-label={t('admin.by_status')}
            data-test="status-distribution"
        >
            <div className="flex h-3.5 gap-0.5 overflow-hidden rounded-full">
                {segments.map((row) => (
                    <Tooltip key={row.status}>
                        <TooltipTrigger asChild>
                            <div
                                className={`h-full min-w-1.5 transition-opacity hover:opacity-80 ${colors[row.status]}`}
                                style={{ flexGrow: row.count, flexBasis: 0 }}
                                aria-hidden
                            />
                        </TooltipTrigger>
                        <TooltipContent>
                            {row.label} · {row.count} (
                            {formatPercent(row.count / total, locale)})
                        </TooltipContent>
                    </Tooltip>
                ))}
            </div>

            <ul className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 text-xs sm:flex-nowrap sm:text-sm">
                {segments.map((row) => (
                    <li
                        key={row.status}
                        className="flex shrink-0 items-center gap-1.5 whitespace-nowrap"
                    >
                        <span
                            className={`size-3 shrink-0 rounded-full ${colors[row.status]}`}
                            aria-hidden
                        />
                        <span className="font-medium">{row.label}</span>
                        <span className="text-muted-foreground tabular-nums">
                            · {formatPercent(row.count / total, locale)}
                        </span>
                    </li>
                ))}
            </ul>
        </figure>
    );
}
