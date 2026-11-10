import { ShieldAlert } from 'lucide-react';
import { useTrans } from '@/hooks/use-trans';

/**
 * The student's access code, shown once right after it is issued
 * (ADR-0009).
 */
export function AccessCodeNotice({ code }: { code: string }) {
    const { t } = useTrans();

    return (
        <section
            role="status"
            className="rounded-xl border border-warning/50 bg-warning/10 p-4 sm:p-6"
            aria-labelledby="new-code"
        >
            <h2 id="new-code" className="flex items-center gap-2 font-semibold">
                <ShieldAlert className="size-5" aria-hidden />
                {t('student.new_code_title')}
            </h2>
            <p
                dir="ltr"
                className="my-4 text-center font-mono text-2xl font-bold tracking-[0.3em] sm:text-3xl"
                data-test="access-code"
            >
                {code}
            </p>
            <p className="text-sm leading-relaxed">
                {t('student.new_code_text')}
            </p>
        </section>
    );
}
