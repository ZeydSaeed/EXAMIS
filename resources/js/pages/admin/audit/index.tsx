import { Head, router, setLayoutProps } from '@inertiajs/react';
import { ScrollText, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { DataTableFrame } from '@/components/examis/data-table-frame';
import { EmptyState } from '@/components/examis/empty-state';
import { PageContainer, PageHeader } from '@/components/examis/page-header';
import { Pagination } from '@/components/examis/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    filters: { action?: string; actor?: string; subject?: string };
};

export default function AuditLog({ events, filters }: Props) {
    const { t, locale } = useTrans();
    const [values, setValues] = useState(filters);

    setLayoutProps({
        breadcrumbs: [{ title: t('nav.audit'), href: admin.audit.index() }],
    });

    const apply = (event: FormEvent) => {
        event.preventDefault();
        const query = Object.fromEntries(
            Object.entries(values).filter(([, value]) => value),
        );
        router.get(admin.audit.index.url(), query, {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title={t('nav.audit')} />
            <PageContainer>
                <PageHeader
                    title={t('nav.audit')}
                    description={t('audit.description')}
                />

                <form
                    onSubmit={apply}
                    className="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end"
                    role="search"
                >
                    <div className="grid gap-1.5">
                        <Label htmlFor="action">{t('audit.action')}</Label>
                        <Input
                            id="action"
                            dir="ltr"
                            placeholder="application."
                            value={values.action ?? ''}
                            onChange={(event) =>
                                setValues({
                                    ...values,
                                    action: event.target.value,
                                })
                            }
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="actor">{t('audit.actor_id')}</Label>
                        <Input
                            id="actor"
                            dir="ltr"
                            inputMode="numeric"
                            value={values.actor ?? ''}
                            onChange={(event) =>
                                setValues({
                                    ...values,
                                    actor: event.target.value,
                                })
                            }
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="subject">{t('audit.subject')}</Label>
                        <Input
                            id="subject"
                            dir="ltr"
                            placeholder="Application"
                            value={values.subject ?? ''}
                            onChange={(event) =>
                                setValues({
                                    ...values,
                                    subject: event.target.value,
                                })
                            }
                        />
                    </div>
                    <Button type="submit">
                        <Search className="size-4" aria-hidden />
                        {t('admin.search')}
                    </Button>
                </form>

                {events.data.length === 0 ? (
                    <EmptyState
                        icon={ScrollText}
                        title={t('admin.no_results')}
                    />
                ) : (
                    <DataTableFrame>
                        <table className="min-w-[56rem]" dir="ltr">
                            <thead>
                                <tr>
                                    <th scope="col">{t('audit.time')}</th>
                                    <th scope="col">{t('audit.action')}</th>
                                    <th scope="col">{t('audit.actor')}</th>
                                    <th scope="col">{t('audit.subject')}</th>
                                    <th scope="col">{t('audit.details')}</th>
                                </tr>
                            </thead>
                            <tbody className="font-mono text-xs">
                                {events.data.map((event) => (
                                    <tr key={event.id}>
                                        <td className="whitespace-nowrap">
                                            {formatDateTime(
                                                event.created_at,
                                                locale,
                                            )}
                                        </td>
                                        <td>{event.action}</td>
                                        <td>
                                            {event.actor
                                                ? `${event.actor.username} (#${event.actor.id})`
                                                : '—'}
                                        </td>
                                        <td>
                                            {event.subject_type
                                                ? `${event.subject_type} #${event.subject_id}`
                                                : '—'}
                                        </td>
                                        <td className="max-w-md break-all text-muted-foreground">
                                            {event.ip_address}{' '}
                                            {event.metadata
                                                ? JSON.stringify(event.metadata)
                                                : ''}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </DataTableFrame>
                )}

                <Pagination
                    prev={events.prev_page_url}
                    next={events.next_page_url}
                />
            </PageContainer>
        </>
    );
}
