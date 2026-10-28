export type User = {
    id: number;
    username: string;
    name: string;
    email: string | null;
    phone: string | null;
    has_password: boolean;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    account_type: 'applicant' | 'staff';
    locale: 'ar' | 'en';
    unread_notifications: number;
    permissions: string[];
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
