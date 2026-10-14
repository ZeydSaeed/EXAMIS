import { useForm } from '@inertiajs/react';
import { CheckCircle2, Clock, FileUp, XCircle } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';
import { formatFileSize } from '@/lib/format';
import applications from '@/routes/applications';
import type { ApplicationDocument, DocumentRequirement } from '@/types';

const extensionsFor: Record<string, string> = {
    'image/jpeg': '.jpg,.jpeg',
    'image/png': '.png',
    'application/pdf': '.pdf',
};

/**
 * One upload slot per required document type. The browser checks type and
 * size for quick feedback only; the server re-validates and scans every file.
 */
export function DocumentUploader({
    applicationUuid,
    requirement,
    current,
    disabled,
}: {
    applicationUuid: string;
    requirement: DocumentRequirement;
    current?: ApplicationDocument;
    disabled?: boolean;
}) {
    const { t, locale } = useTrans();
    const inputId = useId();
    const inputRef = useRef<HTMLInputElement>(null);
    const [clientError, setClientError] = useState<string | null>(null);
    const form = useForm<{ document_type: string; file: File | null }>({
        document_type: requirement.code,
        file: null,
    });

    const mimeTypes = requirement.mime_types ?? [];
    const maxBytes = (requirement.max_size_kb ?? 0) * 1024;
    const accept = mimeTypes
        .map((mime) => extensionsFor[mime] ?? '')
        .filter(Boolean)
        .join(',');

    const upload = (file: File) => {
        setClientError(null);

        if (mimeTypes.length > 0 && !mimeTypes.includes(file.type)) {
            setClientError(t('documents.wrong_type'));

            return;
        }

        if (maxBytes > 0 && file.size > maxBytes) {
            setClientError(
                t('documents.too_large', {
                    size: formatFileSize(maxBytes, locale),
                }),
            );

            return;
        }

        form.transform(() => ({ document_type: requirement.code, file }));
        form.post(applications.documents.store.url(applicationUuid), {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                if (inputRef.current) {
                    inputRef.current.value = '';
                }
            },
        });
    };

    const status = current?.status;

    return (
        <div
            className="rounded-xl border bg-card p-4"
            data-test={`document-${requirement.code}`}
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 space-y-1">
                    <p className="font-medium">
                        {requirement.name}
                        {requirement.required ? (
                            <span
                                className="text-destructive"
                                aria-label={t('common.required')}
                            >
                                {' '}
                                *
                            </span>
                        ) : (
                            <span className="ms-2 text-xs text-muted-foreground">
                                ({t('common.optional')})
                            </span>
                        )}
                    </p>
                    {requirement.description && (
                        <p className="text-sm text-muted-foreground">
                            {requirement.description}
                        </p>
                    )}
                    <p className="text-xs text-muted-foreground">
                        {t('documents.allowed', {
                            types: mimeTypes
                                .map((mime) =>
                                    (extensionsFor[mime] ?? mime)
                                        .replaceAll('.', '')
                                        .toUpperCase(),
                                )
                                .join('، '),
                            size: formatFileSize(maxBytes, locale),
                        })}
                    </p>
                </div>

                <div className="flex shrink-0 flex-col items-start gap-2 sm:items-end">
                    <input
                        ref={inputRef}
                        id={inputId}
                        type="file"
                        className="sr-only"
                        accept={accept}
                        disabled={disabled || form.processing}
                        onChange={(event) => {
                            const file = event.target.files?.[0];

                            if (file) {
                                upload(file);
                            }
                        }}
                        data-test={`file-${requirement.code}`}
                    />
                    <Button
                        asChild
                        variant={current ? 'outline' : 'default'}
                        size="sm"
                        disabled={disabled || form.processing}
                    >
                        <label
                            htmlFor={inputId}
                            className={
                                disabled
                                    ? 'pointer-events-none opacity-50'
                                    : 'cursor-pointer'
                            }
                        >
                            {form.processing ? (
                                <Spinner />
                            ) : (
                                <FileUp className="size-4" aria-hidden />
                            )}
                            {current
                                ? t('documents.replace')
                                : t('documents.upload')}
                        </label>
                    </Button>
                </div>
            </div>

            {form.progress && (
                <progress
                    className="mt-3 h-2 w-full overflow-hidden rounded"
                    value={form.progress.percentage}
                    max={100}
                    aria-label={t('documents.uploading')}
                />
            )}

            {current && (
                <div
                    className="mt-3 flex flex-wrap items-center gap-2 border-t pt-3 text-sm"
                    aria-live="polite"
                >
                    {status === 'verified' && (
                        <CheckCircle2
                            className="size-4 text-success"
                            aria-hidden
                        />
                    )}
                    {status === 'pending' && (
                        <Clock className="size-4 text-info" aria-hidden />
                    )}
                    {status === 'rejected' && (
                        <XCircle
                            className="size-4 text-destructive"
                            aria-hidden
                        />
                    )}
                    <span className="max-w-full truncate" dir="auto">
                        {current.original_name}
                    </span>
                    <span className="text-muted-foreground">
                        · {formatFileSize(current.size_bytes, locale)}
                    </span>
                    <span className="font-medium">
                        · {t(`documents.status.${status}`)}
                    </span>
                    {current.rejection_reason && (
                        <span className="w-full text-destructive">
                            {current.rejection_reason}
                        </span>
                    )}
                    {current.url && (
                        <a
                            href={current.url}
                            className="ms-auto text-primary underline-offset-4 hover:underline"
                        >
                            {t('documents.download')}
                        </a>
                    )}
                </div>
            )}

            <InputError
                className="mt-2"
                message={
                    clientError ??
                    form.errors.file ??
                    form.errors.document_type ??
                    (form.errors as Record<string, string | undefined>)
                        .application
                }
            />
        </div>
    );
}
