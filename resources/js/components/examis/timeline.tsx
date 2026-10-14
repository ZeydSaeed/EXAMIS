import { useTrans } from '@/hooks/use-trans';
import { formatDateTime } from '@/lib/format';
import type { ApplicationEvent } from '@/types';

export function Timeline({ events }: { events: ApplicationEvent[] }) {
    const { t, locale } = useTrans();

    if (events.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                {t('applicant.no_history')}
            </p>
        );
    }

    return (
        <ol className="relative space-y-4 border-s ps-4">
            {[...events].reverse().map((event) => (
                <li key={event.id} className="relative">
                    <span
                        className="absolute -start-[1.3rem] top-1.5 size-2.5 rounded-full border-2 border-background bg-primary"
                        aria-hidden
                    />
                    <p className="text-sm font-medium">
                        {event.to_status_label
                            ? `${event.type_label}: ${event.to_status_label}`
                            : event.type_label}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {event.actor} ·{' '}
                        <time dateTime={event.created_at}>
                            {formatDateTime(event.created_at, locale)}
                        </time>
                    </p>
                    {event.reason && (
                        <p className="mt-1 rounded-md bg-muted p-2 text-sm whitespace-pre-line">
                            {event.reason}
                        </p>
                    )}
                </li>
            ))}
        </ol>
    );
}
