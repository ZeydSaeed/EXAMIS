import { Form, Head, setLayoutProps } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';
import { login } from '@/routes';
import student from '@/routes/student';

/**
 * Second step of student sign-in on a browser that is not remembered: the
 * private access code proves the identifier belongs to the person typing it.
 */
export default function StudentCode() {
    const { t } = useTrans();

    setLayoutProps({
        title: t('student.code_title'),
        description: t('student.code_description'),
    });

    return (
        <>
            <Head title={t('student.code_title')} />
            <Form
                {...student.verify.form()}
                resetOnError={['access_code']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="access_code">
                                {t('student.access_code')}
                            </Label>
                            <Input
                                id="access_code"
                                name="access_code"
                                dir="ltr"
                                required
                                autoFocus
                                autoComplete="one-time-code"
                                autoCapitalize="characters"
                                spellCheck={false}
                                maxLength={20}
                                placeholder="XXXXX-XXXXX"
                                className="text-center font-mono tracking-widest uppercase"
                            />
                            <InputError message={errors.access_code} />
                        </div>

                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="trust_device"
                                name="trust_device"
                                value="1"
                            />
                            <Label
                                htmlFor="trust_device"
                                className="leading-relaxed font-normal"
                            >
                                {t('student.trust_device')}
                            </Label>
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                            data-test="verify-access-code"
                        >
                            {processing && <Spinner />}
                            {t('auth.login')}
                        </Button>

                        <p className="text-center text-sm text-muted-foreground">
                            {t('student.lost_code')}{' '}
                            <TextLink href={login()}>
                                {t('auth.back_to_login')}
                            </TextLink>
                        </p>
                    </div>
                )}
            </Form>
        </>
    );
}
