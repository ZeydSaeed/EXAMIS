import { Head, router, setLayoutProps, usePage } from '@inertiajs/react';
import { DatabaseBackup, LoaderCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { DataTableFrame } from '@/components/examis/data-table-frame';
import { PageContainer, PageHeader } from '@/components/examis/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';
import { formatDateTime, formatFileSize } from '@/lib/format';
import admin from '@/routes/admin';

type BackupSet = { name: string; size: number };

type KindStatus = {
    last: string | null;
    size: number | null;
    count: number;
    sets: BackupSet[];
};

type Props = {
    /** Written by deploy/backup/examis-backup.sh after every run. */
    status: {
        updated_at: string;
        last_run?: { kind: string; stamp: string; result: 'ok' | 'failed' };
        kinds: Partial<
            Record<'6h' | 'daily' | 'manual' | 'weekly', KindStatus>
        >;
    } | null;
    requestedAt: string | null;
    flash: string | null;
};

const KINDS = ['6h', 'daily', 'manual', 'weekly'] as const;

/** 20261108T120000Z → 2026-11-08T12:00:00Z */
function stampToIso(stamp: string): string {
    return stamp.replace(
        /^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})Z$/,
        '$1-$2-$3T$4:$5:$6Z',
    );
}

export default function BackupsIndex({ status, requestedAt, flash }: Props) {
    const { t, locale } = useTrans();
    const { errors } = usePage().props;
    const [processing, setProcessing] = useState(false);

    setLayoutProps({
        breadcrumbs: [{ title: t('nav.backups'), href: admin.backups.index() }],
    });

    // While a requested backup runs, look again every 10 seconds.
    useEffect(() => {
        if (requestedAt === null) {
            return;
        }

        const timer = window.setInterval(
            () => router.reload({ only: ['status', 'requestedAt'] }),
            10_000,
        );

        return () => window.clearInterval(timer);
    }, [requestedAt]);

    const runNow = () =>
        router.post(
            admin.backups.store.url(),
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );

    const sets = KINDS.flatMap((kind) =>
        (status?.kinds[kind]?.sets ?? []).map((set) => ({ ...set, kind })),
    ).sort((a, b) => b.name.localeCompare(a.name));

    return (
        <>
            <Head title={t('backup.title')} />
            <PageContainer className="max-w-4xl">
                <PageHeader
                    title={t('backup.title')}
                    description={t('backup.intro')}
                    actions={
                        <Button
                            onClick={runNow}
                            disabled={processing || requestedAt !== null}
                        >
                            {processing ? (
                                <Spinner />
                            ) : (
                                <DatabaseBackup aria-hidden />
                            )}
                            {t('backup.run_now')}
                        </Button>
                    }
                />

                <p className="text-sm text-muted-foreground">
                    {t('backup.run_hint')}
                </p>

                {flash && (
                    <p
                        role="status"
                        className="rounded-md border border-emerald-600/30 bg-emerald-600/10 px-3 py-2 text-sm"
                    >
                        {flash}
                    </p>
                )}
                <InputError message={errors.backup} />

                {requestedAt !== null && (
                    <p
                        role="status"
                        className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
                    >
                        <LoaderCircle
                            className="size-4 animate-spin"
                            aria-hidden
                        />
                        {t('backup.pending', {
                            time: formatDateTime(requestedAt, locale),
                        })}
                    </p>
                )}

                {status?.last_run?.result === 'failed' && (
                    <InputError
                        message={t('backup.last_failed', {
                            time: formatDateTime(
                                stampToIso(status.last_run.stamp),
                                locale,
                            ),
                        })}
                    />
                )}

                {status === null ? (
                    <p className="rounded-md border px-3 py-6 text-center text-sm text-muted-foreground">
                        {t('backup.no_status')}
                    </p>
                ) : (
                    <>
                        <DataTableFrame className="examis-data-table--plain">
                            <table className="min-w-[32rem]">
                                <caption className="sr-only">
                                    {t('backup.title')}
                                </caption>
                                <thead>
                                    <tr>
                                        <th scope="col">{t('backup.kind')}</th>
                                        <th scope="col">{t('backup.last')}</th>
                                        <th
                                            scope="col"
                                            className="text-center!"
                                        >
                                            {t('backup.size')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="text-center!"
                                        >
                                            {t('backup.count')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {KINDS.map((kind) => {
                                        const entry = status.kinds[kind];

                                        return (
                                            <tr key={kind}>
                                                <td>
                                                    {t(`backup.kinds.${kind}`)}
                                                </td>
                                                <td>
                                                    {entry?.last
                                                        ? formatDateTime(
                                                              stampToIso(
                                                                  entry.last,
                                                              ),
                                                              locale,
                                                          )
                                                        : t('backup.never')}
                                                </td>
                                                <td className="text-center! tabular-nums">
                                                    {entry?.size
                                                        ? formatFileSize(
                                                              entry.size,
                                                              locale,
                                                          )
                                                        : '—'}
                                                </td>
                                                <td className="text-center! tabular-nums">
                                                    {entry?.count ?? 0}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </DataTableFrame>

                        <h2 className="font-semibold">{t('backup.sets')}</h2>
                        <DataTableFrame className="examis-data-table--plain">
                            <table className="min-w-[32rem]">
                                <thead>
                                    <tr>
                                        <th scope="col">{t('backup.set')}</th>
                                        <th scope="col">{t('backup.kind')}</th>
                                        <th
                                            scope="col"
                                            className="text-center!"
                                        >
                                            {t('backup.size')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sets.map((set) => (
                                        <tr key={`${set.kind}-${set.name}`}>
                                            <td>
                                                {formatDateTime(
                                                    stampToIso(set.name),
                                                    locale,
                                                )}
                                            </td>
                                            <td>
                                                {t(`backup.kinds.${set.kind}`)}
                                            </td>
                                            <td className="text-center! tabular-nums">
                                                {formatFileSize(
                                                    set.size,
                                                    locale,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </DataTableFrame>

                        <p className="text-xs text-muted-foreground">
                            {t('backup.updated', {
                                time: formatDateTime(status.updated_at, locale),
                            })}
                        </p>
                    </>
                )}
            </PageContainer>
        </>
    );
}
