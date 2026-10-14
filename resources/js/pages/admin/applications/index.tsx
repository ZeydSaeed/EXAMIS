import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { Inbox, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { DataTableFrame } from '@/components/examis/data-table-frame';
import { EmptyState } from '@/components/examis/empty-state';
import { selectClassName } from '@/components/examis/field';
import { PageContainer, PageHeader } from '@/components/examis/page-header';
import { Pagination } from '@/components/examis/pagination';
import { StatusBadge } from '@/components/examis/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/hooks/use-trans';
import { formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';
import type { ApplicationStatus, Paginated } from '@/types';

type Row = {
    uuid: string;
    reference_number: string | null;
    full_name_ar: string | null;
    status: ApplicationStatus;
    status_label: string;
    exam_period: string;
    assignee: string | null;
    submitted_at: string | null;
};

type Filters = {
    status?: string;
    period?: string | number;
    assigned?: string;
    q?: string;
};

type Props = {
    applications: Paginated<Row>;
    filters: Filters;
    periods: { id: number; name: string }[];
    statuses: { value: ApplicationStatus; label: string }[];
};

export default function AdminApplications({
    applications,
    filters,
    periods,
    statuses,
}: Props) {
    const { t, locale } = useTrans();
    const [values, setValues] = useState<Filters>(filters);

    setLayoutProps({
        breadcrumbs: [
            { title: t('nav.applications'), href: admin.applications.index() },
        ],
    });

    const apply = (event?: FormEvent, next: Filters = values) => {
        event?.preventDefault();
        const query = Object.fromEntries(
            Object.entries(next).filter(
                ([, value]) => value !== undefined && value !== '',
            ),
        );
        router.get(admin.applications.index.url(), query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const update = (key: keyof Filters, value: string) => {
        const next = { ...values, [key]: value };
        setValues(next);

        if (key !== 'q') {
            apply(undefined, next);
        }
    };

    return (
        <>
            <Head title={t('nav.applications')} />
            <PageContainer>
                <PageHeader
                    title={t('nav.applications')}
                    description={t('admin.applications_description', {
                        total: applications.total,
                    })}
                />

                <form
                    onSubmit={apply}
                    className="no-print grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1fr_auto] lg:items-end"
                    role="search"
                >
                    <div className="grid gap-1.5">
                        <Label htmlFor="q">{t('admin.search')}</Label>
                        <Input
                            id="q"
                            type="search"
                            value={values.q ?? ''}
                            onChange={(event) =>
                                update('q', event.target.value)
                            }
                            placeholder={t('admin.search_placeholder')}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="status">
                            {t('applications.status')}
                        </Label>
                        <select
                            id="status"
                            className={selectClassName}
                            value={values.status ?? ''}
                            onChange={(event) =>
                                update('status', event.target.value)
                            }
                        >
                            <option value="">{t('common.all')}</option>
                            {statuses.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="period">
                            {t('applications.exam_period')}
                        </Label>
                        <select
                            id="period"
                            className={selectClassName}
                            value={values.period ?? ''}
                            onChange={(event) =>
                                update('period', event.target.value)
                            }
                        >
                            <option value="">{t('common.all')}</option>
                            {periods.map((period) => (
                                <option key={period.id} value={period.id}>
                                    {period.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="assigned">
                            {t('admin.assignment')}
                        </Label>
                        <select
                            id="assigned"
                            className={selectClassName}
                            value={values.assigned ?? ''}
                            onChange={(event) =>
                                update('assigned', event.target.value)
                            }
                        >
                            <option value="">{t('common.all')}</option>
                            <option value="me">
                                {t('admin.assigned_to_me')}
                            </option>
                            <option value="unassigned">
                                {t('admin.unassigned')}
                            </option>
                        </select>
                    </div>
                    <Button type="submit">
                        <Search className="size-4" aria-hidden />
                        {t('admin.search')}
                    </Button>
                </form>

                {applications.data.length === 0 ? (
                    <EmptyState icon={Inbox} title={t('admin.no_results')} />
                ) : (
                    <DataTableFrame>
                        <table className="min-w-[44rem]">
                            <caption className="sr-only">
                                {t('nav.applications')}
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col">
                                        {t('track.reference_number')}
                                    </th>
                                    <th scope="col">{t('fields.full_name_ar')}</th>
                                    <th scope="col">
                                        {t('applications.status')}
                                    </th>
                                    <th scope="col">{t('admin.assignee')}</th>
                                    <th scope="col">
                                        {t('applications.submitted_at')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {applications.data.map((row) => (
                                    <tr key={row.uuid}>
                                        <td>
                                            <Link
                                                href={admin.applications.show(
                                                    row.uuid,
                                                )}
                                                className="hover:underline"
                                                dir="ltr"
                                            >
                                                {row.reference_number}
                                            </Link>
                                        </td>
                                        <td>{row.full_name_ar}</td>
                                        <td>
                                            <StatusBadge
                                                status={row.status}
                                                label={row.status_label}
                                            />
                                        </td>
                                        <td className="text-muted-foreground">
                                            {row.assignee ?? '—'}
                                        </td>
                                        <td className="text-muted-foreground">
                                            {formatDateTime(
                                                row.submitted_at,
                                                locale,
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </DataTableFrame>
                )}

                <Pagination
                    prev={applications.prev_page_url}
                    next={applications.next_page_url}
                    summary={
                        applications.total > 0
                            ? t('common.showing', {
                                  from: applications.from ?? 0,
                                  to: applications.to ?? 0,
                                  total: applications.total,
                              })
                            : undefined
                    }
                />
            </PageContainer>
        </>
    );
}
