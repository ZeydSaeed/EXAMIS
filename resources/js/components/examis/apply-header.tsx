import { Link } from '@inertiajs/react';
import { ExamisMark } from '@/components/examis/examis-mark';
import { useTrans } from '@/hooks/use-trans';
import { start } from '@/routes';

/**
 * Top of the applying pages (terms, specialization, application form): the
 * application mark with the service title and academic year.
 */
export function ApplyHeader() {
    const { t } = useTrans();

    return (
        <header className="mx-auto w-full max-w-3xl px-4 pt-4 sm:px-6">
            <Link
                href={start()}
                className="flex items-center gap-2 font-semibold"
            >
                <ExamisMark surface="light" className="size-10 shrink-0" />
                <span className="grid leading-snug">
                    <span>{t('apply.terms_header')}</span>
                    <span className="text-sm font-medium text-[var(--examis-navy)]/70 dark:text-white/65">
                        {t('apply.terms_header_note')}
                    </span>
                </span>
            </Link>
        </header>
    );
}
