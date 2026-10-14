import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
}: {
    icon: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 rounded-[var(--sis-surface-radius)] border px-4 py-12 text-center">
            <div className="flex size-12 items-center justify-center rounded-[var(--sis-button-radius)] bg-muted">
                <Icon className="size-6 text-muted-foreground" aria-hidden />
            </div>
            <div className="space-y-1">
                <p className="text-base font-medium">{title}</p>
                {description && (
                    <p className="max-w-md text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {action ? <div className="mt-2">{action}</div> : null}
        </div>
    );
}
