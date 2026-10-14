import { Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    ClipboardCheck,
    FileUp,
    Printer,
    Search,
    UserPlus,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-trans';
import { formatDate } from '@/lib/format';
import { dashboard, register } from '@/routes';
import pages from '@/routes/pages';
import track from '@/routes/track';

type Props = {
    openPeriods: {
        id: number;
        name: string;
        academic_year: string;
        opens_at: string;
        closes_at: string;
    }[];
    pages: { slug: string; title: string }[];
    canRegister: boolean;
};

export default function Welcome({
    openPeriods,
    pages: contentPages,
    canRegister,
}: Props) {
    const { t, locale } = useTrans();
    const { auth, organization } = usePage().props;

    const steps = [
        {
            icon: UserPlus,
            title: t('home.steps.account'),
            text: t('home.steps.account_text'),
        },
        {
            icon: ClipboardCheck,
            title: t('home.steps.form'),
            text: t('home.steps.form_text'),
        },
        {
            icon: FileUp,
            title: t('home.steps.documents'),
            text: t('home.steps.documents_text'),
        },
        {
            icon: Printer,
            title: t('home.steps.receipt'),
            text: t('home.steps.receipt_text'),
        },
    ];

    return (
        <>
            <Head title={t('home.title')} />

            <section className="border-b bg-linear-to-b from-[var(--sis-ribbon)] to-background">
                <div className="mx-auto grid w-full max-w-6xl gap-8 px-4 py-12 sm:py-16 lg:grid-cols-[1.4fr_1fr] lg:items-center">
                    <div className="space-y-5">
                        <p className="text-sm font-medium text-primary">
                            {organization}
                        </p>
                        <h1 className="text-3xl leading-tight font-semibold tracking-tight sm:text-4xl">
                            {t('home.headline')}
                        </h1>
                        <p className="max-w-2xl text-base text-muted-foreground sm:text-lg">
                            {t('home.subheadline')}
                        </p>
                        <div className="flex flex-wrap gap-3">
                            {auth.user ? (
                                <Button size="lg" asChild>
                                    <Link href={dashboard()}>
                                        {t('home.go_to_account')}
                                    </Link>
                                </Button>
                            ) : (
                                canRegister && (
                                    <Button size="lg" asChild>
                                        <Link href={register()}>
                                            {t('home.start_now')}
                                        </Link>
                                    </Button>
                                )
                            )}
                            <Button size="lg" variant="outline" asChild>
                                <Link href={track.create()}>
                                    <Search className="size-4" aria-hidden />
                                    {t('public.track_application')}
                                </Link>
                            </Button>
                        </div>
                    </div>

                    <div className="rounded-[var(--sis-surface-radius)] border bg-card p-5 shadow-xs">
                        <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold tracking-tight">
                            <CalendarClock
                                className="size-5 text-primary"
                                aria-hidden
                            />
                            {t('home.open_periods')}
                        </h2>
                        {openPeriods.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('home.no_open_periods')}
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {openPeriods.map((period) => (
                                    <li
                                        key={period.id}
                                        className="rounded-[var(--sis-field-radius)] border bg-[var(--sis-ribbon)] p-3"
                                    >
                                        <p className="font-medium">
                                            {period.name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {t('home.closes_on', {
                                                date: formatDate(
                                                    period.closes_at,
                                                    locale,
                                                ),
                                            })}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            </section>

            <section
                className="mx-auto w-full max-w-6xl px-4 py-12"
                aria-labelledby="how-it-works"
            >
                <h2
                    id="how-it-works"
                    className="mb-6 text-2xl font-semibold tracking-tight"
                >
                    {t('home.how_it_works')}
                </h2>
                <ol className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {steps.map((step, index) => (
                        <li
                            key={step.title}
                            className="rounded-[var(--sis-surface-radius)] border bg-card p-5"
                        >
                            <div className="mb-3 flex items-center gap-3">
                                <span className="flex size-9 items-center justify-center rounded-[var(--sis-button-radius)] bg-primary font-semibold text-primary-foreground">
                                    {index + 1}
                                </span>
                                <step.icon
                                    className="size-5 text-muted-foreground"
                                    aria-hidden
                                />
                            </div>
                            <h3 className="font-medium">{step.title}</h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {step.text}
                            </p>
                        </li>
                    ))}
                </ol>
            </section>

            {contentPages.length > 0 && (
                <section
                    className="mx-auto w-full max-w-6xl px-4 pb-12"
                    aria-labelledby="information"
                >
                    <h2
                        id="information"
                        className="mb-6 text-2xl font-semibold tracking-tight"
                    >
                        {t('home.information')}
                    </h2>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {contentPages.map((page) => (
                            <Link
                                key={page.slug}
                                href={pages.show(page.slug)}
                                className="rounded-[var(--sis-field-radius)] border bg-card p-4 font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                            >
                                {page.title}
                            </Link>
                        ))}
                    </div>
                </section>
            )}
        </>
    );
}
