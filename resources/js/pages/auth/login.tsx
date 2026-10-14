import { Form, Head, setLayoutProps } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
};

export default function Login({
    status,
    canResetPassword,
    canRegister,
}: Props) {
    const { t } = useTrans();

    setLayoutProps({
        title: t('auth.login_title'),
        description: t('auth.login_description'),
    });

    return (
        <>
            <Head title={t('auth.login')} />

            {status && (
                <div
                    role="status"
                    className="rounded-md border border-success/40 bg-success/10 p-3 text-center text-sm font-medium text-success"
                >
                    {status}
                </div>
            )}

            <PasskeyVerify
                label={t('auth.passkey_sign_in')}
                loadingLabel={t('common.processing')}
                separator={t('auth.or_continue_with_username')}
            />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="username">
                                    {t('auth.username')}
                                </Label>
                                <Input
                                    id="username"
                                    name="username"
                                    dir="ltr"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="username"
                                    autoCapitalize="none"
                                    spellCheck={false}
                                    aria-invalid={
                                        errors.username ? true : undefined
                                    }
                                    aria-describedby={
                                        errors.username
                                            ? 'username-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="username-error"
                                    message={errors.username}
                                />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">
                                        {t('auth.password')}
                                    </Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ms-auto text-sm"
                                            tabIndex={5}
                                        >
                                            {t('auth.forgot_password')}
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">
                                    {t('auth.remember_me')}
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                {t('auth.login')}
                            </Button>
                        </div>

                        {canRegister && (
                            <div className="text-center text-sm text-muted-foreground">
                                {t('auth.no_account')}{' '}
                                <TextLink href={register()} tabIndex={5}>
                                    {t('auth.register')}
                                </TextLink>
                            </div>
                        )}
                    </>
                )}
            </Form>
        </>
    );
}
