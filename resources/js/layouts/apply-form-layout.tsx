import type { ReactNode } from 'react';
import { ApplyHeader } from '@/components/examis/apply-header';
import { BrandBackdrop } from '@/components/examis/brand-backdrop';
import { NetworkStatus } from '@/components/network-status';

/**
 * Shell of the application form: the applying pages' header and backdrop,
 * with no sidebar or toolbar to leave the form by.
 */
export default function ApplyFormLayout({ children }: { children: ReactNode }) {
    return (
        <div className="relative isolate flex min-h-svh flex-col overflow-hidden bg-[var(--examis-pearl)] text-[var(--examis-navy)] dark:bg-[#0a1230] dark:text-white">
            <BrandBackdrop />
            <ApplyHeader />
            <main id="main-content" className="mx-auto w-full max-w-3xl flex-1">
                {children}
            </main>
            <NetworkStatus />
        </div>
    );
}
