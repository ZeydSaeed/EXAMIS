import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTrans } from '@/hooks/use-trans';
import { formatPercent } from '@/lib/format';
import type { ApplicationStatus } from '@/types';

type Row = { status: ApplicationStatus; label: string; count: number };

type Props = {
    rows: Row[];
    /** Fill classes per status, shared with the status cards. */
    colors: Partial<Record<ApplicationStatus, string>>;
    className?: string;
};

/** Display order of the statuses, shared with the dashboard cards. */
export const statusOrder: ApplicationStatus[] = [
    'submitted',
    'under_review',
    'requires_correction',
    'resubmitted',
    'accepted',
    'rejected',
    'withdrawn',
];

/** Non-empty statuses in display order, with their total. */
function useSegments(rows: Row[]): { segments: Row[]; total: number } {
    const segments = statusOrder
        .map((status) => rows.find((row) => row.status === status))
        .filter((row): row is Row => !!row && row.count > 0);

    return {
        segments,
        total: segments.reduce((sum, row) => sum + row.count, 0),
    };
}

/**
 * Share of applications in each status as a single horizontal segmented
 * bar, in `statusOrder`. Colours come from the caller (the dashboard passes
 * the status-card colours), each status keeps its colour regardless of rank,
 * and every segment has a labelled entry in `StatusDistributionLegend`.
 * Empty statuses are left out.
 */
export function StatusDistributionBar({ rows, colors, className = '' }: Props) {
    const { t, locale } = useTrans();
    const { segments, total } = useSegments(rows);

    if (total === 0) {
        return null;
    }

    return (
        <div
            className={`flex h-3.5 gap-0.5 overflow-hidden rounded-full ${className}`}
            role="img"
            aria-label={t('admin.by_status')}
            data-test="status-distribution"
        >
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
    );
}

/** Vertical legend for `StatusDistributionBar`: label and share per status. */
export function StatusDistributionLegend({
    rows,
    colors,
    className = '',
}: Props) {
    const { locale } = useTrans();
    const { segments, total } = useSegments(rows);

    if (total === 0) {
        return null;
    }

    return (
        <ul
            className={`flex flex-col gap-2.5 text-xs sm:text-sm ${className}`}
            data-test="status-legend"
        >
            {segments.map((row) => (
                <li
                    key={row.status}
                    className="flex items-center gap-1.5 whitespace-nowrap"
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
    );
}
