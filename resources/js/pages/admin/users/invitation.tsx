import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/hooks/use-trans';
import { formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';

type Props = {
    invitee: { name: string; email: string };
    invitationUrl: string;
    expiresAt: string;
};

/**
 * Shown once, in the response that issued the link: it is never stored in
 * clear, so leaving this page is the end of it.
 */
export default function StaffInvitationLink({
    invitee,
    invitationUrl,
    expiresAt,
}: Props) {
    const { t, locale } = useTrans();
    const [copied, setCopied] = useState(false);

    setLayoutProps({
        breadcrumbs: [{ title: t('nav.users'), href: admin.users.index() }],
    });

    return (
        <>
            <Head title={t('users.invitation_title')} />

            <div className="mx-auto grid w-full max-w-2xl gap-4 p-4 sm:p-6">
                <h1 className="text-lg font-semibold">
                    {t('users.invitation_title')}
                </h1>
                <p className="text-sm text-muted-foreground">
                    {t('users.invitation_once')}
                </p>
                <p className="text-sm">
                    <span className="font-medium">{invitee.name}</span>
                    {' · '}
                    <span dir="ltr">{invitee.email}</span>
                </p>
                <div className="flex gap-2">
                    <Input
                        readOnly
                        dir="ltr"
                        value={invitationUrl}
                        aria-label={t('users.invitation_title')}
                        data-test="invitation-url"
                        onFocus={(event) => event.target.select()}
                    />
                    <Button
                        type="button"
                        variant="outline"
                        aria-label={t('users.invitation_copy')}
                        onClick={() => {
                            void navigator.clipboard.writeText(invitationUrl);
                            setCopied(true);
                        }}
                    >
                        {copied ? <Check /> : <Copy />}
                    </Button>
                </div>
                <p className="text-sm text-muted-foreground">
                    {t('users.invitation_expires')}:{' '}
                    {formatDateTime(expiresAt, locale)}
                </p>
                <Button asChild variant="outline" className="w-fit">
                    <Link href={admin.users.index()}>{t('nav.users')}</Link>
                </Button>
            </div>
        </>
    );
}
