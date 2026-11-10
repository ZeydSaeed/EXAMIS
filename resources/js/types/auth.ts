export type User = {
    id: number;
    username: string | null;
    name: string;
    email: string | null;
    phone: string | null;
    has_password: boolean;
    avatar?: string;
    account_type: 'applicant' | 'staff';
    locale: 'ar' | 'en';
    unread_notifications: number;
    permissions: string[];
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};
