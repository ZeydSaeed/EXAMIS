import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            organization: string;
            locale: 'ar' | 'en';
            direction: 'rtl' | 'ltr';
            translations: Record<string, unknown>;
            auth: Auth;
            status?: string | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
        flashDataType: {
            toast?: { type: 'success' | 'error'; message: string };
            submitted?: { reference_number: string | null };
        };
    }
}
