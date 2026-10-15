import { Head, setLayoutProps } from '@inertiajs/react';
import { ScrollText } from 'lucide-react';
import { DataTableFrame } from '@/components/examis/data-table-frame';
import { EmptyState } from '@/components/examis/empty-state';
import { Highlight } from '@/components/examis/highlight';
import {
    HeaderFilter,
    HeaderFilterChip,
    ListHeader,
} from '@/components/examis/list-header';
import { Pagination } from '@/components/examis/pagination';
import { useListQuery } from '@/hooks/use-list-query';
import { useTrans } from '@/hooks/use-trans';
import { formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';
import type { CursorPaginated } from '@/types';

type AuditRow = {
    id: number;
    action: string;
    actor: { id: number; username: string } | null;
    subject_type: string | null;
    subject_id: number | null;
    ip_address: string | null;
    request_id: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
};

type Props = {
    events: CursorPaginated<AuditRow>;
    filters: {
        action?: string;
        actor?: string | number;
        subject?: string;
        q?: string;
        per_page?: string | number;
    };
    subjects: string[];
};

/**
 * Cursor-paginated (previous / next only) so deep pages stay cheap on the
 * large append-only table; there is no total, hence no page numbers.
 */
export default function AuditLog({ events, filters, subjects }: Props) {
    const { t, locale } = useTrans();
    const { search, setSearch, visit, tableArea } = useListQuery({
        url: admin.audit.index.url,
        filters,
        paginator: events,
    });

    setLayoutProps({
        breadcrumbs: [{ title: t('nav.audit'), href: admin.audit.index() }],
    });

    return (
        <>
            <Head title={t('nav.audit')} />

            <ListHeader
                search={search}
                onSearchChange={setSearch}
                onSearchSubmit={() => visit({ q: search.trim() })}
                placeholder={t('audit.search_placeholder')}
            >
                <HeaderFilter
                    label={t('audit.subject')}
                    value={filters.subject ?? ''}
                    options={subjects.map((subject) => ({
                        value: subject,
                        label: subject,
                    }))}
                    onChange={(subject) => visit({ subject })}
                />
                {filters.action && (
                    <HeaderFilterChip
                        label={`${t('audit.action')}: ${filters.action}`}
                        onRemove={() => visit({ action: '' })}
                    />
                )}
                {filters.actor && (
                    <HeaderFilterChip
                        label={`${t('audit.actor_id')}: ${filters.actor}`}
                        onRemove={() => visit({ actor: '' })}
                    />
                )}
            </ListHeader>

            <div className="mx-auto flex min-h-0 w-full max-w-6xl flex-1 flex-col gap-3 p-4 sm:p-6">
                <h1 className="sr-only">{t('nav.audit')}</h1>

                <div ref={tableArea} className="min-h-0 flex-1">
                    {events.data.length === 0 ? (
                        <EmptyState
                            icon={ScrollText}
                            title={t('admin.no_results')}
                        />
                    ) : (
                        <DataTableFrame className="examis-data-table--plain max-h-full">
                            <table className="min-w-[56rem] [&_td]:whitespace-nowrap">
                                <caption className="sr-only">
                                    {t('nav.audit')}
                                </caption>
                                <thead>
                                    <tr>
                                        <th scope="col">{t('audit.time')}</th>
                                        <th scope="col">{t('audit.action')}</th>
                                        <th scope="col">{t('audit.actor')}</th>
                                        <th scope="col">
                                            {t('audit.subject')}
                                        </th>
                                        <th scope="col">
                                            {t('audit.details')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {events.data.map((event) => (
                                        <tr key={event.id}>
                                            <td className="text-muted-foreground">
                                                {formatDateTime(
                                                    event.created_at,
                                                    locale,
                                                )}
                                            </td>
                                            <td
                                                className="font-mono text-xs"
                                                dir="ltr"
                                            >
                                                <Highlight
                                                    text={event.action}
                                                    query={filters.q}
                                                />
                                            </td>
                                            <td dir="ltr">
                                                {event.actor ? (
                                                    <>
                                                        <Highlight
                                                            text={
                                                                event.actor
                                                                    .username
                                                            }
                                                            query={filters.q}
                                                        />{' '}
                                                        <span className="text-muted-foreground">
                                                            #{event.actor.id}
                                                        </span>
                                                    </>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td dir="ltr">
                                                {event.subject_type
                                                    ? `${event.subject_type} #${event.subject_id}`
                                                    : '—'}
                                            </td>
                                            <td
                                                className="max-w-md truncate font-mono text-xs text-muted-foreground"
                                                dir="ltr"
                                                title={
                                                    event.metadata
                                                        ? JSON.stringify(
                                                              event.metadata,
                                                          )
                                                        : undefined
                                                }
                                            >
                                                {event.ip_address}{' '}
                                                {event.metadata
                                                    ? JSON.stringify(
                                                          event.metadata,
                                                      )
                                                    : ''}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </DataTableFrame>
                    )}
                </div>

                <Pagination
                    prev={events.prev_page_url}
                    next={events.next_page_url}
                />
            </div>
        </>
    );
}
