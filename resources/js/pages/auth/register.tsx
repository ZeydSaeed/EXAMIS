import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { MobileInput } from '@/components/examis/mobile-input';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';
import { store } from '@/routes/register';

/**
 * Student registration: a mobile number only (ADR-0010). Each registration
 * is a new one-time account. The number is not verified by SMS or WhatsApp.
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
                                <Label htmlFor="phone">
                                    {t('auth.mobile')}
                                </Label>
                                <MobileInput
                                    id="phone"
                                    name="phone"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="tel"
                                    placeholder={t('auth.mobile_placeholder')}
                                    className="placeholder:text-muted-foreground/50"
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

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                tabIndex={2}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                {t('auth.create_account')}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}
