import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';
import { login } from '@/routes';
import pages from '@/routes/pages';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    const { t } = useTrans();

    setLayoutProps({
        title: t('auth.register_title'),
        description: t('auth.register_description'),
    });

    return (
        <>
            <Head title={t('auth.register')} />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    {t('auth.full_name')}
                                </Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="username">
                                    {t('auth.username')}
                                </Label>
                                <Input
                                    id="username"
                                    name="username"
                                    dir="ltr"
                                    required
                                    tabIndex={2}
                                    autoComplete="username"
                                    autoCapitalize="none"
                                    spellCheck={false}
                                    pattern="[a-zA-Z0-9._\-]{4,32}"
                                    aria-describedby="username-hint"
                                />
                                <p
                                    id="username-hint"
                                    className="text-xs text-muted-foreground"
                                >
                                    {t('auth.username_hint')}
                                </p>
                                <InputError message={errors.username} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">{t('auth.email')}</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    dir="ltr"
                                    required
                                    tabIndex={3}
                                    autoComplete="email"
                                    aria-describedby="email-hint"
                                />
                                <p
                                    id="email-hint"
                                    className="text-xs text-muted-foreground"
                                >
                                    {t('auth.email_shared_hint')}
                                </p>
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    {t('auth.password')}
                                </Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    name="password"
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
                                    required
                                    tabIndex={5}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id="terms"
                                        name="terms"
                                        value="1"
                                        tabIndex={6}
                                        required
                                    />
                                    <Label
                                        htmlFor="terms"
                                        className="leading-relaxed font-normal"
                                    >
                                        {t('auth.accept_terms_prefix')}{' '}
                                        <Link
                                            href={pages.show('terms')}
                                            className="underline underline-offset-4"
                                        >
                                            {t('public.links.terms')}
                                        </Link>{' '}
                                        {t('common.and')}{' '}
                                        <Link
                                            href={pages.show('privacy')}
                                            className="underline underline-offset-4"
                                        >
                                            {t('public.links.privacy')}
                                        </Link>
                                    </Label>
                                </div>
                                <InputError message={errors.terms} />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                tabIndex={7}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                {t('auth.create_account')}
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            {t('auth.have_account')}{' '}
                            <TextLink href={login()} tabIndex={8}>
                                {t('auth.login')}
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}
