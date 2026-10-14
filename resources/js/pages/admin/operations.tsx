import { Head, setLayoutProps, usePoll } from '@inertiajs/react';
import { CheckCircle2, XCircle } from 'lucide-react';
import { PageContainer, PageHeader } from '@/components/examis/page-header';
import { useTrans } from '@/hooks/use-trans';
import admin from '@/routes/admin';

type Props = {
    checks: Record<'database' | 'cache' | 'storage', boolean>;
    queue: Record<string, number | null>;
    security: Record<string, number>;
    environment: Record<string, string>;
};

export default function Operations({
    checks,
    queue,
    security,
    environment,
}: Props) {
    const { t } = useTrans();
    usePoll(30000);

    setLayoutProps({
        breadcrumbs: [{ title: t('nav.operations'), href: admin.operations() }],
    });

    const metric = (
        label: string,
        value: number | null | undefined,
        warnAbove?: number,
    ) => (
        <div key={label} className="rounded-lg border bg-card p-4">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p
                className={`text-2xl font-semibold ${warnAbove !== undefined && (value ?? 0) > warnAbove ? 'text-destructive' : ''}`}
            >
                {value ?? '—'}
            </p>
        </div>
    );

    return (
        <>
            <Head title={t('nav.operations')} />
            <PageContainer>
                <PageHeader
                    title={t('nav.operations')}
                    description={t('operations.description')}
                />

                <section aria-labelledby="checks" className="space-y-3">
                    <h2 id="checks" className="font-semibold">
                        {t('operations.dependencies')}
                    </h2>
                    <div className="grid gap-3 sm:grid-cols-3">
                        {(
                            Object.entries(checks) as [
                                keyof Props['checks'],
                                boolean,
                            ][]
                        ).map(([name, ok]) => (
                            <div
                                key={name}
                                className="flex items-center gap-3 rounded-lg border bg-card p-4"
                            >
                                {ok ? (
                                    <CheckCircle2
                                        className="size-6 text-success"
                                        aria-hidden
                                    />
                                ) : (
                                    <XCircle
                                        className="size-6 text-destructive"
                                        aria-hidden
                                    />
                                )}
                                <div>
                                    <p className="font-medium">
                                        {t(`operations.check.${name}`)}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {ok
                                            ? t('operations.ok')
                                            : t('operations.failing')}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                <section aria-labelledby="queue" className="space-y-3">
                    <h2 id="queue" className="font-semibold">
                        {t('operations.queues')}
                    </h2>
                    <div className="grid gap-3 sm:grid-cols-4">
                        {metric(
                            t('operations.pending_jobs'),
                            queue.pending_jobs,
                            500,
                        )}
                        {metric(
                            t('operations.oldest_pending_seconds'),
                            queue.oldest_pending_seconds,
                            300,
                        )}
                        {metric(
                            t('operations.failed_jobs'),
                            queue.failed_jobs,
                            0,
                        )}
                        {metric(
                            t('operations.documents_pending_scan'),
                            queue.documents_pending_scan,
                            50,
                        )}
                    </div>
                </section>

                <section aria-labelledby="security" className="space-y-3">
                    <h2 id="security" className="font-semibold">
                        {t('operations.security')}
                    </h2>
                    <div className="grid gap-3 sm:grid-cols-4">
                        {metric(
                            t('operations.failed_logins_last_hour'),
                            security.failed_logins_last_hour,
                            100,
                        )}
                        {metric(
                            t('operations.lockouts_last_hour'),
                            security.lockouts_last_hour,
                            10,
                        )}
                    </div>
                </section>

                <section aria-labelledby="environment" className="space-y-3">
                    <h2 id="environment" className="font-semibold">
                        {t('operations.configuration')}
                    </h2>
                    <dl
                        className="grid gap-2 rounded-xl border bg-card p-4 text-sm sm:grid-cols-2"
                        dir="ltr"
                    >
                        {Object.entries(environment).map(([key, value]) => (
                            <div
                                key={key}
                                className="flex justify-between gap-4"
                            >
                                <dt className="text-muted-foreground">{key}</dt>
                                <dd className="font-mono">{value}</dd>
                            </div>
                        ))}
                    </dl>
                    {environment.scanner === 'structural' && (
                        <p className="text-sm text-warning">
                            {t('operations.scanner_warning')}
                        </p>
                    )}
                </section>
            </PageContainer>
        </>
    );
}
