import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';

/**
 * Confirmation for consequential actions. `children` is the trigger;
 * `body` can hold extra inputs (e.g. a reason) that belong to the action.
 */
export function ConfirmDialog({
    title,
    description,
    confirmLabel,
    destructive = false,
    processing = false,
    open,
    onOpenChange,
    onConfirm,
    body,
    children,
}: {
    title: string;
    description?: string;
    confirmLabel: string;
    destructive?: boolean;
    processing?: boolean;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    onConfirm: () => void;
    body?: ReactNode;
    children: ReactNode;
}) {
    const { t } = useTrans();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <DialogHeader className="text-start">
                    <DialogTitle>{title}</DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>
                {body}
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="outline" disabled={processing}>
                            {t('common.cancel')}
                        </Button>
                    </DialogClose>
                    <Button
                        variant={destructive ? 'destructive' : 'default'}
                        onClick={onConfirm}
                        disabled={processing}
                        data-test="confirm-action"
                    >
                        {processing && <Spinner />}
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
