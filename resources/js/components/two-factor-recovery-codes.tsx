import { LockKeyhole } from 'lucide-react';
import AlertError from '@/components/alert-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Props = {
    issued: boolean;
    remaining: number;
    recoveryCodesList: string[];
    issueRecoveryCodes: () => Promise<void>;
    errors: string[];
};

/**
 * Recovery codes exist in clear for this one view. Only their hashes are
 * stored, so they cannot be shown again; an administrator's reset starts over.
 */
export default function TwoFactorRecoveryCodes({
    issued,
    remaining,
    recoveryCodesList,
    issueRecoveryCodes,
    errors,
}: Props) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex gap-3">
                    <LockKeyhole className="size-4" aria-hidden="true" />
                    2FA recovery codes
                </CardTitle>
                <CardDescription>
                    Recovery codes let you regain access if you lose your 2FA
                    device. Each works once. Store them in a secure password
                    manager: they are shown only one time.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {errors.length > 0 && <AlertError errors={errors} />}

                {recoveryCodesList.length > 0 ? (
                    <div
                        className="grid gap-1 rounded-lg bg-muted p-4 font-mono text-sm select-text"
                        role="list"
                        aria-label="Recovery codes"
                        data-test="recovery-codes"
                    >
                        {recoveryCodesList.map((code) => (
                            <div key={code} role="listitem">
                                {code}
                            </div>
                        ))}
                    </div>
                ) : issued ? (
                    <p className="text-sm text-muted-foreground">
                        Codes left: {remaining}. Ask an administrator if you run
                        out.
                    </p>
                ) : (
                    <Button
                        onClick={() => void issueRecoveryCodes()}
                        data-test="issue-recovery-codes"
                    >
                        Show my recovery codes
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}
