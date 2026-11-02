import { Form, Head, setLayoutProps } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';
import apply from '@/routes/apply';
import { store } from '@/routes/login';

type Props = {
    status?: string;
};

/**
 * Password sign-in for staff. Students sign in with their QR code.
 */
export default function StaffLogin({ status }: Props) {
    const { t } = useTrans();

    setLayoutProps({
        title: t('auth.staff_login_title'),
        description: t('auth.staff_login_description'),
    });

    return (
        <>
            <Head title={t('auth.staff_login')} />

            {status && (
                <div
                    role="status"
                    className="rounded-md border border-success/40 bg-success/10 p-3 text-center text-sm font-medium text-success"
                >
                    {status}
                </div>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">{t('auth.email')}</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    dir="ltr"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="username"
                                    autoCapitalize="none"
                                    spellCheck={false}
                                    aria-invalid={
                                        errors.email ? true : undefined
                                    }
                                    aria-describedby={
                                        errors.email ? 'email-error' : undefined
                                    }
                                />
                                <InputError
                                    id="email-error"
                                    message={errors.email}
                                />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">
                                        {t('auth.password')}
                                    </Label>
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                />
                                <InputError message={errors.password} />
                                <p className="text-xs text-muted-foreground">
                                    {t('auth.forgot_password_hint')}
                                </p>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={3}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                {t('auth.login')}
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            <TextLink href={apply.terms()} tabIndex={6}>
                                {t('auth.student_login_link')}
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}
