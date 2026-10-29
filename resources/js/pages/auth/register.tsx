import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { MobileInput } from '@/components/examis/mobile-input';
import InputError from '@/components/input-error';
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

/**
 * Student registration: a username and a mobile number only (ADR-0009).
 * The number is not verified by SMS or WhatsApp.
 */
export default function Register() {
    const { t } = useTrans();

    setLayoutProps({
        title: t('auth.register_title'),
    });

    return (
        <>
            <Head title={t('auth.register')} />
            <Form
                {...store.form()}
                disableWhileProcessing
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
                                    dir="auto"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="username"
                                    autoCapitalize="none"
                                    spellCheck={false}
                                    minLength={3}
                                    maxLength={32}
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
                                <Label htmlFor="phone">
                                    {t('auth.mobile')}
                                </Label>
                                <MobileInput
                                    id="phone"
                                    name="phone"
                                    required
                                    tabIndex={2}
                                    autoComplete="tel"
                                    placeholder={t('auth.mobile_placeholder')}
                                    aria-describedby="phone-hint"
                                />
                                <p
                                    id="phone-hint"
                                    className="text-xs text-muted-foreground"
                                >
                                    {t('auth.mobile_hint')}
                                </p>
                                <InputError message={errors.phone} />
                            </div>

                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="trust_device"
                                    name="trust_device"
                                    value="1"
                                    defaultChecked
                                    tabIndex={3}
                                />
                                <Label
                                    htmlFor="trust_device"
                                    className="leading-relaxed font-normal"
                                >
                                    {t('student.trust_device')}
                                </Label>
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id="terms"
                                        name="terms"
                                        value="1"
                                        tabIndex={4}
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
                                tabIndex={5}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                {t('auth.create_account')}
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            {t('auth.have_account')}{' '}
                            <TextLink href={login()} tabIndex={6}>
                                {t('auth.login')}
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}
