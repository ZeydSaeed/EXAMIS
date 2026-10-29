import { useForm, usePage } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/examis/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-trans';
import admin from '@/routes/admin';

export type StudentAccount = {
    id: number;
    username: string;
    student_number: number | null;
    phone: string | null;
};

/**
 * The applicant's sign-in identity on the staff review page, with the
 * in-person access recovery (new access code) for administrators.
 */
export function StudentAccountCard({
    student,
    canResetAccess,
}: {
    student: StudentAccount;
    canResetAccess: boolean;
}) {
    const { t } = useTrans();
    const { flash } = usePage();
    const [open, setOpen] = useState(false);
    const form = useForm({});
    const issued = (
        flash as {
            student_access_code?: { user_id: number; code: string };
        }
    ).student_access_code;

    return (
        <section
            className="rounded-xl border bg-card p-4"
            aria-labelledby="student-account"
        >
            <h2 id="student-account" className="mb-3 font-semibold">
                {t('student.account')}
            </h2>
            <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                <dt className="text-muted-foreground">
                    {t('student.student_number')}
                </dt>
                <dd>
                    <bdi dir="ltr" className="font-semibold">
                        {student.student_number ?? '—'}
                    </bdi>
                </dd>
                <dt className="text-muted-foreground">{t('auth.username')}</dt>
                <dd>
                    <bdi>{student.username}</bdi>
                </dd>
                <dt className="text-muted-foreground">{t('auth.mobile')}</dt>
                <dd>
                    <bdi dir="ltr">{student.phone ?? '—'}</bdi>
                </dd>
            </dl>

            {issued && issued.user_id === student.id && (
                <div
                    role="status"
                    className="mt-4 rounded-md border border-warning/40 bg-warning/10 p-3 text-sm"
                >
                    <p>{t('student.staff_code_issued')}</p>
                    <p
                        dir="ltr"
                        className="mt-2 text-center font-mono text-lg font-bold tracking-widest"
                        data-test="issued-access-code"
                    >
                        {issued.code}
                    </p>
                </div>
            )}

            {canResetAccess && (
                <ConfirmDialog
                    title={t('student.reset_access')}
                    description={t('student.reset_access_confirm')}
                    confirmLabel={t('student.reset_access')}
                    destructive
                    open={open}
                    onOpenChange={setOpen}
                    processing={form.processing}
                    onConfirm={() =>
                        form.post(admin.students.accessCode.url(student.id), {
                            preserveScroll: true,
                            onSuccess: () => setOpen(false),
                        })
                    }
                >
                    <Button
                        variant="outline"
                        size="sm"
                        className="mt-4 w-full"
                        data-test="reset-student-access"
                    >
                        <KeyRound className="size-4" aria-hidden />
                        {t('student.reset_access')}
                    </Button>
                </ConfirmDialog>
            )}
        </section>
    );
}
