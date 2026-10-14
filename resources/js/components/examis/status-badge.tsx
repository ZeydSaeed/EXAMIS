import {
    CheckCircle2,
    CircleDashed,
    CircleSlash,
    Clock,
    FileEdit,
    Search,
    Send,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { ApplicationStatus } from '@/types';

const appearance: Record<
    ApplicationStatus,
    { icon: LucideIcon; className: string }
> = {
    draft: {
        icon: CircleDashed,
        className: 'border-border bg-muted text-muted-foreground',
    },
    submitted: { icon: Send, className: 'border-info/40 bg-info/10 text-info' },
    under_review: {
        icon: Search,
        className: 'border-info/40 bg-info/10 text-info',
    },
    requires_correction: {
        icon: FileEdit,
        className: 'border-warning/50 bg-warning/15 text-foreground',
    },
    resubmitted: {
        icon: Clock,
        className: 'border-info/40 bg-info/10 text-info',
    },
    accepted: {
        icon: CheckCircle2,
        className: 'border-success/40 bg-success/10 text-success',
    },
    rejected: {
        icon: XCircle,
        className: 'border-destructive/40 bg-destructive/10 text-destructive',
    },
    withdrawn: {
        icon: CircleSlash,
        className: 'border-border bg-muted text-muted-foreground',
    },
};

/**
 * Status is conveyed by icon and text, never by colour alone.
 */
export function StatusBadge({
    status,
    label,
    className,
}: {
    status: ApplicationStatus;
    label: string;
    className?: string;
}) {
    const { icon: Icon, className: tone } = appearance[status];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium whitespace-nowrap',
                tone,
                className,
            )}
            data-status={status}
        >
            <Icon className="size-3.5" aria-hidden />
            {label}
        </span>
    );
}
