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
 * staff member's own WhatsApp on the student, who presses send there.
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
