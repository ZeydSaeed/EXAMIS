import { Form, Head, setLayoutProps } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';
import { update } from '@/routes/password';

type Props = {
    token: string;
    username: string;
    passwordRules: string;
};

export default function ResetPassword({
    token,
    username,
    passwordRules,
}: Props) {
    const { t } = useTrans();

    setLayoutProps({
        title: t('auth.reset_title'),
        description: t('auth.reset_description'),
    });

    return (
        <>
            <Head title={t('auth.reset_title')} />

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token })}
                resetOnSuccess={['password', 'password_confirmation']}
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="username">
                                {t('auth.username')}
                            </Label>
                            <Input
                                id="username"
                                name="username"
                                dir="auto"
                                autoComplete="username"
                                defaultValue={username}
                                required
                            />
                            <InputError message={errors.username} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                {t('auth.new_password')}
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                autoFocus
                                passwordrules={passwordRules}
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                {t('auth.confirm_password')}
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>

                        <Button
                            type="submit"
                            className="mt-4 w-full"
                            disabled={processing}
                            data-test="reset-password-button"
                        >
                            {processing && <Spinner />}
                            {t('auth.reset_password')}
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}
