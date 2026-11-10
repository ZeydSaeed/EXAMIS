import { usePage } from '@inertiajs/react';
import { Check, Copy, MessageCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTrans } from '@/hooks/use-trans';
import { whatsappUrl } from '@/lib/decision-message';

/**
 * The last step of a decision. Nothing is sent by the system: this opens the
 * staff member's own WhatsApp on the student, who presses send there. A link
 * can only name the recipient, so the number on the account is shown for the
 * staff member to check against the WhatsApp on their device.
 */
export function SendMessageDialog({
    message,
    phone,
    open,
    onOpenChange,
}: {
    message: string;
    phone: string | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTrans();
    const { auth } = usePage().props;
    const [copied, copy] = useClipboard();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader className="text-start">
                    <DialogTitle>{t('admin.message_ready')}</DialogTitle>
                    <DialogDescription>
                        {phone
                            ? t('admin.message_ready_text')
                            : t('admin.message_no_phone')}
                    </DialogDescription>
                </DialogHeader>
                {phone && (
                    <div
                        className="grid gap-1 text-sm"
                        data-test="message-numbers"
                    >
                        <p>
                            {t('admin.message_to')}{' '}
                            <span
                                dir="ltr"
                                className="font-medium tabular-nums"
                            >
                                +{phone}
                            </span>
                        </p>
                        {auth.user.phone ? (
                            <p>
                                {t('admin.message_from')}{' '}
                                <span
                                    dir="ltr"
                                    className="font-medium tabular-nums"
                                >
                                    {auth.user.phone}
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    {t('admin.message_from_hint')}
                                </span>
                            </p>
                        ) : (
                            <p className="text-muted-foreground">
                                {t('admin.message_no_sender')}
                            </p>
                        )}
                    </div>
                )}
                <p
                    className="max-h-64 overflow-y-auto rounded-md bg-muted p-3 text-sm whitespace-pre-line"
                    data-test="decision-message"
                >
                    {message}
                </p>
                <div className="flex flex-wrap gap-2">
                    {phone && (
                        <Button asChild>
                            <a
                                href={whatsappUrl(phone, message)}
                                target="_blank"
                                rel="noopener noreferrer"
                                data-test="whatsapp-link"
                            >
                                <MessageCircle aria-hidden />
                                {t('admin.send_whatsapp')}
                            </a>
                        </Button>
                    )}
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => void copy(message)}
                    >
                        {copied === message ? <Check /> : <Copy />}
                        {t('admin.copy_message')}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
