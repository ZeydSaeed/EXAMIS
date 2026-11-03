import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import admin from '@/routes/admin';

/** A reader types a whole code faster than this between keys; a person never does. */
const MAX_GAP_MS = 60;

/** Student numbers are five or six digits. */
const CODE = /^\d{5,12}$/;

const isEditable = (target: EventTarget | null) =>
    target instanceof HTMLElement &&
    (target.isContentEditable ||
        ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));

/**
 * The barcode on the printed form holds the student number. A reader types
 * it and presses Enter; caught anywhere on a staff page, it searches the
 * applications, which open that student's application. A field with the
 * focus keeps what is typed into it: the applications search box already
 * runs the same search on Enter.
 */
export function useBarcodeScanner(): void {
    const { auth } = usePage().props;
    const enabled =
        auth.user?.account_type === 'staff' &&
        auth.user.permissions.includes('applications.view');

    useEffect(() => {
        if (!enabled) {
            return;
        }

        let code = '';
        let lastKeyAt = 0;

        const onKeyDown = (event: KeyboardEvent) => {
            if (
                event.ctrlKey ||
                event.metaKey ||
                event.altKey ||
                isEditable(event.target)
            ) {
                code = '';

                return;
            }

            const now = event.timeStamp;
            const fast = now - lastKeyAt <= MAX_GAP_MS;
            lastKeyAt = now;

            if (/^\d$/.test(event.key)) {
                code = fast ? code + event.key : event.key;

                return;
            }

            if (event.key === 'Enter' && fast && CODE.test(code)) {
                event.preventDefault();
                router.get(
                    admin.applications.index.url({ query: { q: code } }),
                );
            }

            code = '';
        };

        window.addEventListener('keydown', onKeyDown, true);

        return () => window.removeEventListener('keydown', onKeyDown, true);
    }, [enabled]);
}
