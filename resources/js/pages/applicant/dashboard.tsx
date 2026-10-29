import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    CalendarClock,
    FileText,
    Plus,
} from 'lucide-react';
import { EmptyState } from '@/components/examis/empty-state';
import { PageContainer, PageHeader } from '@/components/examis/page-header';
import { StatusBadge } from '@/components/examis/status-badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';
import { formatDate, formatDateTime } from '@/lib/format';
import applications from '@/routes/applications';
import apply from '@/routes/apply';
import type { Application } from '@/types';

type Props = {
    applications: Application[];
    openPeriods: {
        id: number;
        name: string;
        academic_year: string;
        closes_at: string;
        has_applied: boolean;
    }[];
};

export default function ApplicantDashboard({
    applications: myApplications,
    openPeriods,
}: Props) {
    const { t, locale, isRtl } = useTrans();
    const Forward = isRtl ? ArrowLeft : ArrowRight;

    setLayoutProps({
        breadcrumbs: [{ title: t('nav.my_applications'), href: '/dashboard' }],
    });

    const availablePeriods = openPeriods.filter(
        (period) => !period.has_applied,
    );

    return (
        <>
            <Head title={t('nav.my_applications')} />
            <PageContainer>
                <PageHeader
                    title={t('applicant.dashboard_title')}
                    description={t('applicant.dashboard_description')}
                />

                {availablePeriods.length > 0 && (
                    <section
                        aria-labelledby="open-periods"
                        className="space-y-3"
                    >
                        <h2 id="open-periods" className="font-semibold">
                            {t('home.open_periods')}
                        </h2>
                        <div className="grid gap-3 md:grid-cols-2">
                            {availablePeriods.map((period) => (
                                <div
                                    key={period.id}
                                    className="flex flex-col gap-3 rounded-xl border bg-card p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="space-y-1">
                                        <p className="font-medium">
                                            {period.name}
                                        </p>
                                        <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                            <CalendarClock
                                                className="size-4"
                                                aria-hidden
                                            />
                                            {t('home.closes_on', {
                                                date: formatDate(
                                                    period.closes_at,
                                                    locale,
                                                ),
                                            })}
                                        </p>
                                    </div>
                                    <Form
                                        {...applications.store.form()}
                                        transform={() => ({
                                            exam_period_id: period.id,
                                        })}
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <Button
                                                    type="submit"
                                                    disabled={processing}
                                                    data-test="start-application"
                                                >
                                                    {processing ? (
                                                        <Spinner />
                                                    ) : (
                                                        <Plus
                                                            className="size-4"
                                                            aria-hidden
                                                        />
                                                    )}
                                                    {t(
                                                        'applicant.start_application',
                                                    )}
                                                </Button>
                                                {errors.exam_period && (
                                                    <p className="mt-2 text-sm text-destructive">
                                                        {errors.exam_period}
                                                    </p>
                                                )}
                                            </>
                                        )}
                                    </Form>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                <section
                    aria-labelledby="my-applications"
                    className="space-y-3"
                >
                    <h2 id="my-applications" className="font-semibold">
                        {t('nav.my_applications')}
                    </h2>
                    {myApplications.length === 0 ? (
                        <EmptyState
                            icon={FileText}
                            title={t('applicant.no_applications')}
                            description={t('applicant.no_applications_text')}
                        />
                    ) : (
                        <ul className="grid gap-3">
                            {myApplications.map((application) => (
                                <li
                                    key={application.uuid}
                                    className="rounded-xl border bg-card p-4"
                                >
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="min-w-0 space-y-1.5">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="font-medium">
                                                    {
                                                        application.exam_period
                                                            ?.name
                                                    }
                                                </p>
                                                <StatusBadge
                                                    status={application.status}
                                                    label={
                                                        application.status_label
                                                    }
                                                />
                                            </div>
                                            <p className="text-sm text-muted-foreground">
                                                {t('applicant.last_saved', {
                                                    date: formatDateTime(
                                                        application.updated_at,
                                                        locale,
                                                    ),
                                                })}
                                            </p>
                                        </div>
                                        <div className="flex gap-2">
                                            {application.is_editable &&
                                                (application.status ===
                                                    'submitted' ||
                                                application.status ===
                                                    'resubmitted' ? (
                                                    <Button asChild>
                                                        <Link
                                                            href={apply.specialization()}
                                                        >
                                                            {t(
                                                                'applicant.edit_application',
                                                            )}
                                                        </Link>
                                                    </Button>
                                                ) : (
                                                    <Button asChild>
                                                        <Link
                                                            href={applications.edit(
                                                                application.uuid,
                                                            )}
                                                        >
                                                            {application.status ===
                                                            'draft'
                                                                ? t(
                                                                      'applicant.continue_application',
                                                                  )
                                                                : t(
                                                                      'applicant.respond_to_correction',
                                                                  )}
                                                        </Link>
                                                    </Button>
                                                ))}
                                            <Button variant="outline" asChild>
                                                <Link
                                                    href={applications.show(
                                                        application.uuid,
                                                    )}
                                                >
                                                    {t(
                                                        'applicant.view_application',
                                                    )}
                                                    <Forward
                                                        className="size-4"
                                                        aria-hidden
                                                    />
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </PageContainer>
        </>
    );
}
