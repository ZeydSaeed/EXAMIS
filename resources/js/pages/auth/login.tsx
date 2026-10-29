import { Form, Head, setLayoutProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Hash, QrCode, Smartphone, UserRound } from 'lucide-react';
import { useState } from 'react';
import { MobileInput } from '@/components/examis/mobile-input';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';
import { cn } from '@/lib/utils';
import { register } from '@/routes';
import staff from '@/routes/staff';
import student from '@/routes/student';

type Props = {
    status?: string;
    canRegister: boolean;
};

type Method = 'username' | 'mobile' | 'student_number' | 'qr';

/**
 * Student sign-in with any one identifier (ADR-0009). Each tab posts the
 * same `identifier` field; the server decides which account it names and
 * asks for the access code unless this browser is remembered.
 */
export default function Login({ status, canRegister }: Props) {
    const { t } = useTrans();
    const [method, setMethod] = useState<Method>('username');

    setLayoutProps({
        title: t('auth.login_title'),
        description: t('auth.login_description'),
    });

    const methods: { value: Method; icon: LucideIcon; label: string }[] = [
        { value: 'username', icon: UserRound, label: t('auth.username') },
        { value: 'mobile', icon: Smartphone, label: t('auth.mobile') },
        {
            value: 'student_number',
            icon: Hash,
            label: t('student.student_number'),
        },
        { value: 'qr', icon: QrCode, label: t('student.qr_code') },
    ];

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

            <div
                role="tablist"
                aria-label={t('auth.login_method')}
                className="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1 sm:grid-cols-4"
            >
                {methods.map(({ value, icon: Icon, label }) => (
                    <button
                        key={value}
                        type="button"
                        role="tab"
                        aria-selected={method === value}
                        onClick={() => setMethod(value)}
                        data-test={`login-method-${value}`}
                        className={cn(
                            'flex items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-sm transition-colors',
                            method === value
                                ? 'bg-background font-medium shadow-xs'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        <Icon className="size-4" aria-hidden />
                        {label}
                    </button>
                ))}
            </div>

            {method === 'qr' ? (
                <div className="space-y-3 rounded-lg border bg-card p-4 text-sm leading-relaxed">
                    <p className="font-medium">{t('student.qr_login_title')}</p>
                    <p className="text-muted-foreground">
                        {t('student.qr_login_text')}
                    </p>
                </div>
            ) : (
                <Form {...student.login.form()} className="flex flex-col gap-6">
                    {({ processing, errors }) => (
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                {method === 'username' && (
                                    <>
                                        <Label htmlFor="identifier">
                                            {t('auth.username')}
                                        </Label>
                                        <Input
                                            key="username"
                                            id="identifier"
                                            name="identifier"
                                            dir="auto"
                                            required
                                            autoFocus
                                            autoComplete="username"
                                            autoCapitalize="none"
                                            spellCheck={false}
                                            placeholder={t(
                                                'auth.username_placeholder',
                                            )}
                                        />
                                    </>
                                )}
                                {method === 'mobile' && (
                                    <>
                                        <Label htmlFor="identifier">
                                            {t('auth.mobile')}
                                        </Label>
                                        <MobileInput
                                            key="mobile"
                                            id="identifier"
                                            name="identifier"
                                            required
                                            autoFocus
                                            autoComplete="tel"
                                            placeholder={t(
                                                'auth.mobile_placeholder',
                                            )}
                                            aria-describedby="mobile-hint"
                                        />
                                        <p
                                            id="mobile-hint"
                                            className="text-xs text-muted-foreground"
                                        >
                                            {t('auth.mobile_login_hint')}
                                        </p>
                                    </>
                                )}
                                {method === 'student_number' && (
                                    <>
                                        <Label htmlFor="identifier">
                                            {t('student.student_number')}
                                        </Label>
                                        <Input
                                            key="student_number"
                                            id="identifier"
                                            name="identifier"
                                            dir="ltr"
                                            required
                                            autoFocus
                                            inputMode="numeric"
                                            pattern="[0-9]{5}"
                                            maxLength={5}
                                            placeholder={t(
                                                'student.student_number_placeholder',
                                            )}
                                        />
                                    </>
                                )}
                                <InputError message={errors.identifier} />
                            </div>

                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    value="1"
                                />
                                <Label htmlFor="remember">
                                    {t('auth.remember_me')}
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                {t('auth.continue')}
                            </Button>
                        </div>
                    )}
                </Form>
            )}

            <div className="space-y-2 text-center text-sm text-muted-foreground">
                {canRegister && (
                    <p>
                        {t('auth.no_account')}{' '}
                        <TextLink href={register()}>
                            {t('auth.register')}
                        </TextLink>
                    </p>
                )}
                <p>
                    <TextLink href={staff.login()}>
                        {t('auth.staff_login_link')}
                    </TextLink>
                </p>
            </div>
        </>
    );
}
