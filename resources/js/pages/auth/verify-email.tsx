import { Form, Head, setLayoutProps, usePage } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    const { t } = useTrans();
    const { auth } = usePage().props;

    setLayoutProps({
        title: t('auth.verify_title'),
        description: t('auth.verify_description'),
    });

    return (
        <>
            <Head title={t('auth.verify_title')} />

            {auth.user && (
                <div className="rounded-md border bg-muted/50 p-3 text-center text-sm">
                    {t('auth.your_username_is')}{' '}
                    <bdi dir="ltr" className="font-semibold">
                        {auth.user.username}
                    </bdi>
                </div>
            )}

            {status === 'verification-link-sent' && (
                <div
                    role="status"
                    className="rounded-md border border-success/40 bg-success/10 p-3 text-center text-sm font-medium text-success"
                >
                    {t('auth.verification_sent')}
                </div>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button disabled={processing} variant="secondary">
                            {processing && <Spinner />}
                            {t('auth.resend_verification')}
                        </Button>

                        <TextLink
                            href={logout()}
                            className="mx-auto block text-sm"
                        >
                            {t('nav.logout')}
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}
