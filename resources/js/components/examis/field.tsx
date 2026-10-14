import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';

/**
 * Label + control + hint + error, wired with aria attributes so screen
 * readers announce the hint and the validation message with the field.
 */
export function Field({
    id,
    label,
    required = false,
    hint,
    error,
    children,
    className = '',
}: {
    id: string;
    label: string;
    required?: boolean;
    hint?: string;
    error?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={`grid gap-2 ${className}`}>
            <Label htmlFor={id}>
                {label}
                {required && (
                    <span className="text-destructive" aria-hidden>
                        {' '}
                        *
                    </span>
                )}
            </Label>
            {children}
            {hint && (
                <p id={`${id}-hint`} className="text-xs text-muted-foreground">
                    {hint}
                </p>
            )}
            <InputError id={`${id}-error`} message={error} />
        </div>
    );
}

export function describedBy(
    id: string,
    hint?: string,
    error?: string,
): string | undefined {
    const ids = [
        hint ? `${id}-hint` : null,
        error ? `${id}-error` : null,
    ].filter(Boolean);

    return ids.length > 0 ? ids.join(' ') : undefined;
}

export const selectClassName =
    'flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive md:text-sm dark:bg-input/30';

export const textareaClassName =
    'flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm dark:bg-input/30';
