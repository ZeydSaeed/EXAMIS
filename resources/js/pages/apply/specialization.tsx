import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check } from 'lucide-react';
import { BrandBackdrop } from '@/components/examis/brand-backdrop';
import { ExamisMark } from '@/components/examis/examis-mark';
import { selectClassName } from '@/components/examis/field';
import InputError from '@/components/input-error';
import { LanguageSwitcher } from '@/components/language-switcher';
import { ScrollButtons } from '@/components/scroll-buttons';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';
import { cn } from '@/lib/utils';
import { start } from '@/routes';
import apply from '@/routes/apply';
import student from '@/routes/student';

type Branch = {
    code: string;
    name: string;
    specializations: { code: string; name: string }[];
};

type Props = {
    period: { name: string; academic_year: string } | null;
    branches: Branch[];
    selected: { branch_code: string | null; category_code: string | null };
    locked: boolean;
};

/**
 * Right after registration: the student picks the branch and the
 * specialization they apply for, from lists, with every branch and its
 * specializations laid out below. Styled like the application terms page.
 */
export default function Specialization({
    period,
    branches,
    selected,
    locked,
}: Props) {
    const { t, isRtl } = useTrans();
    const Forward = isRtl ? ArrowLeft : ArrowRight;
    const form = useForm({
        branch_code: selected.branch_code ?? '',
        category_code: selected.category_code ?? '',
    });

    const branch = branches.find((item) => item.code === form.data.branch_code);
    const specialization = branch?.specializations.find(
        (item) => item.code === form.data.category_code,
    );
    const canChoose = period !== null && !locked && branches.length > 0;

    const choose = (branchCode: string, categoryCode = '') =>
        form.setData({ branch_code: branchCode, category_code: categoryCode });

    return (
        <>
            <Head title={t('apply.specialization_title')} />

            <div className="relative isolate flex min-h-svh flex-col overflow-hidden bg-[var(--examis-pearl)] text-[var(--examis-navy)] dark:bg-[#0a1230] dark:text-white">
                <BrandBackdrop />

                <header className="mx-auto flex w-full max-w-3xl items-center justify-between gap-3 px-4 pt-4 sm:px-6">
                    <Link
                        href={start()}
                        className="flex items-center gap-2 font-semibold"
                    >
                        <ExamisMark surface="light" className="size-10" />
                        <span className="hidden sm:inline">
                            {t('start.title')}
                        </span>
                    </Link>
                    <LanguageSwitcher className="text-current hover:bg-black/5 dark:hover:bg-white/10" />
                </header>

                <main className="mx-auto w-full max-w-3xl flex-1 px-4 pt-8 pb-40 sm:px-6 sm:pt-12">
                    <div className="mb-6 space-y-1 motion-safe:animate-[start-rise_0.6s_ease-out_both]">
                        <p className="text-sm font-medium text-[var(--examis-green)] dark:text-emerald-300">
                            {t('start.department')}
                        </p>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                            {t('apply.specialization_title')}
                        </h1>
                        <p className="text-sm text-[var(--examis-navy)]/70 dark:text-white/65">
                            {period
                                ? t('apply.specialization_intro', {
                                      period: `${period.name} (${period.academic_year})`,
                                  })
                                : t('apply.no_open_period')}
                        </p>
                    </div>

                    {locked && (
                        <p
                            role="status"
                            className="mb-6 rounded-2xl border border-[var(--examis-gold)]/50 bg-[var(--examis-gold)]/10 p-4 text-sm"
                        >
                            {t('apply.specialization_locked')}
                        </p>
                    )}

                    {canChoose && (
                        <section
                            aria-label={t('apply.your_choice')}
                            className="mb-6 grid gap-4 rounded-3xl border border-white/60 bg-white/75 p-5 shadow-[var(--examis-navy)]/5 shadow-xl backdrop-blur-md motion-safe:animate-[start-rise_0.6s_0.1s_ease-out_both] sm:grid-cols-2 sm:p-6 dark:border-white/10 dark:bg-white/5"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="branch_code">
                                    {t('apply.branch')}
                                </Label>
                                <select
                                    id="branch_code"
                                    className={cn(selectClassName, 'h-11')}
                                    value={form.data.branch_code}
                                    onChange={(event) =>
                                        choose(event.target.value)
                                    }
                                    data-test="branch-select"
                                >
                                    <option value="">
                                        {t('apply.choose_branch')}
                                    </option>
                                    {branches.map((item) => (
                                        <option
                                            key={item.code}
                                            value={item.code}
                                        >
                                            {item.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.branch_code} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="category_code">
                                    {t('apply.specialization')}
                                </Label>
                                <select
                                    id="category_code"
                                    className={cn(selectClassName, 'h-11')}
                                    value={form.data.category_code}
                                    disabled={!branch}
                                    onChange={(event) =>
                                        form.setData(
                                            'category_code',
                                            event.target.value,
                                        )
                                    }
                                    data-test="specialization-select"
                                >
                                    <option value="">
                                        {branch
                                            ? t('apply.choose_specialization')
                                            : t('apply.choose_branch_first')}
                                    </option>
                                    {branch?.specializations.map((item) => (
                                        <option
                                            key={item.code}
                                            value={item.code}
                                        >
                                            {item.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={form.errors.category_code}
                                />
                            </div>
                        </section>
                    )}

                    {branches.length > 0 && (
                        <section
                            aria-labelledby="branch-details"
                            className="rounded-3xl border border-white/60 bg-white/75 p-2 shadow-[var(--examis-navy)]/5 shadow-xl backdrop-blur-md motion-safe:animate-[start-rise_0.6s_0.2s_ease-out_both] sm:p-3 dark:border-white/10 dark:bg-white/5"
                        >
                            <h2
                                id="branch-details"
                                className="px-4 pt-3 pb-1 font-semibold sm:px-5"
                            >
                                {t('apply.branch_details')}
                            </h2>
                            <ol className="divide-y divide-[var(--examis-navy)]/8 dark:divide-white/10">
                                {branches.map((item, index) => {
                                    const isBranch =
                                        item.code === form.data.branch_code;

                                    return (
                                        <li
                                            key={item.code}
                                            className={cn(
                                                'flex gap-4 rounded-2xl p-4 transition-colors sm:p-5',
                                                isBranch &&
                                                    'bg-[var(--examis-green)]/8 ring-1 ring-[var(--examis-green)]/30 dark:bg-emerald-400/10 dark:ring-emerald-300/30',
                                            )}
                                        >
                                            <span
                                                aria-hidden
                                                className="flex size-8 shrink-0 items-center justify-center rounded-xl bg-[var(--examis-gold)]/20 text-sm font-bold dark:bg-[var(--examis-gold)]/15 dark:text-[var(--examis-gold)]"
                                            >
                                                {(index + 1).toLocaleString(
                                                    isRtl ? 'ar-IQ' : 'en',
                                                )}
                                            </span>
                                            <div className="min-w-0 flex-1 space-y-3 pt-1">
                                                <h3 className="font-semibold">
                                                    {t('apply.branch_label', {
                                                        name: item.name,
                                                    })}
                                                </h3>
                                                <p className="text-xs text-[var(--examis-navy)]/60 dark:text-white/55">
                                                    {t(
                                                        'apply.specializations_count',
                                                        {
                                                            count: item
                                                                .specializations
                                                                .length,
                                                        },
                                                    )}
                                                </p>
                                                <ul className="flex flex-wrap gap-2">
                                                    {item.specializations.map(
                                                        (spec) => {
                                                            const isChosen =
                                                                isBranch &&
                                                                spec.code ===
                                                                    form.data
                                                                        .category_code;

                                                            return (
                                                                <li
                                                                    key={
                                                                        spec.code
                                                                    }
                                                                >
                                                                    <button
                                                                        type="button"
                                                                        disabled={
                                                                            !canChoose
                                                                        }
                                                                        aria-pressed={
                                                                            isChosen
                                                                        }
                                                                        onClick={() =>
                                                                            choose(
                                                                                item.code,
                                                                                spec.code,
                                                                            )
                                                                        }
                                                                        className={cn(
                                                                            'inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm transition-colors disabled:cursor-default',
                                                                            isChosen
                                                                                ? 'border-[var(--examis-navy)] bg-[var(--examis-navy)] text-white dark:border-[var(--examis-gold)] dark:bg-[var(--examis-gold)] dark:text-[var(--examis-navy)]'
                                                                                : 'border-[var(--examis-navy)]/15 bg-white/70 hover:border-[var(--examis-navy)]/40 dark:border-white/15 dark:bg-white/5 dark:hover:border-white/40',
                                                                        )}
                                                                    >
                                                                        {isChosen && (
                                                                            <Check
                                                                                className="size-3.5"
                                                                                aria-hidden
                                                                            />
                                                                        )}
                                                                        {
                                                                            spec.name
                                                                        }
                                                                    </button>
                                                                </li>
                                                            );
                                                        },
                                                    )}
                                                </ul>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ol>
                        </section>
                    )}
                </main>

                {/* Summary and Next, always in reach at the bottom. */}
                <div className="fixed inset-x-0 bottom-0 z-10 border-t border-[var(--examis-navy)]/10 bg-white/85 backdrop-blur-lg dark:border-white/10 dark:bg-[#0a1230]/85">
                    <div className="mx-auto flex w-full max-w-3xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <p className="text-sm" aria-live="polite">
                            {canChoose ? (
                                branch && specialization ? (
                                    <>
                                        <span className="text-[var(--examis-navy)]/60 dark:text-white/60">
                                            {t('apply.your_choice')}:{' '}
                                        </span>
                                        <span className="font-semibold">
                                            {branch.name} —{' '}
                                            {specialization.name}
                                        </span>
                                    </>
                                ) : (
                                    <span className="text-[var(--examis-navy)]/60 dark:text-white/60">
                                        {t('apply.choose_both')}
                                    </span>
                                )
                            ) : null}
                        </p>
                        {canChoose ? (
                            <Button
                                size="lg"
                                disabled={!specialization || form.processing}
                                onClick={() =>
                                    form.post(apply.specialization.store.url())
                                }
                                className={cn(
                                    'px-8 transition-all',
                                    specialization && 'shadow-lg',
                                )}
                                data-test="specialization-next"
                            >
                                {form.processing ? (
                                    <Spinner />
                                ) : (
                                    specialization && (
                                        <Check className="size-4" aria-hidden />
                                    )
                                )}
                                {t('apply.next')}
                                <Forward className="size-4" aria-hidden />
                            </Button>
                        ) : (
                            <Button size="lg" asChild className="px-8">
                                <Link href={student.card()}>
                                    {t('apply.next')}
                                    <Forward className="size-4" aria-hidden />
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
            </div>
            <ScrollButtons className="bottom-48 sm:bottom-32" />
        </>
    );
}
