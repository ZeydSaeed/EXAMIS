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
    { icon: LucideIcon; text: string; border: string; surface: string }
> = {
    draft: {
        icon: CircleDashed,
        text: 'text-slate-700 dark:text-slate-300',
        border: 'border-slate-500/40',
        surface: 'bg-slate-500/10',
    },
    submitted: {
        icon: Send,
        text: 'text-blue-700 dark:text-blue-300',
        border: 'border-blue-500/40',
        surface: 'bg-blue-500/10',
    },
    under_review: {
        icon: Search,
        text: 'text-yellow-800 dark:text-yellow-300',
        border: 'border-yellow-500/40',
        surface: 'bg-yellow-500/15',
    },
    requires_correction: {
        icon: FileEdit,
        text: 'text-orange-700 dark:text-orange-300',
        border: 'border-orange-500/40',
        surface: 'bg-orange-500/10',
    },
    resubmitted: {
        icon: Clock,
        text: 'text-teal-700 dark:text-teal-300',
        border: 'border-teal-500/40',
        surface: 'bg-teal-500/10',
    },
    accepted: {
        icon: CheckCircle2,
        text: 'text-green-700 dark:text-green-300',
        border: 'border-green-500/40',
        surface: 'bg-green-500/10',
    },
    rejected: {
        icon: XCircle,
        text: 'text-red-700 dark:text-red-300',
        border: 'border-red-500/40',
        surface: 'bg-red-500/10',
    },
    withdrawn: {
        icon: CircleSlash,
        text: 'text-stone-700 dark:text-stone-300',
        border: 'border-stone-500/40',
        surface: 'bg-stone-500/10',
    },
};

/**
 * Background tint of a status, for table cells that show a `plain` badge.
 */
export function statusSurface(status: ApplicationStatus): string {
    return appearance[status].surface;
}

/**
 * Each status has its own hue; it is still conveyed by icon and text,
 * never by colour alone.
 */
export function StatusBadge({
    status,
    label,
    variant = 'badge',
    className,
}: {
    status: ApplicationStatus;
    label: string;
    /** `plain` drops the pill (border and fill) and keeps icon and text. */
    variant?: 'badge' | 'plain';
    className?: string;
}) {
    const { icon: Icon, text, border, surface } = appearance[status];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 text-xs font-medium whitespace-nowrap',
                text,
                variant === 'badge' &&
                    cn('rounded-full border px-2.5 py-0.5', border, surface),
                className,
            )}
            data-status={status}
        >
            <Icon className="size-3.5" aria-hidden />
            {label}
        </span>
    );
}
