import {
    Head,
    router,
    setLayoutProps,
    useForm,
    usePage,
} from '@inertiajs/react';
import { Trash2, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ConfirmDialog } from '@/components/examis/confirm-dialog';
import {
    Field,
    selectClassName,
    textareaClassName,
} from '@/components/examis/field';
import { PageContainer, PageHeader } from '@/components/examis/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';
import admin from '@/routes/admin';
import type { DatabaseColumn, DatabaseTable } from './types';

type Props = {
    table: DatabaseTable;
    columns: DatabaseColumn[];
    row: Record<string, string | null> | null;
    rowKey: string | null;
    /** The row as it was read; a save based on an older copy is refused. */
    fingerprint?: string | null;
    student: { student_number: string } | null;
};

export default function DatabaseRow({
    table,
    columns,
    row,
    rowKey,
    fingerprint = null,
    student,
}: Props) {
    const { t } = useTrans();
    const { auth, status, errors } = usePage<{ status?: string | null }>()
        .props;
    const canManage =
        auth.user.permissions.includes('database.manage') && table.writable;
    const isEditing = row !== null && rowKey !== null;
    const [deleting, setDeleting] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [confirmText, setConfirmText] = useState('');

    const form = useForm<{
        key: string | null;
        fingerprint: string | null;
        values: Record<string, string>;
    }>({
        key: rowKey,
        fingerprint,
        values: Object.fromEntries(
            columns
                .filter(
                    (column) =>
                        !column.secret && !(column.protected && row !== null),
                )
                .map((column) => [column.name, row?.[column.name] ?? '']),
        ),
    });
    const fieldErrors = form.errors as Record<string, string | undefined>;

    setLayoutProps({
        breadcrumbs: [
            { title: t('nav.database'), href: admin.database.index() },
            { title: table.label, href: admin.database.table(table.name) },
            {
                title: isEditing ? t('db.edit_row') : t('db.new_row'),
                href: isEditing
                    ? admin.database.edit(table.name, {
                          query: { key: rowKey },
                      })
                    : admin.database.create(table.name),
            },
        ],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (isEditing) {
            form.put(admin.database.update.url(table.name), {
                preserveScroll: true,
            });
        } else {
            form.post(admin.database.store.url(table.name));
        }
    };

    const destroy = () => {
        router.delete(admin.database.destroy.url(table.name), {
            data: { key: rowKey, confirm: confirmText },
            onStart: () => setDeleting(true),
            onFinish: () => setDeleting(false),
            onSuccess: () => setConfirmOpen(false),
        });
    };

    const hint = (column: DatabaseColumn): string | undefined => {
        if (column.secret) {
            return t('db.secret');
        }

        if (column.key && isEditing) {
            return t('db.key_column');
        }

        if (column.protected && isEditing) {
            return t('db.protected_column');
        }

        const parts = [
            column.kind === 'datetime' ? t('db.utc_hint') : null,
            column.kind === 'boolean' ? t('db.boolean_hint') : null,
            !isEditing && (column.auto || column.has_default)
                ? t('db.auto_hint')
                : column.nullable
                  ? t('db.empty_is_null')
                  : null,
        ].filter(Boolean);

        return parts.length > 0 ? parts.join(' · ') : undefined;
    };

    const control = (column: DatabaseColumn) => {
        const id = `col-${column.name}`;
        const locked = !canManage || column.secret || (column.key && isEditing);

        if (column.secret || (column.protected && isEditing)) {
            return (
                <Input
                    id={id}
                    dir="auto"
                    value={row?.[column.name] ?? ''}
                    disabled
                />
            );
        }

        const value = form.data.values[column.name] ?? '';
        const set = (next: string) =>
            form.setData('values', {
                ...form.data.values,
                [column.name]: next,
            });

        if (column.kind === 'boolean') {
            return (
                <select
                    id={id}
                    dir="ltr"
                    className={selectClassName}
                    value={value}
                    disabled={locked}
                    onChange={(event) => set(event.target.value)}
                >
                    {(column.nullable || value === '') && (
                        <option value="">NULL</option>
                    )}
                    <option value="true">true</option>
                    <option value="false">false</option>
                </select>
            );
        }

        if (
            column.kind === 'json' ||
            (column.kind === 'text' &&
                (value.length > 80 || value.includes('\n')))
        ) {
            return (
                <textarea
                    id={id}
                    dir="auto"
                    className={`${textareaClassName} font-mono text-xs`}
                    rows={Math.min(12, Math.max(3, value.split('\n').length))}
                    value={value}
                    disabled={locked}
                    onChange={(event) => set(event.target.value)}
                />
            );
        }

        return (
            <Input
                id={id}
                dir={column.kind === 'text' ? 'auto' : 'ltr'}
                value={value}
                disabled={locked}
                placeholder={
                    column.kind === 'datetime'
                        ? '2026-11-08 15:30:00'
                        : column.kind === 'date'
                          ? '2026-11-08'
                          : undefined
                }
                onChange={(event) => set(event.target.value)}
            />
        );
    };

    return (
        <>
            <Head
                title={`${table.label} · ${isEditing ? t('db.edit_row') : t('db.new_row')}`}
            />
            <PageContainer className="max-w-4xl">
                <PageHeader
                    title={isEditing ? t('db.edit_row') : t('db.new_row')}
                    description={
                        <>
                            {table.label}{' '}
                            <span dir="ltr" className="font-mono">
                                ({table.name})
                            </span>
                        </>
                    }
                    actions={
                        canManage && isEditing ? (
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={() => setConfirmOpen(true)}
                            >
                                <Trash2 aria-hidden />
                                {student
                                    ? t('db.delete_student')
                                    : t('db.delete')}
                            </Button>
                        ) : undefined
                    }
                />

                {canManage && (
                    <p className="flex items-start gap-2 rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm">
                        <TriangleAlert
                            className="mt-0.5 size-4 shrink-0 text-amber-600"
                            aria-hidden
                        />
                        {t('db.warning')}
                    </p>
                )}

                {status && (
                    <p
                        role="status"
                        className="rounded-md border border-emerald-600/30 bg-emerald-600/10 px-3 py-2 text-sm"
                    >
                        {status}
                    </p>
                )}

                <InputError message={fieldErrors.database ?? errors.database} />

                <form onSubmit={submit} className="space-y-6">
                    <section className="grid gap-5 rounded-xl border bg-card p-4 sm:grid-cols-2 sm:p-6">
                        {columns.map((column) => (
                            <Field
                                key={column.name}
                                id={`col-${column.name}`}
                                label={`${column.name}  (${column.type})`}
                                hint={hint(column)}
                                error={fieldErrors[column.name]}
                                required={
                                    !column.nullable &&
                                    !column.secret &&
                                    !(column.key && isEditing) &&
                                    !(
                                        !isEditing &&
                                        (column.auto || column.has_default)
                                    )
                                }
                                className={
                                    column.kind === 'json' ||
                                    (form.data.values[column.name] ?? '')
                                        .length > 80
                                        ? 'sm:col-span-2'
                                        : ''
                                }
                            >
                                {control(column)}
                            </Field>
                        ))}
                    </section>

                    {canManage && (
                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                {t('common.save')}
                            </Button>
                        </div>
                    )}
                </form>
            </PageContainer>

            <ConfirmDialog
                open={confirmOpen}
                onOpenChange={setConfirmOpen}
                title={student ? t('db.delete_student') : t('db.delete')}
                description={
                    student
                        ? t('db.delete_student_text', {
                              number: student.student_number,
                          })
                        : t('db.delete_confirm')
                }
                confirmLabel={student ? t('db.delete_student') : t('db.delete')}
                cancelLabel={t('db.cancel')}
                destructive
                processing={deleting}
                onConfirm={destroy}
                body={
                    student ? (
                        <Field
                            id="confirm-student"
                            label={t('db.confirm_label')}
                            error={errors.confirm}
                        >
                            <Input
                                id="confirm-student"
                                dir="ltr"
                                inputMode="numeric"
                                autoComplete="off"
                                value={confirmText}
                                onChange={(event) =>
                                    setConfirmText(event.target.value)
                                }
                            />
                        </Field>
                    ) : (
                        <InputError message={errors.database} />
                    )
                }
            />
        </>
    );
}
