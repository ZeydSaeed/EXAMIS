import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/examis/confirm-dialog';
import { DataTableFrame } from '@/components/examis/data-table-frame';
import {
    Field,
    selectClassName,
    textareaClassName,
} from '@/components/examis/field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/hooks/use-trans';
import admin from '@/routes/admin';

export type MessageTemplate = {
    id: number;
    title: string;
    status: string;
    status_label: string;
    body: string;
};

type Option = { value: string; label: string };

function TemplateDialog({
    template,
    statuses,
    placeholders,
    open,
    onOpenChange,
}: {
    template: MessageTemplate | null;
    statuses: Option[];
    placeholders: string[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTrans();
    const form = useForm({
        title: template?.title ?? '',
        status: template?.status ?? statuses[0]?.value ?? '',
        body: template?.body ?? '',
    });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader className="text-start">
                    <DialogTitle>
                        {template
                            ? t('content.edit_message')
                            : t('content.add_message')}
                    </DialogTitle>
                </DialogHeader>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();

                        const options = {
                            preserveScroll: true,
                            onSuccess: () => onOpenChange(false),
                        };

                        if (template) {
                            form.put(
                                admin.messageTemplates.update.url(template.id),
                                options,
                            );
                        } else {
                            form.post(
                                admin.messageTemplates.store.url(),
                                options,
                            );
                        }
                    }}
                >
                    <Field
                        id="message-title"
                        label={t('content.message_title')}
                        error={form.errors.title}
                        required
                    >
                        <Input
                            id="message-title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                        />
                    </Field>
                    <Field
                        id="message-status"
                        label={t('applications.status')}
                        error={form.errors.status}
                        required
                    >
                        <select
                            id="message-status"
                            className={selectClassName}
                            value={form.data.status}
                            onChange={(event) =>
                                form.setData('status', event.target.value)
                            }
                        >
                            {statuses.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        id="message-body"
                        label={t('content.message_body')}
                        error={form.errors.body}
                        required
                    >
                        <textarea
                            id="message-body"
                            className={textareaClassName}
                            rows={9}
                            maxLength={1500}
                            value={form.data.body}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                        />
                    </Field>
                    <div className="grid gap-2">
                        <p className="text-sm text-muted-foreground">
                            {t('content.placeholders_hint')}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {placeholders.map((placeholder) => (
                                <Button
                                    key={placeholder}
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        form.setData(
                                            'body',
                                            `${form.data.body}{${placeholder}}`,
                                        )
                                    }
                                >
                                    {`{${placeholder}}`}
                                </Button>
                            ))}
                        </div>
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        {t('common.save')}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Ready-made decision messages. Staff pick one when they decide an
 * application; changing one here never touches a message already sent.
 */
export function MessageTemplatesPanel({
    templates,
    statuses,
    placeholders,
}: {
    templates: MessageTemplate[];
    statuses: Option[];
    placeholders: string[];
}) {
    const { t } = useTrans();
    const [editing, setEditing] = useState<MessageTemplate | 'new' | null>(
        null,
    );

    return (
        <section aria-labelledby="messages" className="grid gap-3">
            <div className="flex items-center justify-between gap-3">
                <h2 id="messages" className="font-semibold">
                    {t('content.messages')}
                </h2>
                <Button
                    size="sm"
                    onClick={() => setEditing('new')}
                    data-test="add-message"
                >
                    <Plus aria-hidden />
                    {t('content.add_message')}
                </Button>
            </div>

            <DataTableFrame className="examis-data-table--plain">
                <table className="min-w-[40rem]">
                    <caption className="sr-only">
                        {t('content.messages')}
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col" className="w-12 text-center!">
                                {t('admin.serial')}
                            </th>
                            <th scope="col">{t('content.message_title')}</th>
                            <th scope="col">{t('applications.status')}</th>
                            <th scope="col">{t('content.message_body')}</th>
                            <th scope="col">
                                <span className="sr-only">
                                    {t('admin.actions')}
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {templates.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="text-center! text-muted-foreground"
                                >
                                    {t('admin.no_results')}
                                </td>
                            </tr>
                        ) : (
                            templates.map((template, index) => (
                                <tr key={template.id}>
                                    <td className="text-center! text-muted-foreground tabular-nums">
                                        {index + 1}
                                    </td>
                                    <td className="whitespace-nowrap">
                                        {template.title}
                                    </td>
                                    <td className="whitespace-nowrap">
                                        {template.status_label}
                                    </td>
                                    <td className="text-sm whitespace-pre-line text-muted-foreground">
                                        {template.body}
                                    </td>
                                    <td className="whitespace-nowrap">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            aria-label={t(
                                                'content.edit_message',
                                            )}
                                            title={t('content.edit_message')}
                                            onClick={() => setEditing(template)}
                                        >
                                            <Pencil aria-hidden />
                                        </Button>
                                        <ConfirmDialog
                                            title={t('content.delete_message')}
                                            description={template.title}
                                            confirmLabel={t(
                                                'content.delete_message',
                                            )}
                                            destructive
                                            onConfirm={() =>
                                                router.delete(
                                                    admin.messageTemplates.destroy.url(
                                                        template.id,
                                                    ),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                aria-label={t(
                                                    'content.delete_message',
                                                )}
                                                title={t(
                                                    'content.delete_message',
                                                )}
                                            >
                                                <Trash2 aria-hidden />
                                            </Button>
                                        </ConfirmDialog>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </DataTableFrame>

            {editing !== null && (
                <TemplateDialog
                    key={editing === 'new' ? 'new' : editing.id}
                    template={editing === 'new' ? null : editing}
                    statuses={statuses}
                    placeholders={placeholders}
                    open
                    onOpenChange={(open) => !open && setEditing(null)}
                />
            )}
        </section>
    );
}
