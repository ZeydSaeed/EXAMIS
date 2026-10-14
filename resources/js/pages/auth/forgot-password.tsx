import { Form, Head, setLayoutProps } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    const { t } = useTrans();

    setLayoutProps({
        title: t('auth.forgot_title'),
        description: t('auth.forgot_description'),
    });

    return (
        <>
            <Head title={t('auth.forgot_title')} />

            {status && (
                <div
                    role="status"
                    className="rounded-md border border-success/40 bg-success/10 p-3 text-center text-sm font-medium text-success"
                >
                    {status}
                </div>
            )}

            <div className="space-y-6">
                <Form {...email.form()}>
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="username">
                                    {t('auth.username')}
                                </Label>
                                <Input
                                    id="username"
                                    name="username"
                                    dir="ltr"
                                    autoComplete="username"
                                    autoCapitalize="none"
                                    autoFocus
                                    required
                                />
                                <InputError message={errors.username} />
                            </div>

                            <div className="my-6">
                                <Button
                                    className="w-full"
                                    disabled={processing}
                                    data-test="email-password-reset-link-button"
                                >
                                    {processing && <Spinner />}
                                    {t('auth.send_reset_link')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <div className="text-center text-sm text-muted-foreground">
                    <TextLink href={login()}>
                        {t('auth.back_to_login')}
                    </TextLink>
                </div>
            </div>
        </>
    );
}
