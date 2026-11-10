import { Form, Head, router, setLayoutProps } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import StaffInvitationController from '@/actions/App/Http/Controllers/Auth/StaffInvitationController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';

type Props = {
    passwordRules: string;
    invalid?: boolean;
    invitee?: { name: string; email: string } | null;
};

/**
 * The token sits in the URL fragment, so the server never sees it in a GET.
 * It is lifted out of the address bar at once and posted from memory.
 */
function useFragmentToken(): string {
    const [token] = useState(() => window.location.hash.slice(1));

    useEffect(() => {
        if (window.location.hash === '') {
            return;
        }

        window.history.replaceState(null, '', window.location.pathname);

        router.post(
            StaffInvitationController.inspect.url(),
            { token },
            { preserveState: true, replace: true },
        );
    }, [token]);

    return token;
}

export default function StaffInvitation({
    passwordRules,
    invalid = false,
    invitee,
}: Props) {
    const { t } = useTrans();
    const token = useFragmentToken();

    setLayoutProps({
        title: t('auth.invitation_title'),
        description: t('auth.invitation_description'),
    });

    return (
        <>
            <Head title={t('auth.invitation_title')} />

            {(invalid || token === '') && (
                <p
                    role="alert"
                    className="text-center text-sm text-destructive"
                >
                    {t('auth.invitation_unusable')}
                </p>
            )}

            {invitee && !invalid && (
                <Form
                    {...StaffInvitationController.store.form()}
                    transform={(data) => ({ ...data, token })}
                    resetOnError
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="text-sm">
                                <p className="font-medium">{invitee.name}</p>
                                <p className="text-muted-foreground" dir="ltr">
                                    {invitee.email}
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    {t('auth.new_password')}
                                </Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoFocus
                                    autoComplete="new-password"
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
                                    required
                                    autoComplete="new-password"
                                    passwordrules={passwordRules}
                                />
                            </div>

                            <InputError message={errors.token} />

                            <Button
                                type="submit"
                                disabled={processing}
                                data-test="accept-invitation-button"
                            >
                                {processing && <Spinner />}
                                {t('auth.invitation_submit')}
                            </Button>
                        </>
                    )}
                </Form>
            )}
        </>
    );
}
